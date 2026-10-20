<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Carapi\Cache\FilesystemCache;
use Carapi\DromResolver;
use Carapi\Http\CurlFetchTransport;
use Carapi\Http\FetchTransport;
use Carapi\Http\FileRateLimiter;
use Carapi\Http\PublicAddress;
use Carapi\Http\SafeFetch;
use Carapi\Service;

$failures = 0;
$check = static function (string $label, bool $ok) use (&$failures): void {
    if ($ok) {
        echo "ok {$label}\n";

        return;
    }
    $failures++;
    echo "fail {$label}\n";
};

$blocked = [
    '127.0.0.1', '10.1.1.1', '172.16.0.1', '192.168.1.1', '169.254.1.1', '0.0.0.0',
    '100.64.0.1', '192.0.0.1', '192.0.2.1', '198.18.0.1', '198.51.100.1', '203.0.113.1',
    '224.0.0.1', '240.0.0.1', '255.255.255.255', '::1', 'fc00::1', 'fe80::1',
    '2001:db8::1', 'ff02::1', '::ffff:8.8.8.8', '2002:0808:0808::',
];
foreach ($blocked as $ip) {
    $check('block ' . $ip, !PublicAddress::isPublic($ip));
}
$check('public v4', PublicAddress::isPublic('8.8.8.8'));
$check('public v6', PublicAddress::isPublic('2001:4860:4860::8888'));
$check('pin v6', PublicAddress::pin('cdn.example', '2001:4860:4860::8888') === 'cdn.example:443:[2001:4860:4860::8888]');

$hosts = ['cdn.example', 'img.example'];
$check('reject http', SafeFetch::allowedHost('http://cdn.example/a', $hosts) === null);
$check('reject credentials', SafeFetch::allowedHost('https://user:pass@cdn.example/a', $hosts) === null);
$check('reject ip url', SafeFetch::allowedHost('https://8.8.8.8/a', $hosts) === null);
$check('reject port', SafeFetch::allowedHost('https://cdn.example:8443/a', $hosts) === null);
$check('reject other host', SafeFetch::allowedHost('https://evil.example/a', $hosts) === null);
$check('allow host', SafeFetch::allowedHost('https://CDN.example/a', $hosts) === 'cdn.example');

$opts = CurlFetchTransport::options(['cdn.example:443:8.8.8.8'], 8);
$check('curl https only', ($opts[CURLOPT_PROTOCOLS] ?? 0) === CURLPROTO_HTTPS && ($opts[CURLOPT_REDIR_PROTOCOLS] ?? 0) === CURLPROTO_HTTPS);
$check('curl no follow', ($opts[CURLOPT_FOLLOWLOCATION] ?? true) === false);
$check('curl verify', ($opts[CURLOPT_SSL_VERIFYPEER] ?? false) === true && ($opts[CURLOPT_SSL_VERIFYHOST] ?? 0) === 2);
$check('curl no proxy', ($opts[CURLOPT_PROXY] ?? null) === '' && ($opts[CURLOPT_NOPROXY] ?? '') === '*');
$check('curl pin', ($opts[CURLOPT_RESOLVE] ?? []) === ['cdn.example:443:8.8.8.8']);
$check('curl fresh', ($opts[CURLOPT_FRESH_CONNECT] ?? false) === true && ($opts[CURLOPT_FORBID_REUSE] ?? false) === true && ($opts[CURLOPT_DNS_CACHE_TIMEOUT] ?? 1) === 0);

final class RecordingTransport implements FetchTransport
{
    /** @var list<array{url:string,resolve:list<string>}> */
    public array $calls = [];

    /** @var list<array{status:int,location:?string,body:string,failed:bool}> */
    public array $script = [];

    public function execute(string $url, array $resolve, int $maxBytes, int $timeoutSeconds): array
    {
        $this->calls[] = ['url' => $url, 'resolve' => $resolve];
        $next = array_shift($this->script);

        return $next ?? ['status' => 0, 'location' => null, 'body' => '', 'failed' => true];
    }
}

$dnsCalls = 0;
$dns = static function (string $host) use (&$dnsCalls): array {
    $dnsCalls++;
    return match ($host) {
        'cdn.example' => ['8.8.8.8'],
        'img.example' => ['1.1.1.1'],
        'mixed.example' => ['8.8.8.8', '10.1.1.1'],
        'private.example' => ['127.0.0.1'],
        default => ['2001:db8::1'],
    };
};

$noDns = static function (string $host) use (&$failures): array {
    $failures++;
    echo "fail dns called for {$host}\n";

    return [];
};
$check('empty allowlist skips dns', (new SafeFetch([], $noDns, new RecordingTransport()))->fetch('https://cdn.example/a', 100) === null);

$private = new RecordingTransport();
$private->script[] = ['status' => 200, 'location' => null, 'body' => 'x', 'failed' => false];
$check('private dns skipped', (new SafeFetch(['private.example'], $dns, $private))->fetch('https://private.example/a', 100) === null && $private->calls === []);

$mixed = new RecordingTransport();
$check('mixed dns skipped', (new SafeFetch(['mixed.example'], $dns, $mixed))->fetch('https://mixed.example/a', 100) === null && $mixed->calls === []);

$okTransport = new RecordingTransport();
$okTransport->script[] = ['status' => 302, 'location' => 'https://img.example/p.jpg', 'body' => '', 'failed' => false];
$okTransport->script[] = ['status' => 200, 'location' => null, 'body' => 'image-bytes', 'failed' => false];
$fetched = (new SafeFetch($hosts, $dns, $okTransport))->fetch('https://cdn.example/start', 100);
$check('redirect pinned', $fetched === 'image-bytes'
    && $okTransport->calls[0]['resolve'] === ['cdn.example:443:8.8.8.8']
    && $okTransport->calls[1]['resolve'] === ['img.example:443:1.1.1.1']);

$evil = new RecordingTransport();
$evil->script[] = ['status' => 302, 'location' => 'https://evil.example/a', 'body' => '', 'failed' => false];
$check('reject foreign redirect', (new SafeFetch($hosts, $dns, $evil))->fetch('https://cdn.example/a', 100) === null && count($evil->calls) === 1);

$httpRedirect = new RecordingTransport();
$httpRedirect->script[] = ['status' => 302, 'location' => 'http://cdn.example/a', 'body' => '', 'failed' => false];
$check('reject http redirect', (new SafeFetch($hosts, $dns, $httpRedirect))->fetch('https://cdn.example/a', 100) === null && count($httpRedirect->calls) === 1);

$loop = new RecordingTransport();
for ($i = 0; $i < 5; $i++) {
    $loop->script[] = ['status' => 302, 'location' => '/next', 'body' => '', 'failed' => false];
}
$check('max redirects', (new SafeFetch(['cdn.example'], $dns, $loop))->fetch('https://cdn.example/a', 100) === null && count($loop->calls) === 4);

$huge = new RecordingTransport();
$huge->script[] = ['status' => 200, 'location' => null, 'body' => str_repeat('x', 101), 'failed' => false];
$check('max bytes', (new SafeFetch(['cdn.example'], $dns, $huge))->fetch('https://cdn.example/a', 100) === null);

$limitDir = sys_get_temp_dir() . '/carapi-limit-' . bin2hex(random_bytes(4));
$limiter = new FileRateLimiter($limitDir, 30, 60);
$allowed = 0;
for ($i = 0; $i < 30; $i++) {
    if ($limiter->hit('203.0.113.10', 1_700_000_000)['allowed']) {
        $allowed++;
    }
}
$blockedHit = $limiter->hit('203.0.113.10', 1_700_000_030);
$reset = $limiter->hit('203.0.113.10', 1_700_000_060);
$check('limit 30', $allowed === 30 && $blockedHit['allowed'] === false && $blockedHit['retry_after'] >= 1 && $reset['allowed'] === true);

$closedPath = $limitDir . '-not-dir';
file_put_contents($closedPath, 'x');
$closed = (new FileRateLimiter($closedPath, 30))->hit('203.0.113.10', time());
$check('limit fail closed', $closed['allowed'] === false);
@unlink($closedPath);

$childCode = 'require ' . var_export($root . '/vendor/autoload.php', true) . ';'
    . '$l=new Carapi\\Http\\FileRateLimiter(' . var_export($limitDir . '-race', true) . ', 30);'
    . '$ok=0;$no=0; for($i=0;$i<20;$i++){ $h=$l->hit("203.0.113.50", 1700000000); if($h["allowed"]){$ok++;}else{$no++;} }'
    . 'echo $ok," ",$no,PHP_EOL;';
$runChild = static function () use ($childCode): array {
    $proc = proc_open([PHP_BINARY, '-r', $childCode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return [0, 0];
    }
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    if (!is_string($out) || sscanf($out, '%d %d', $ok, $no) !== 2) {
        return [0, 0];
    }

    return [$ok, $no];
};
$first = proc_open([PHP_BINARY, '-r', $childCode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesA);
$second = proc_open([PHP_BINARY, '-r', $childCode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesB);
$outA = is_resource($first) ? stream_get_contents($pipesA[1]) : '';
$outB = is_resource($second) ? stream_get_contents($pipesB[1]) : '';
if (is_resource($first)) {
    fclose($pipesA[1]);
    fclose($pipesA[2]);
    proc_close($first);
}
if (is_resource($second)) {
    fclose($pipesB[1]);
    fclose($pipesB[2]);
    proc_close($second);
}
$raceOk = 0;
foreach ([$outA, $outB] as $out) {
    if (is_string($out) && sscanf($out, '%d %d', $ok, $no) === 2) {
        $raceOk += $ok;
    }
}
$check('limit concurrent', $raceOk === 30);
unset($runChild);

$cacheDir = sys_get_temp_dir() . '/carapi-cache-' . bin2hex(random_bytes(4));
$cache = new FilesystemCache($cacheDir);
$cache->putImage('abc123abc123abc123abc123abc123ab.jpg', str_repeat('A', 1200), 'image/jpeg');
$cache->setMeta(Service::key('Toyota', 'Camry', 2015), [
    'status' => 1,
    'file' => 'abc123abc123abc123abc123abc123ab.jpg',
    'mime' => 'image/jpeg',
    'source' => 'carapi',
    'title' => 'hidden-title',
    'license' => 'unverified',
    'author' => 'x',
    'attribution' => 'hidden-title',
    'catalog_url' => '',
]);
$metaBefore = file_get_contents($cacheDir . '/meta/' . sha1(Service::key('Toyota', 'Camry', 2015)) . '.json');
$started = microtime(true);
$resolved = DromResolver::resolve('Toyota', 'Camry', 2015);
$elapsed = microtime(true) - $started;
$check('no external resolve', $resolved === null && $elapsed < 1);
$check('cache not served', (new Service($cache))->get('Toyota', 'Camry', 2015) === null);
$metaAfter = file_get_contents($cacheDir . '/meta/' . sha1(Service::key('Toyota', 'Camry', 2015)) . '.json');
$check('cache file kept', $metaBefore === $metaAfter && is_file($cacheDir . '/img/abc123abc123abc123abc123abc123ab.jpg'));

$prodImg = count(glob($root . '/cache/img/*') ?: []);
$prodMeta = count(glob($root . '/cache/meta/*') ?: []);

$host = '127.0.0.1';
$port = 18083;
$cmd = [PHP_BINARY, '-S', $host . ':' . $port, '-t', $root . '/public', $root . '/public/index.php'];
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/tmp/getimage-security.log', 'w'], 2 => ['file', '/tmp/getimage-security.err', 'w']], $pipes, $root, [
    'PATH' => getenv('PATH') ?: '/usr/bin',
    'CARAPI_CACHE_DIR' => $cacheDir,
    'CARAPI_GETIMAGE_LIMIT_DIR' => $limitDir . '-http',
]);
if (!is_resource($proc)) {
    fwrite(STDERR, "server failed to start\n");
    exit(1);
}
$stop = static function () use ($proc, &$pipes): void {
    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    proc_terminate($proc);
    proc_close($proc);
};
$ready = false;
for ($i = 0; $i < 40; $i++) {
    $probe = @fsockopen($host, $port, $errno, $err, 0.2);
    if (is_resource($probe)) {
        fclose($probe);
        $ready = true;
        break;
    }
    usleep(100000);
}
if (!$ready) {
    fwrite(STDERR, "server did not listen\n");
    $stop();
    exit(1);
}

/** @return array{0:int,1:string,2:string} */
function image_raw(string $host, int $port, string $target, string $extra = ''): array
{
    $fp = fsockopen($host, $port, $errno, $err, 3);
    if (!is_resource($fp)) {
        return [0, '', ''];
    }
    fwrite($fp, "GET {$target} HTTP/1.0\r\nHost: {$host}\r\nConnection: close\r\n{$extra}\r\n");
    $raw = stream_get_contents($fp);
    fclose($fp);
    if (!is_string($raw) || !str_contains($raw, "\r\n\r\n")) {
        return [0, '', ''];
    }
    [$head, $body] = explode("\r\n\r\n", $raw, 2);
    $status = 0;
    if (preg_match('#^HTTP/\S+\s+(\d+)#', $head, $match) === 1) {
        $status = (int) $match[1];
    }

    return [$status, $head, $body];
}

[$status, $head, $body] = image_raw($host, $port, '/getImage?make=Toyota');
$check('missing model 400', $status === 400 && str_contains($body, 'make') && str_contains($body, 'model'));

[$status, $head, $body] = image_raw($host, $port, '/getImage?make=Toyota&model=Camry&year=2015&format=json');
$decoded = json_decode($body, true);
$check('json not found', $status === 404 && is_array($decoded) && ($decoded['found'] ?? true) === false && !str_contains($body, 'hidden-title'));

[$status, $head, $body] = image_raw($host, $port, '/getImage?make=Toyota&model=Camry&year=2015', "X-Forwarded-For: 198.51.100.20\r\n");
$check('svg fallback', $status === 404 && str_contains($head, 'image/svg+xml') && str_contains($body, 'no photo found') && !str_contains($body, str_repeat('A', 50)));

$svgOk = 0;
for ($i = 0; $i < 27; $i++) {
    [$status] = image_raw($host, $port, '/getImage?make=Toyota&model=Camry', 'X-Forwarded-For: 198.51.100.' . ($i + 1) . "\r\n");
    if ($status === 404) {
        $svgOk++;
    }
}
[$status, $head, $body] = image_raw($host, $port, '/getImage?make=Toyota&model=Camry&format=json', "X-Forwarded-For: 203.0.113.99\r\n");
$check('rate json', $svgOk === 27 && $status === 429 && str_contains($head, 'Retry-After:') && str_contains($body, 'rate limit exceeded'));
[$status, $head, $body] = image_raw($host, $port, '/getImage?make=Toyota&model=Camry');
$check('rate text', $status === 429 && str_contains($body, 'rate limit exceeded') && !str_contains($head, 'image/svg+xml'));

$stop();
$check('production cache unchanged', count(glob($root . '/cache/img/*') ?: []) === $prodImg && count(glob($root . '/cache/meta/*') ?: []) === $prodMeta);

if ($failures > 0) {
    fwrite(STDERR, "failures={$failures}\n");
    exit(1);
}
echo "getimage security tests passed\n";

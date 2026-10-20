<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Carapi\\')) {
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};

$rows = Carapi\Catalog\VehiclePickerRepository::disambiguate([
    ['id' => '1', 'label' => 'XV80 · 2023–'],
    ['id' => '2', 'label' => 'XV80 · 2023–'],
    ['id' => '3', 'label' => 'XV70 · 2017–2024'],
]);
$check($rows[0]['label'] === 'XV80 · 2023– · 1' && $rows[1]['label'] === 'XV80 · 2023– · 2' && $rows[2]['label'] === 'XV70 · 2017–2024', 'duplicate generation labels keep an id');

$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
$ka = 'ავტორიზაცია ვერ შესრულდა. API გასაღები არ არის მითითებული, არასწორია ან ვეღარ გამოიყენება.';
$check(str_starts_with(Carapi\Catalog\ApiMessages::localize($ka), 'Authorization failed'), 'english Accept-Language translates 401');
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ru';
$check(str_starts_with(Carapi\Catalog\ApiMessages::localize($ka), 'Authorization failed'), 'russian Accept-Language uses english');
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ka';
$check(Carapi\Catalog\ApiMessages::localize($ka) === $ka, 'georgian Accept-Language stays georgian');

$spec = Carapi\Catalog\PublicOpenApi::document();
$check(isset($spec['components']['schemas']['Brand'], $spec['paths']['/api/v1/brands'], $spec['paths']['/widget/v1/{publicKey}/brands']), 'openapi has schemas and widget path');
$check(!str_contains(json_encode($spec), 'carapi_pk_example'), 'openapi does not advertise a fake key');

$port = 18191;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/hm-audit.log', 'w'], 2 => ['file', '/tmp/hm-audit.err', 'w']], $pipes, $root, []);
usleep(300000);
$http = static function (string $path, array $headers = []) use ($port): array {
    $ch = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'header' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
};

$home = $http('/');
$check($home['status'] === 200 && str_contains($home['header'], 'Content-Security-Policy') && str_contains($home['header'], 'X-Frame-Options: DENY') && str_contains($home['body'], 'rel="canonical"') && str_contains($home['body'], 'hreflang="en"') && !str_contains($home['body'], 'Stack trace'), 'home has security and seo headers');
$check(str_contains($home['body'], 'შასის კოდი'), 'home explains the generation count');

$en = $http('/?lang=en');
$check($en['status'] === 200 && str_contains($en['body'], 'CarAPI — car catalog, API, and widget') && str_contains($en['body'], 'Select a brand'), 'english locale translates title and picker');

$redirect = $http('/en');
$check($redirect['status'] === 302 && str_contains($redirect['header'], 'Location: /locale/en'), '/en redirects to /locale/en');

$pricing = $http('/pricing');
$check($pricing['status'] === 200 && str_contains($pricing['body'], '10 000') && str_contains($pricing['body'], '60') && !preg_match('/\\$\\d/', $pricing['body']), 'pricing shows the free limit only');

$robots = $http('/robots.txt');
$check($robots['status'] === 200 && str_contains($robots['body'], 'Sitemap:'), 'robots points at the sitemap');
$sitemap = $http('/sitemap.xml');
$check($sitemap['status'] === 200 && str_contains($sitemap['body'], '/pricing') && str_contains($sitemap['body'], '/guide'), 'sitemap lists public pages');

$docs = $http('/api/docs/openapi.json', ['Accept: application/json']);
$check($docs['status'] === 200 && str_contains($docs['header'], 'application/json') && !str_contains($docs['body'], 'exception') && !str_contains($docs['body'], '/opt/'), 'public openapi has no stack trace');
$page = $http('/api/docs');
$check($page['status'] === 200 && str_contains($page['body'], 'POST /widget/v1/'), 'api docs describe the widget path');

$guide = $http('/guide');
$check($guide['status'] === 200 && str_contains($guide['body'], 'demo') && str_contains($guide['body'], '/widget/v1/') && !str_contains($guide['body'], 'carapi_pk_example'), 'guide example uses the demo key');

proc_terminate($proc);
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

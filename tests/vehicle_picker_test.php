<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$catalogUser = '';
$catalogSecret = '';
foreach (file('/www/server/carapi/catalog.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_CATALOG_PASSWORD=')) {
        $catalogSecret = substr($line, 26);
    }
    if (str_starts_with($line, 'CARAPI_CATALOG_USER=')) {
        $catalogUser = substr($line, 20);
    }
}
if ($catalogUser === '') {
    fwrite(STDERR, "CARAPI_CATALOG_USER is empty\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

$admin = new PDO('pgsql:host=127.0.0.1;dbname=carbase', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$chain = $admin->query(<<<'SQL'
    SELECT b.id AS brand_id, mo.id AS model_id, g.id AS generation_id, mod.id AS modification_id,
           mod.year_start AS year
    FROM public.model_photos p
    JOIN public.models mo ON mo.id = p.model_id
    JOIN public.brands b ON b.id = mo.brand_id
    JOIN public.generations g ON g.model_id = mo.id
    JOIN public.modifications mod ON mod.generation_id = g.id
    JOIN public.modification_specs s ON s.modification_id = mod.id
    WHERE p.verified
      AND p.image IS NOT NULL
      AND g.year_start IS NOT NULL
      AND mod.year_start IS NOT NULL
      AND mod.year_start >= g.year_start
      AND mod.year_start <= COALESCE(g.year_end, EXTRACT(YEAR FROM CURRENT_DATE)::int)
      AND jsonb_exists(s.specs, 'power_hp')
      AND mod.transmission ~ '^(MT|AT|AMT|CVT)'
      AND mod.drive_type IN ('FWD', 'RWD', '4WD')
    LIMIT 1
SQL)->fetch(PDO::FETCH_ASSOC);
$hidden = $admin->query('SELECT model_id FROM public.model_photos WHERE verified = false AND image IS NOT NULL LIMIT 1')->fetchColumn();
$missing = $admin->query('SELECT m.id FROM public.models m WHERE NOT EXISTS (SELECT 1 FROM public.model_photos p WHERE p.model_id = m.id) LIMIT 1')->fetchColumn();
if (!is_array($chain) || $hidden === false || $missing === false) {
    fwrite(STDERR, "fixture query failed\n");
    exit(1);
}

$port = 18121;
$env = [
    'CARAPI_CATALOG_ENABLED' => '1',
    'CARAPI_CATALOG_HOST' => '127.0.0.1',
    'CARAPI_CATALOG_PORT' => '5432',
    'CARAPI_CATALOG_DATABASE' => 'carbase',
    'CARAPI_CATALOG_USER' => $catalogUser,
    'CARAPI_CATALOG_PASSWORD' => $catalogSecret,
    'CARAPI_CATALOG_STATEMENT_TIMEOUT_MS' => '3000',
];
$proc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
    [1 => ['file', '/tmp/vehicle-picker.log', 'w'], 2 => ['file', '/tmp/vehicle-picker.err', 'w']],
    $pipes,
    $root,
    $env
);
if (!is_resource($proc)) {
    fwrite(STDERR, "server failed to start\n");
    exit(1);
}

$base = 'http://127.0.0.1:' . $port;
$origin = $base;
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};
$call = static function (string $method, string $path, bool $withOrigin = true) use ($base, $origin): array {
    $ch = curl_init($base . $path);
    $headers = ['Accept: application/json'];
    if ($withOrigin) {
        $headers[] = 'Origin: ' . $origin;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'header' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
};

usleep(200000);
$ready = $call('GET', '/demo');
$check($ready['status'] === 200, 'demo page responds');

try {
    $page = $call('GET', '/demo');
    $check(
        str_contains($page['body'], 'Vehicle picker')
        && str_contains($page['body'], 'Select brand')
        && str_contains($page['body'], 'No photo yet')
        && str_contains($page['body'], '>Standard<')
        && str_contains($page['body'], '>Optional<')
        && str_contains($page['body'], '>Not available<')
        && str_contains($page['body'], 'lang="en"'),
        'picker copy is English'
    );

    $home = $call('GET', '/');
    $check(str_contains($home['body'], 'href="/demo"'), 'landing links to the picker');

    $denied = $call('GET', '/api/picker/brands', false);
    $check($denied['status'] === 403, 'brands without origin rejected');

    $brands = $call('GET', '/api/picker/brands?per_page=1');
    $brandPayload = json_decode($brands['body'], true);
    $check(
        $brands['status'] === 200
        && is_array($brandPayload)
        && ($brandPayload['meta']['per_page'] ?? null) === 1
        && ($brandPayload['meta']['total'] ?? 0) > 1
        && isset($brandPayload['data'][0]['id'], $brandPayload['data'][0]['name'])
        && !str_contains($brands['body'], 'source_url')
        && !str_contains($brands['body'], 'drom_'),
        'brands are paged'
    );

    $year = (string) $chain['year'];
    $models = $call('GET', '/api/picker/brands/' . $chain['brand_id'] . '/models?per_page=100');
    $modelPayload = json_decode($models['body'], true);
    $modelIds = array_column($modelPayload['data'] ?? [], 'id');
    $check($models['status'] === 200 && in_array((string) $chain['model_id'], $modelIds, true), 'models filtered by brand');

    $years = $call('GET', '/api/picker/models/' . $chain['model_id'] . '/years?per_page=100');
    $yearIds = array_column(json_decode($years['body'], true)['data'] ?? [], 'id');
    $check($years['status'] === 200 && in_array($year, $yearIds, true), 'years come from generation ranges');

    $outside = $call('GET', '/api/picker/models/' . $chain['model_id'] . '/generations?year=1880&per_page=100');
    $outsidePayload = json_decode($outside['body'], true);
    $check($outside['status'] === 200 && ($outsidePayload['meta']['total'] ?? -1) === 0, 'year outside the range returns no generations');

    $generations = $call('GET', '/api/picker/models/' . $chain['model_id'] . '/generations?year=' . $year . '&per_page=100');
    $generationIds = array_column(json_decode($generations['body'], true)['data'] ?? [], 'id');
    $check($generations['status'] === 200 && in_array((string) $chain['generation_id'], $generationIds, true), 'generations filtered by year');

    $mods = ['status' => 0, 'body' => ''];
    $modPayload = ['meta' => ['total' => 0], 'data' => []];
    $sample = null;
    for ($page = 1; $page <= 40 && $sample === null; $page++) {
        $mods = $call('GET', '/api/picker/generations/' . $chain['generation_id'] . '/modifications?year=' . $year . '&per_page=100&page=' . $page);
        $modPayload = json_decode($mods['body'], true);
        if (!is_array($modPayload)) {
            break;
        }
        foreach ($modPayload['data'] ?? [] as $row) {
            if ((string) $row['id'] === (string) $chain['modification_id']) {
                $sample = $row;
                break;
            }
        }
        $total = (int) ($modPayload['meta']['total'] ?? 0);
        if ($page * 100 >= $total) {
            break;
        }
    }
    $check(
        $mods['status'] === 200
        && ($modPayload['meta']['total'] ?? 0) > 0
        && ($modPayload['meta']['total'] ?? 0) < 10000
        && $sample !== null
        && in_array($sample['transmission'], ['automatic', 'manual', 'robot', 'CVT'], true)
        && in_array($sample['drive'], ['FWD', 'RWD', 'AWD 4x4'], true)
        && str_contains((string) $sample['label'], (string) $sample['transmission']),
        'modifications stay on one generation and label the drivetrain'
    );

    $missingYear = $call('GET', '/api/picker/generations/' . $chain['generation_id'] . '/modifications');
    $check($missingYear['status'] === 422, 'modifications require a year');
    $check($call('POST', '/api/picker/brands')['status'] === 405, 'picker rejects POST');
    $check($call('GET', '/api/picker/brands/0/models')['status'] === 422, 'bad brand id rejected');

    $detail = $call('GET', '/api/picker/modifications/' . $chain['modification_id']);
    $detailPayload = json_decode($detail['body'], true);
    $data = is_array($detailPayload) ? ($detailPayload['data'] ?? []) : [];
    $equipmentCount = count($data['equipment']['standard'] ?? [])
        + count($data['equipment']['optional'] ?? [])
        + count($data['equipment']['not_available'] ?? []);
    $expectedEquipment = (int) $admin->query('SELECT count(*) FROM public.modification_equipment WHERE modification_id = ' . (int) $chain['modification_id'] . " AND status IN ('included', 'option', 'not_available')")->fetchColumn();
    $check(
        $detail['status'] === 200
        && ($data['has_photo'] ?? false) === true
        && ($data['brand']['name'] ?? '') !== ''
        && ($data['model']['id'] ?? '') === (string) $chain['model_id']
        && ($data['power_hp'] ?? null) !== null
        && isset($data['equipment']['standard'], $data['equipment']['optional'], $data['equipment']['not_available'])
        && $equipmentCount === $expectedEquipment
        && !str_contains($detail['body'], 'source_url')
        && !str_contains($detail['body'], 'drom_'),
        'modification card payload is filtered'
    );

    $photo = $call('GET', '/api/models/' . $chain['model_id'] . '/photo', false);
    $check(
        $photo['status'] === 200
        && str_contains($photo['header'], 'Content-Type: image/jpeg')
        && str_starts_with($photo['body'], "\xFF\xD8\xFF"),
        'verified photo is jpeg'
    );
    $check($call('GET', '/api/models/' . $hidden . '/photo', false)['status'] === 404, 'unverified photo hidden');
    $check($call('GET', '/api/models/' . $missing . '/photo', false)['status'] === 404, 'missing photo hidden');
    $check($call('GET', '/api/models/nope/photo', false)['status'] === 422, 'bad photo id rejected');
} finally {
    proc_terminate($proc);
    proc_close($proc);
}

if ($failures !== []) {
    fwrite(STDERR, 'failed: ' . implode(', ', $failures) . "\n");
    exit(1);
}
echo "passed\n";

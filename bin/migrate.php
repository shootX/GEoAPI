<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$host = getenv('CARAPI_PLATFORM_ADMIN_HOST') ?: '127.0.0.1';
$port = getenv('CARAPI_PLATFORM_ADMIN_PORT') ?: '5432';
$database = getenv('CARAPI_PLATFORM_ADMIN_DATABASE') ?: '';
$user = getenv('CARAPI_PLATFORM_ADMIN_USER') ?: '';
$password = getenv('CARAPI_PLATFORM_ADMIN_PASSWORD');
$password = is_string($password) ? $password : '';
$files = glob($root . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

if ($database === '' || $user === '' || $files === []) {
    fwrite(STDERR, "admin database configuration is incomplete\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=3', $host, (int) $port, $database),
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

foreach ($files as $file) {
    $version = basename($file, '.sql');
    $applied = false;
    try {
        $check = $pdo->prepare('SELECT 1 FROM platform.schema_migrations WHERE version = :version');
        $check->execute(['version' => $version]);
        $applied = $check->fetchColumn() !== false;
    } catch (PDOException) {
        $applied = false;
    }
    if ($applied) {
        echo "already applied {$version}\n";
        continue;
    }
    $pdo->beginTransaction();
    $pdo->exec(file_get_contents($file) ?: '');
    $insert = $pdo->prepare('INSERT INTO platform.schema_migrations (version) VALUES (:version)');
    $insert->execute(['version' => $version]);
    $pdo->commit();
    echo "applied {$version}\n";
}

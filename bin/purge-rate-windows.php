<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Carapi\Platform\Connection::pdo();
$deleted = $pdo->exec("DELETE FROM platform.rate_windows WHERE window_start < now() - interval '2 minutes'");
echo "purged {$deleted}\n";

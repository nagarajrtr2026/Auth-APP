<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/php/config.php';

if (!extension_loaded('mongodb')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP mongodb extension is not loaded.';
    exit(1);
}

$client = new MongoDB\Client(MONGO_URI);
$client->selectDatabase(MONGO_DB_NAME)->command(['ping' => 1]);

header('Content-Type: text/plain; charset=utf-8');
echo 'MongoDB connected successfully (database: ' . MONGO_DB_NAME . ').';

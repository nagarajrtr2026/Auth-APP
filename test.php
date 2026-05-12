<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/php/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!extension_loaded('mongodb')) {
    http_response_code(503);
    echo "FAIL: PHP mongodb extension is not loaded.\n";
    exit(1);
}

echo "Atlas / MongoDB connection test\n";
echo str_repeat('-', 48) . "\n";
echo 'MONGO_URI (host only): ' . preg_replace('#//([^:]+):[^@]+@#', '//***:***@', MONGO_URI) . "\n";
echo 'TLS: tlsCAFile appended for Atlas when CA bundle is found (see php/certs/cacert.pem).' . "\n";
echo 'MONGO_DB_NAME: ' . MONGO_DB_NAME . "\n";
echo 'PHP: ' . PHP_VERSION . ' | ext mongodb: ' . phpversion('mongodb') . "\n";
echo 'OpenSSL: ' . OPENSSL_VERSION_TEXT . "\n";
echo str_repeat('-', 48) . "\n";

try {
    $client = new MongoDB\Client(mongo_connection_string());
    $client->selectDatabase(MONGO_DB_NAME)->command(['ping' => 1]);
    echo "OK: Connected to MongoDB Atlas and ping succeeded.\n";
    exit(0);
} catch (Throwable $e) {
    http_response_code(502);
    echo 'FAIL: ' . $e->getMessage() . "\n\n";
    echo "If you see \"tlsv1 alert internal error\" during TLS hello:\n";
    echo "1) Atlas → Network Access → ADD IP ADDRESS → use \"Allow access from anywhere\" (0.0.0.0/0) for dev, or add your current public IP. Missing allowlist often shows as TLS errors.\n";
    echo "2) Turn off VPN / corporate proxy / HTTPS inspection and try again.\n";
    echo "3) Confirm the DB user/password in the URI (Atlas → Database Access).\n";
    echo "4) php.ini (Apache): openssl.cafile + curl.cainfo → Mozilla cacert.pem; project ships php/certs/cacert.pem and the app appends tlsCAFile for Atlas.\n";
    echo "Docs: https://www.mongodb.com/docs/atlas/troubleshoot-connection/\n";
    exit(1);
}

<?php
/**
 * GET — JSON status for MySQL, MongoDB, and Redis (same settings as config.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$mysqlOk = false;
$mongoOk = false;
$mysql = ['status' => 'error', 'database' => MYSQL_DB];
$mongoExt = extension_loaded('mongodb');
$mongo = [
    'status' => 'error',
    'database' => MONGO_DB_NAME,
    'collection' => MONGO_COLLECTION_PROFILES,
    'php_extension_mongodb' => $mongoExt,
    'uri' => mongo_uri_public_display(),
    'tls' => str_contains(MONGO_URI, 'mongodb+srv://')
        ? 'Atlas: tlsCAFile + tlsDisableOCSPEndpointCheck (unless MONGO_TLS_STRICT=1). Set MONGO_TLS_CAFILE to override CA path.'
        : 'Direct / local connection.',
];
$redis = ['status' => 'unknown'];

try {
    $mysqli = db_mysql();
    $mysqli->query('SELECT 1');
    $mysqli->close();
    $mysqlOk = true;
    $mysql = ['status' => 'ok', 'database' => MYSQL_DB];
} catch (Throwable $e) {
    $mysql['message'] = $e->getMessage();
}

if (!$mongoExt) {
    $mongo['message'] = 'PHP mongodb extension is not enabled for this server (Apache/nginx often uses a different php.ini than CLI).';
    $mongo['hint'] = 'Open phpinfo() from the browser, note "Loaded Configuration File", add extension=mongodb there, restart the web server.';
} else {
    try {
        $profiles = db_mongo_profiles();
        $count = $profiles->estimatedDocumentCount();
        $mongoOk = true;
        $mongo['status'] = 'ok';
        $mongo['profiles_document_count_estimate'] = $count;
    } catch (Throwable $e) {
        $mongo['message'] = $e->getMessage();
        if (str_contains(MONGO_URI, 'mongodb+srv://') || str_contains(MONGO_URI, '.mongodb.net')) {
            $mongo['hint'] = 'Atlas: open Network Access and allow your IP (or 0.0.0.0/0 for dev). TLS "internal error" during hello usually means Atlas closed the socket before auth—often IP allowlist or VPN/proxy. Verify user/password under Database Access. See https://www.mongodb.com/docs/atlas/troubleshoot-connection/';
        } else {
            $mongo['hint'] = 'Ensure mongod is running on the host in MONGO_URI, firewall allows the port, and MONGO_URI in config/env is correct.';
        }
    }
}

$r = db_redis();
if ($r === null) {
    $predis = predis_session_client();
    if ($predis !== null) {
        $redis = ['status' => 'ok', 'driver' => 'predis', 'endpoint' => REDIS_HOST . ':' . REDIS_PORT];
    } else {
        $redis = ['status' => 'unavailable', 'message' => 'php-redis and Predis could not connect'];
    }
} else {
    try {
        $r->ping();
        $redis = ['status' => 'ok', 'driver' => 'phpredis', 'endpoint' => REDIS_HOST . ':' . REDIS_PORT];
    } catch (Throwable $e) {
        $redis = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

$payload = [
    'ok' => $mysqlOk && $mongoOk,
    'mysql' => $mysql,
    'mongodb' => $mongo,
    'redis' => $redis,
];

http_response_code($payload['ok'] ? 200 : 503);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

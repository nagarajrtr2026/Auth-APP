<?php
/**
 * Central configuration for MySQL, MongoDB, and optional Redis.
 * Internship Project Config
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------------
// MYSQL CONFIG
// -----------------------------
define('MYSQL_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
define('MYSQL_PORT', (int) (getenv('MYSQL_PORT') ?: '3306'));
define('MYSQL_DB', getenv('MYSQL_DB') ?: 'mt_auth');
define('MYSQL_USER', getenv('MYSQL_USER') ?: 'root');
define('MYSQL_PASS', getenv('MYSQL_PASS') ?: '');

// -----------------------------
// MONGODB CONFIG — local mongod (connect Compass with mongodb://127.0.0.1:27017)
// Override with MONGO_URI / MONGO_DB_NAME env if needed.
// -----------------------------
define('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017');
define('MONGO_DB_NAME', getenv('MONGO_DB_NAME') ?: 'mt_auth');
define('MONGO_COLLECTION_PROFILES', 'profiles');

// -----------------------------
// REDIS CONFIG (OPTIONAL)
// -----------------------------
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', (int) (getenv('REDIS_PORT') ?: '6379'));
define('REDIS_PASS', getenv('REDIS_PASS') ?: null);

define('SESSION_TTL_SECONDS', 86400); // 24 hours

// -----------------------------
// JSON RESPONSE HELPER
// -----------------------------
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    echo json_encode($data, JSON_UNESCAPED_UNICODE);

    exit;
}

// -----------------------------
// READ JSON BODY
// -----------------------------
function read_json_body(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

// -----------------------------
// MYSQL CONNECTION
// -----------------------------
function db_mysql(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $mysqli = new mysqli(
        MYSQL_HOST,
        MYSQL_USER,
        MYSQL_PASS,
        MYSQL_DB,
        MYSQL_PORT
    );

    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

// -----------------------------
// MONGODB CONNECTION
// -----------------------------
function db_mongo_profiles(): object
{
    if (!extension_loaded('mongodb')) {
        throw new RuntimeException(
            'PHP mongodb extension is not loaded. In php.ini used by your web server, enable: extension=mongodb (then restart Apache / PHP).'
        );
    }

    if (!class_exists(\MongoDB\Client::class)) {
        throw new RuntimeException(
            'MongoDB library missing. Run composer install in the project root.'
        );
    }

    $client = new \MongoDB\Client(MONGO_URI);

    return $client
        ->selectDatabase(MONGO_DB_NAME)
        ->selectCollection(MONGO_COLLECTION_PROFILES);
}

// -----------------------------
// REDIS CONNECTION (OPTIONAL)
// -----------------------------
function db_redis(): ?Redis
{
    if (!class_exists(Redis::class)) {
        return null;
    }

    try {

        $redis = new Redis();

        $redis->connect(REDIS_HOST, REDIS_PORT);

        if (REDIS_PASS !== null && REDIS_PASS !== '') {
            $redis->auth(REDIS_PASS);
        }

        return $redis;

    } catch (Exception $e) {

        return null;
    }
}

// -----------------------------
// REDIS SESSION KEY
// -----------------------------
function redis_session_key(string $token): string
{
    return 'session:' . $token;
}

/**
 * Predis client (Composer) when ext-redis is unavailable; null if Redis unreachable.
 */
function predis_session_client(): ?\Predis\Client
{
    static $cached = null;

    if ($cached instanceof \Predis\Client) {
        return $cached;
    }

    if (!class_exists(\Predis\Client::class)) {
        return null;
    }

    $parameters = [
        'host' => REDIS_HOST,
        'port' => REDIS_PORT,
    ];

    if (REDIS_PASS !== null && REDIS_PASS !== '') {
        $parameters['password'] = REDIS_PASS;
    }

    try {
        $client = new \Predis\Client($parameters);
        $client->ping();
        $cached = $client;

        return $cached;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Read opaque session JSON from Redis (phpredis or Predis).
 */
function session_store_get(string $token): ?string
{
    $key = redis_session_key($token);
    $ext = db_redis();

    if ($ext !== null) {
        $raw = $ext->get($key);

        if ($raw === false || $raw === null) {
            return null;
        }

        return (string) $raw;
    }

    $predis = predis_session_client();

    if ($predis === null) {
        return null;
    }

    try {
        $raw = $predis->get($key);

        if ($raw === null) {
            return null;
        }

        return (string) $raw;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Write session payload to Redis with TTL (phpredis or Predis).
 */
function session_store_set(string $token, string $payload): void
{
    $key = redis_session_key($token);
    $ttl = SESSION_TTL_SECONDS;
    $ext = db_redis();

    if ($ext !== null) {
        $ext->setex($key, $ttl, $payload);

        return;
    }

    $predis = predis_session_client();

    if ($predis !== null) {
        $predis->setex($key, $ttl, $payload);

        return;
    }

    throw new RuntimeException(
        'Redis is not available. Start Redis on '
        . REDIS_HOST . ':' . REDIS_PORT
        . ' or enable the php-redis extension (Predis is used as a fallback when the extension is missing).'
    );
}
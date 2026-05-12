<?php
/**
 * Central configuration for MySQL, MongoDB, and optional Redis.
 * Internship Project Config
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Optional project root .env (KEY=value). Does not override existing server env vars.
$__envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_readable($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $__line) {
        $__line = trim($__line);
        if ($__line === '' || str_starts_with($__line, '#')) {
            continue;
        }
        if (!str_contains($__line, '=')) {
            continue;
        }
        [$__k, $__v] = explode('=', $__line, 2);
        $__k = trim($__k);
        $__v = trim($__v);
        if ($__k === '') {
            continue;
        }
        if ($__v !== '' && (($__v[0] === '"' && str_ends_with($__v, '"')) || ($__v[0] === "'" && str_ends_with($__v, "'")))) {
            $__v = substr($__v, 1, -1);
        }
        if (getenv($__k) !== false) {
            continue;
        }
        putenv($__k . '=' . $__v);
        $_ENV[$__k] = $__v;
    }
}
unset($__envFile, $__line, $__k, $__v);

// -----------------------------
// MYSQL CONFIG
// -----------------------------
define('MYSQL_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
define('MYSQL_PORT', (int) (getenv('MYSQL_PORT') ?: '3306'));
define('MYSQL_DB', getenv('MYSQL_DB') ?: 'mt_auth');
define('MYSQL_USER', getenv('MYSQL_USER') ?: 'root');
define('MYSQL_PASS', getenv('MYSQL_PASS') ?: '');

// -----------------------------
// MONGODB CONFIG — Atlas for deployment (mongodb+srv). Local dev: set MONGO_URI in .env
// to mongodb://127.0.0.1:27017 or use Atlas. Prefer secrets in .env (see .env.example).
// -----------------------------
define(
    'MONGO_URI',
    getenv('MONGO_URI') ?: 'mongodb+srv://manivasgam15_db_user:dgSMRDDppHMlWuXM@cluster0.bi2vawe.mongodb.net/'
);
define('MONGO_DB_NAME', getenv('MONGO_DB_NAME') ?: 'mt_auth');
define('MONGO_COLLECTION_PROFILES', 'profiles');

/**
 * Build MongoDB URI for Atlas / TLS: CA bundle + OCSP workaround (common on Windows/XAMPP).
 * Set MONGO_TLS_STRICT=1 in the environment to skip OCSP relaxation.
 */
function mongo_connection_string(): string
{
    $uri = MONGO_URI;

    $needsCa = str_starts_with($uri, 'mongodb+srv://')
        || (str_starts_with($uri, 'mongodb://') && str_contains($uri, '.mongodb.net'));

    if (! $needsCa) {
        return $uri;
    }

    $append = [];

    if (! str_contains($uri, 'retryWrites=')) {
        $append['retryWrites'] = 'true';
    }
    if (! str_contains($uri, 'w=') && ! str_contains($uri, 'w%3D')) {
        $append['w'] = 'majority';
    }

    if (getenv('MONGO_TLS_STRICT') !== '1') {
        if (! str_contains($uri, 'tlsDisableOCSPEndpointCheck=')) {
            $append['tlsDisableOCSPEndpointCheck'] = 'true';
        }
    }

    if (! str_contains($uri, 'tlsCAFile=')) {
        $candidates = [];
        $envCa = getenv('MONGO_TLS_CAFILE');
        if (is_string($envCa) && $envCa !== '') {
            $candidates[] = $envCa;
        }
        $iniCa = ini_get('openssl.cafile');
        if (is_string($iniCa) && $iniCa !== '') {
            $candidates[] = $iniCa;
        }
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';

        foreach ($candidates as $path) {
            if ($path !== '' && is_readable($path)) {
                $append['tlsCAFile'] = str_replace('\\', '/', $path);
                break;
            }
        }
    }

    if ($append === []) {
        return $uri;
    }

    $qs = http_build_query($append, '', '&', PHP_QUERY_RFC3986);
    $sep = str_contains($uri, '?') ? '&' : '?';

    return $uri . $sep . $qs;
}

/** Mask password for logs / JSON (never expose Atlas credentials in API output). */
function mongo_uri_public_display(): string
{
    return preg_replace('#//([^:]+):[^@]+@#', '//***:***@', MONGO_URI);
}

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

    $client = new \MongoDB\Client(mongo_connection_string());

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
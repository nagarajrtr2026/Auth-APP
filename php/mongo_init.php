<?php
/**
 * One-time / dev helper: creates database mt_auth, collection profiles, and unique index
 * so they show in Atlas / Compass even before any user registers.
 *
 * Open once in a browser: .../php/mongo_init.php
 * (Do not expose this URL on a public server without protection.)
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use GET or POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!extension_loaded('mongodb')) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'PHP mongodb extension is not loaded.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $client = new MongoDB\Client(mongo_connection_string());
    $db = $client->selectDatabase(MONGO_DB_NAME);
    $collectionName = MONGO_COLLECTION_PROFILES;

    $existing = [];
    foreach ($db->listCollectionNames() as $n) {
        $existing[] = $n;
    }

    $createdCollection = false;
    if (!in_array($collectionName, $existing, true)) {
        $db->createCollection($collectionName);
        $createdCollection = true;
    }

    $profiles = $db->selectCollection($collectionName);
    $profiles->createIndex(
        ['user_id' => 1, 'tenant_id' => 1],
        ['unique' => true, 'name' => 'uq_user_tenant']
    );

    echo json_encode([
        'success' => true,
        'message' => 'MongoDB namespace is ready. Refresh Compass / Atlas → Browse Collections.',
        'database' => MONGO_DB_NAME,
        'collection' => $collectionName,
        'created_empty_collection' => $createdCollection,
        'index' => 'uq_user_tenant on { user_id: 1, tenant_id: 1 } (unique)',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'hint' => 'Check Atlas Network Access (your IP), Database user password, and MONGO_URI in php/config.php.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

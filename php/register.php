<?php
/**
 * POST JSON: tenant_id?, name, email, password
 * Creates MySQL user + MongoDB profile stub (multi-tenant aware).
 */
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = read_json_body();

$tenantId = isset($data['tenant_id']) ? trim((string) $data['tenant_id']) : 'default';
$name = isset($data['name']) ? trim((string) $data['name']) : '';
$email = isset($data['email']) ? trim(strtolower((string) $data['email'])) : '';
$password = isset($data['password']) ? (string) $data['password'] : '';

if ($tenantId === '' || strlen($tenantId) > 64) {
    json_response(['success' => false, 'message' => 'Invalid tenant identifier.'], 422);
}
if ($name === '' || strlen($name) > 255) {
    json_response(['success' => false, 'message' => 'Please enter your name.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email.'], 422);
}
if (strlen($password) < 8) {
    json_response(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $mysqli = db_mysql();
    $stmt = $mysqli->prepare('INSERT INTO users (tenant_id, name, email, password) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $tenantId, $name, $email, $hash);
    $stmt->execute();
    $userId = (int) $mysqli->insert_id;
    $stmt->close();

    // MongoDB profile stub (optional at signup). If Atlas/local Mongo is down, account still works;
    // profile.php will upsert the document when Mongo is reachable.
    $profileSynced = true;
    try {
        $profiles = db_mongo_profiles();
        $profiles->insertOne([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'age' => null,
            'dob' => null,
            'contact' => null,
            'bio' => null,
        ]);
    } catch (Throwable) {
        $profileSynced = false;
    }

    $mysqli->close();

    $msg = $profileSynced
        ? 'Registration successful. You can sign in now.'
        : 'Registration successful. You can sign in now. (Extended profile will sync when the profile database is available.)';

    json_response([
        'success' => true,
        'message' => $msg,
        'profile_synced' => $profileSynced,
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'tenant_id' => $tenantId],
    ]);
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        json_response(['success' => false, 'message' => 'An account with this email already exists for this tenant. Use another email or sign in.'], 409);
    }
    json_response(['success' => false, 'message' => 'Database error. Please try again.'], 500);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

<?php
/**
 * POST JSON: tenant_id?, email, password
 * Verifies credentials; stores session token in Redis; returns token for localStorage.
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
$email = isset($data['email']) ? trim(strtolower((string) $data['email'])) : '';
$password = isset($data['password']) ? (string) $data['password'] : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

try {
    $mysqli = db_mysql();
    $stmt = $mysqli->prepare('SELECT id, tenant_id, name, email, password FROM users WHERE email = ? AND tenant_id = ? LIMIT 1');
    $stmt->bind_param('ss', $email, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($password, $row['password'])) {
        json_response(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    $token = bin2hex(random_bytes(32));
    $payload = json_encode([
        'user_id' => (int) $row['id'],
        'tenant_id' => $row['tenant_id'],
        'email' => $row['email'],
    ], JSON_UNESCAPED_UNICODE);

    session_store_set($token, $payload);

    json_response([
        'success' => true,
        'message' => 'Signed in successfully.',
        'token' => $token,
        'user' => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'tenant_id' => $row['tenant_id'],
        ],
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

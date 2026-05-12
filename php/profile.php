<?php
/**
 * GET: load MySQL user + MongoDB profile (requires token).
 * POST: update name/email (MySQL) + profile fields (MongoDB).
 *
 * Token: header X-Session-Token or JSON body "token"
 */
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Resolve bearer token from header or JSON/query (GET allows query for convenience).
 */
function resolve_token(): ?string
{
    $h = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
    if (is_string($h) && $h !== '') {
        return trim($h);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
        return trim((string) $_GET['token']);
    }
    $data = read_json_body();
    if (isset($data['token']) && is_string($data['token'])) {
        return trim($data['token']);
    }
    return null;
}

function session_from_redis(string $token): ?array
{
    $raw = session_store_get($token);
    if ($raw === null || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = resolve_token();
    if ($token === null || $token === '') {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $session = session_from_redis($token);
    if ($session === null || !isset($session['user_id'])) {
        json_response(['success' => false, 'message' => 'Invalid or expired session.'], 401);
    }

    $userId = (int) $session['user_id'];
    $tenantId = (string) ($session['tenant_id'] ?? 'default');

    try {
        $mysqli = db_mysql();
        $stmt = $mysqli->prepare('SELECT id, tenant_id, name, email, created_at FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->bind_param('is', $userId, $tenantId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userRow) {
            json_response(['success' => false, 'message' => 'User not found.'], 404);
        }

        $profiles = db_mongo_profiles();
        $profileDoc = $profiles->findOne([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);

        $profile = [
            'age' => null,
            'dob' => null,
            'contact' => null,
            'bio' => null,
        ];
        if ($profileDoc !== null) {
            $arr = json_decode(json_encode($profileDoc), true) ?: [];
            $profile['age'] = $arr['age'] ?? null;
            $profile['dob'] = $arr['dob'] ?? null;
            $profile['contact'] = $arr['contact'] ?? null;
            $profile['bio'] = $arr['bio'] ?? null;
        }

        json_response([
            'success' => true,
            'user' => [
                'id' => (int) $userRow['id'],
                'tenant_id' => $userRow['tenant_id'],
                'name' => $userRow['name'],
                'email' => $userRow['email'],
                'created_at' => $userRow['created_at'],
            ],
            'profile' => $profile,
        ]);
    } catch (Throwable $e) {
        json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_body();
    $token = null;
    if (isset($data['token']) && is_string($data['token'])) {
        $token = trim($data['token']);
    }
    if ($token === null || $token === '') {
        $h = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
        $token = is_string($h) ? trim($h) : '';
    }
    if ($token === '') {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $session = session_from_redis($token);
    if ($session === null || !isset($session['user_id'])) {
        json_response(['success' => false, 'message' => 'Invalid or expired session.'], 401);
    }

    $userId = (int) $session['user_id'];
    $tenantId = (string) ($session['tenant_id'] ?? 'default');

    $name = isset($data['name']) ? trim((string) $data['name']) : null;
    $email = isset($data['email']) ? trim(strtolower((string) $data['email'])) : null;
    $age = array_key_exists('age', $data) ? $data['age'] : null;
    $dob = isset($data['dob']) ? trim((string) $data['dob']) : null;
    $contact = isset($data['contact']) ? trim((string) $data['contact']) : null;
    $bio = isset($data['bio']) ? trim((string) $data['bio']) : null;

    try {
        $mysqli = db_mysql();

        if ($name !== null && $name !== '') {
            $stmt = $mysqli->prepare('UPDATE users SET name = ? WHERE id = ? AND tenant_id = ?');
            $stmt->bind_param('sis', $name, $userId, $tenantId);
            $stmt->execute();
            $stmt->close();
        }

        if ($email !== null && $email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'message' => 'Invalid email address.'], 422);
            }
            $stmt = $mysqli->prepare('UPDATE users SET email = ? WHERE id = ? AND tenant_id = ?');
            $stmt->bind_param('sis', $email, $userId, $tenantId);
            try {
                $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    json_response(['success' => false, 'message' => 'Email already in use for this tenant.'], 409);
                }
                throw $e;
            }
            $stmt->close();
            // Keep Redis session email in sync
            $payload = json_encode([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'email' => $email,
            ], JSON_UNESCAPED_UNICODE);
            session_store_set($token, $payload);
        }

        $profiles = db_mongo_profiles();
        $updateDoc = [];
        if ($age !== null) {
            $updateDoc['age'] = $age === '' ? null : (int) $age;
        }
        if ($dob !== null) {
            $updateDoc['dob'] = $dob === '' ? null : $dob;
        }
        if ($contact !== null) {
            $updateDoc['contact'] = $contact === '' ? null : $contact;
        }
        if ($bio !== null) {
            $updateDoc['bio'] = $bio === '' ? null : $bio;
        }

        if ($updateDoc !== []) {
            // Upsert ensures MongoDB profile exists even if registration missed inserting it.
            $profiles->updateOne(
                ['user_id' => $userId, 'tenant_id' => $tenantId],
                [
                    '$set' => $updateDoc,
                    '$setOnInsert' => [
                        'user_id' => $userId,
                        'tenant_id' => $tenantId,
                    ],
                ],
                ['upsert' => true]
            );
        }

        // Refresh merged response
        $stmt = $mysqli->prepare('SELECT id, tenant_id, name, email, created_at FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->bind_param('is', $userId, $tenantId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $profileDoc = $profiles->findOne(['user_id' => $userId, 'tenant_id' => $tenantId]);
        $profile = ['age' => null, 'dob' => null, 'contact' => null, 'bio' => null];
        if ($profileDoc !== null) {
            $arr = json_decode(json_encode($profileDoc), true) ?: [];
            $profile['age'] = $arr['age'] ?? null;
            $profile['dob'] = $arr['dob'] ?? null;
            $profile['contact'] = $arr['contact'] ?? null;
            $profile['bio'] = $arr['bio'] ?? null;
        }

        json_response([
            'success' => true,
            'message' => 'Profile updated.',
            'user' => [
                'id' => (int) $userRow['id'],
                'tenant_id' => $userRow['tenant_id'],
                'name' => $userRow['name'],
                'email' => $userRow['email'],
                'created_at' => $userRow['created_at'],
            ],
            'profile' => $profile,
        ]);
    } catch (Throwable $e) {
        json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
    }
}

json_response(['success' => false, 'message' => 'Method not allowed'], 405);

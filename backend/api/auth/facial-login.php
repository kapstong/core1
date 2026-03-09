<?php
/**
 * Facial Login API Endpoint
 * POST /backend/api/auth/facial-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        Response::error('Invalid JSON input', 400);
    }

    $username = trim((string)($input['username'] ?? ''));
    $descriptor = $input['descriptor'] ?? null;
    $blinkCount = (int)($input['blink_count'] ?? 0);
    $livenessVerifiedAt = (string)($input['liveness_verified_at'] ?? '');

    if ($username === '') {
        Response::error('Username is required', 400);
    }

    if (!is_array($descriptor) || count($descriptor) < 64) {
        Response::error('Invalid facial descriptor payload', 400);
    }

    if ($blinkCount < 1) {
        Response::error('Liveness verification failed (blink required)', 401);
    }

    $livenessTs = strtotime($livenessVerifiedAt);
    if ($livenessTs === false || (time() - $livenessTs) > 60) {
        Response::error('Liveness challenge expired. Please blink again.', 401);
    }

    $descriptor = array_map('floatval', $descriptor);

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, username, email, role, full_name, is_active, password_hash, face_descriptor, face_biometric_enabled
        FROM users
        WHERE username = :username
        LIMIT 1
    ");
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        AuditLogger::log('face_login_failed', 'user', null, "Face login failed: unknown username {$username}");
        Response::error('Invalid face login credentials', 401);
    }

    if ((int)$user['is_active'] !== 1) {
        Response::error('Account is inactive', 403);
    }

    $allowedRoles = ['admin', 'inventory_manager', 'staff'];
    if (!in_array($user['role'], $allowedRoles, true)) {
        Response::error('Face login is only available for admin and inventory staff accounts', 403);
    }

    if ((int)($user['face_biometric_enabled'] ?? 0) !== 1 || empty($user['face_descriptor'])) {
        Response::error('Facial login is not enrolled for this account', 403);
    }

    $storedDescriptor = json_decode($user['face_descriptor'], true);
    if (!is_array($storedDescriptor) || count($storedDescriptor) !== count($descriptor)) {
        AuditLogger::log('face_login_failed', 'user', (int)$user['id'], 'Face descriptor data is invalid for account');
        Response::error('Facial profile is invalid. Please re-enroll face data.', 500);
    }

    $distance = euclideanDistance($descriptor, array_map('floatval', $storedDescriptor));
    $threshold = 0.48;

    if ($distance > $threshold) {
        logFaceEvent($db, (int)$user['id'], false, $distance, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        AuditLogger::log('face_login_failed', 'user', (int)$user['id'], 'Face login failed: descriptor mismatch');
        Response::error('Face does not match enrolled profile', 401);
    }

    Auth::loginWithRemember($user, false);
    logFaceEvent($db, (int)$user['id'], true, $distance, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
    AuditLogger::log('face_login', 'user', (int)$user['id'], 'Face login successful');

    Response::success([
        'user' => array_intersect_key($user, array_flip(['id', 'username', 'email', 'role', 'full_name'])),
        'session_id' => session_id(),
        'match_distance' => $distance
    ], 'Face login successful');
} catch (Exception $e) {
    error_log('facial-login error: ' . $e->getMessage());
    Response::serverError('An error occurred during face login');
}

function euclideanDistance($a, $b) {
    $sum = 0.0;
    $length = min(count($a), count($b));
    for ($i = 0; $i < $length; $i++) {
        $delta = (float)$a[$i] - (float)$b[$i];
        $sum += $delta * $delta;
    }
    return sqrt($sum);
}

function logFaceEvent($db, $userId, $success, $distance, $ipAddress, $userAgent) {
    try {
        $stmt = $db->prepare("
            INSERT INTO face_auth_events (user_id, success, face_distance, ip_address, user_agent, created_at)
            VALUES (:user_id, :success, :face_distance, :ip_address, :user_agent, NOW())
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':success', $success ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':face_distance', $distance);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':user_agent', $userAgent);
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Failed to write face_auth_events: ' . $e->getMessage());
    }
}

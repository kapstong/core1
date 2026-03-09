<?php
/**
 * Users API - Face biometric profile management
 * GET    /backend/api/users/face-profile.php   -> enrollment status
 * POST   /backend/api/users/face-profile.php   -> enroll/update face descriptor
 * DELETE /backend/api/users/face-profile.php   -> remove face descriptor
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

Auth::requireAuth();
$currentUser = Auth::user();
$userId = (int)$currentUser['id'];

$allowedRoles = ['inventory_manager', 'staff'];
if (!in_array($currentUser['role'], $allowedRoles, true)) {
    Response::error('Face enrollment is only available for inventory staff accounts', 403);
}

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $db->prepare("
            SELECT face_biometric_enabled, face_last_enrolled_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error('User not found', 404);
        }

        Response::success([
            'face_biometric_enabled' => (int)($row['face_biometric_enabled'] ?? 0) === 1,
            'face_last_enrolled_at' => $row['face_last_enrolled_at'] ?? null
        ], 'Face profile loaded');
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Response::error('Invalid JSON input', 400);
        }

        $descriptor = $input['descriptor'] ?? null;
        $blinkCount = (int)($input['blink_count'] ?? 0);
        $livenessVerifiedAt = (string)($input['liveness_verified_at'] ?? '');

        if (!is_array($descriptor) || count($descriptor) !== 128) {
            Response::error('Invalid face descriptor. Expected 128 values.', 400);
        }

        if ($blinkCount < 1) {
            Response::error('Liveness verification failed (blink required)', 401);
        }

        $livenessTs = strtotime($livenessVerifiedAt);
        if ($livenessTs === false || (time() - $livenessTs) > 60) {
            Response::error('Liveness challenge expired. Please retry enrollment.', 401);
        }

        $normalized = [];
        foreach ($descriptor as $value) {
            $normalized[] = round((float)$value, 8);
        }

        $stmt = $db->prepare("
            UPDATE users
            SET
                face_descriptor = :face_descriptor,
                face_biometric_enabled = 1,
                face_last_enrolled_at = NOW()
            WHERE id = :id
        ");
        $stmt->bindValue(':face_descriptor', json_encode($normalized));
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        AuditLogger::log('face_enrolled', 'user', $userId, 'User enrolled face biometric profile');

        Response::success([
            'face_biometric_enabled' => true,
            'face_last_enrolled_at' => date('Y-m-d H:i:s')
        ], 'Face enrolled successfully');
    }

    if ($method === 'DELETE') {
        $stmt = $db->prepare("
            UPDATE users
            SET
                face_descriptor = NULL,
                face_biometric_enabled = 0,
                face_last_enrolled_at = NULL
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        AuditLogger::log('face_enrollment_removed', 'user', $userId, 'User removed face biometric profile');

        Response::success([
            'face_biometric_enabled' => false,
            'face_last_enrolled_at' => null
        ], 'Face enrollment removed');
    }

    Response::error('Method not allowed', 405);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'face_descriptor') !== false || strpos($e->getMessage(), 'face_biometric_enabled') !== false) {
        Response::error('Face columns are missing. Run database/face_auth_migration.sql first.', 500);
    }
    Response::error('Database error while processing face profile', 500);
} catch (Exception $e) {
    error_log('face-profile error: ' . $e->getMessage());
    Response::error('An error occurred while processing face profile', 500);
}

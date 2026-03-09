<?php
/**
 * Users API - Face biometric profile management
 * GET    /backend/api/users/face-profile.php   -> enrollment status
 * POST   /backend/api/users/face-profile.php   -> send code OR enroll/update face descriptor
 * DELETE /backend/api/users/face-profile.php   -> remove face descriptor (requires code)
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

$allowedRoles = ['admin', 'inventory_manager', 'staff'];
if (!in_array($currentUser['role'], $allowedRoles, true)) {
    Response::error('Face enrollment is only available for admin and inventory staff accounts', 403);
}

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

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
        $action = (string)($input['action'] ?? '');

        // Step 1: send 6-digit verification code to email for enroll/remove.
        if ($action === 'send_verification_code') {
            $operation = (string)($input['operation'] ?? '');
            if (!in_array($operation, ['enroll', 'delete'], true)) {
                Response::error('Invalid verification operation', 400);
            }

            $userStmt = $db->prepare("SELECT email, full_name, username FROM users WHERE id = :id LIMIT 1");
            $userStmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $userStmt->execute();
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRow || empty($userRow['email'])) {
                Response::error('No email is configured for this account', 400);
            }

            $codeType = $operation === 'enroll' ? 'face_enroll' : 'face_delete';
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $deleteStmt = $db->prepare("
                DELETE FROM verification_codes
                WHERE user_id = :user_id
                  AND code_type = :code_type
            ");
            $deleteStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $deleteStmt->bindValue(':code_type', $codeType);
            $deleteStmt->execute();

            $insertStmt = $db->prepare("
                INSERT INTO verification_codes (user_id, code, code_type, expires_at, attempts, max_attempts, created_at, is_used)
                VALUES (:user_id, :code, :code_type, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, 5, NOW(), 0)
            ");
            $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insertStmt->bindValue(':code', $code);
            $insertStmt->bindValue(':code_type', $codeType);
            $insertStmt->execute();

            require_once __DIR__ . '/../../utils/Email.php';
            $email = new Email();
            $operationLabel = $operation === 'enroll' ? 'Face Enrollment' : 'Face Enrollment Removal';
            $html = buildFaceVerificationEmailHtml($userRow, $code, $operationLabel);
            $sent = $email->send($userRow['email'], "Your {$operationLabel} Verification Code", $html, $userRow['full_name'] ?? $userRow['username'] ?? '');

            if (!$sent) {
                Response::error('Failed to send verification code email', 500);
            }

            AuditLogger::log('face_verification_code_sent', 'user', $userId, "Face verification code sent for {$operation}");
            Response::success(['expires_in_minutes' => 10], 'Verification code sent to your email');
        }

        // Step 2: enroll/update face with code.
        $descriptor = $input['descriptor'] ?? null;
        $blinkCount = (int)($input['blink_count'] ?? 0);
        $livenessVerifiedAt = (string)($input['liveness_verified_at'] ?? '');
        $verificationCode = trim((string)($input['verification_code'] ?? ''));

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

        if ($verificationCode === '' || !preg_match('/^\d{6}$/', $verificationCode)) {
            Response::error('A valid 6-digit verification code is required', 400);
        }

        validateAndConsumeFaceCode($db, $userId, 'face_enroll', $verificationCode);

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
        $verificationCode = trim((string)($input['verification_code'] ?? ''));
        if ($verificationCode === '' || !preg_match('/^\d{6}$/', $verificationCode)) {
            Response::error('A valid 6-digit verification code is required', 400);
        }

        validateAndConsumeFaceCode($db, $userId, 'face_delete', $verificationCode);

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
    $msg = $e->getMessage();
    if (strpos($msg, 'face_descriptor') !== false || strpos($msg, 'face_biometric_enabled') !== false || strpos($msg, 'face_last_enrolled_at') !== false) {
        Response::error('Face columns are missing. Run database/face_auth_migration.sql first.', 500);
    }
    if (strpos($msg, 'verification_codes') !== false) {
        Response::error('verification_codes table is missing. Please run base database migration first.', 500);
    }
    Response::error('Database error while processing face profile', 500);
} catch (Exception $e) {
    error_log('face-profile error: ' . $e->getMessage());
    Response::error($e->getMessage(), 400);
}

function validateAndConsumeFaceCode($db, $userId, $codeType, $submittedCode) {
    $stmt = $db->prepare("
        SELECT id, code, attempts, max_attempts, expires_at, is_used
        FROM verification_codes
        WHERE user_id = :user_id
          AND code_type = :code_type
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':code_type', $codeType);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('No verification code found. Please request a new code.');
    }

    if ((int)$row['is_used'] === 1) {
        throw new Exception('Verification code is already used. Request a new code.');
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        throw new Exception('Verification code expired. Request a new code.');
    }

    if ((int)$row['attempts'] >= (int)$row['max_attempts']) {
        throw new Exception('Verification code attempt limit reached. Request a new code.');
    }

    if (!hash_equals((string)$row['code'], (string)$submittedCode)) {
        $attemptStmt = $db->prepare("UPDATE verification_codes SET attempts = attempts + 1 WHERE id = :id");
        $attemptStmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $attemptStmt->execute();
        throw new Exception('Invalid verification code.');
    }

    $useStmt = $db->prepare("UPDATE verification_codes SET is_used = 1, used_at = NOW() WHERE id = :id");
    $useStmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
    $useStmt->execute();
}

function buildFaceVerificationEmailHtml($user, $code, $operationLabel) {
    $name = htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? 'User'));
    $safeOperation = htmlspecialchars($operationLabel);
    $safeCode = htmlspecialchars($code);

    return "
    <html>
    <body style='font-family: Arial, sans-serif; color: #1f2937;'>
      <div style='max-width: 560px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='margin-top: 0;'>{$safeOperation} Verification</h2>
        <p>Hello {$name},</p>
        <p>Use the 6-digit code below to continue your face security action:</p>
        <div style='font-size: 34px; font-weight: 700; letter-spacing: 6px; text-align: center; margin: 20px 0; background: #111827; color: #fff; padding: 14px; border-radius: 8px;'>
          {$safeCode}
        </div>
        <p>This code expires in <strong>10 minutes</strong>.</p>
        <p>If you did not request this, please secure your account immediately.</p>
        <p>PC Parts Central Security</p>
      </div>
    </body>
    </html>";
}

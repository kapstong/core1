<?php
/**
 * 2FA Verification API Endpoint
 * POST /backend/api/auth/verify-2fa.php - Verify 2FA codes and complete login
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Check if user is in 2FA verification mode
    if (!isset($_SESSION['requires_2fa']) || !$_SESSION['requires_2fa'] || !isset($_SESSION['user_id'])) {
        Response::error('No active 2FA verification session', 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $code = isset($input['code']) ? trim($input['code']) : '';

    if (empty($code)) {
        Response::error('Verification code is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Get the verification code record
    $query = "
        SELECT * FROM verification_codes
        WHERE user_id = :user_id
            AND code = :code
            AND code_type = '2fa'
            AND is_used = 0
            AND is_blocked = 0
            AND expires_at > NOW()
            AND attempts < max_attempts
        ORDER BY created_at DESC
        LIMIT 1
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':code', $code);
    $stmt->execute();

    $verificationRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verificationRecord) {
        // Increment attempt count for existing unexpired codes
        $updateAttemptsQuery = "
            UPDATE verification_codes
            SET attempts = attempts + 1,
                is_blocked = CASE
                    WHEN attempts >= max_attempts THEN 1
                    ELSE 0
                END
            WHERE user_id = :user_id
                AND code_type = '2fa'
                AND is_used = 0
                AND is_blocked = 0
                AND expires_at > NOW()
        ";
        $updateStmt = $db->prepare($updateAttemptsQuery);
        $updateStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $updateStmt->execute();

        // Log failed 2FA attempt
        $failureQuery = "
            INSERT INTO security_events
            (user_id, event_type, severity, ip_address, user_agent, session_id, details)
            VALUES
            (:user_id, '2fa_bypass_attempt', 'high', :ip_address, :user_agent, :session_id,
             JSON_OBJECT('attempted_code', :attempted_code, 'time', NOW()))
        ";
        $failureStmt = $db->prepare($failureQuery);
        $failureStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $failureStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
        $failureStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $failureStmt->bindValue(':session_id', session_id());
        $failureStmt->bindValue(':attempted_code', substr($code, 0, 1) . '***'); // Hide full code for security
        $failureStmt->execute();

        Response::error('Invalid or expired verification code', 401);
    }

    // Code is valid - complete authentication
    $db->beginTransaction();

    try {
        // Mark code as used
        $useCodeQuery = "
            UPDATE verification_codes
            SET is_used = 1, used_at = NOW(),
                ip_address = :ip_address,
                user_agent = :user_agent
            WHERE id = :code_id
        ";
        $useCodeStmt = $db->prepare($useCodeQuery);
        $useCodeStmt->bindValue(':code_id', $verificationRecord['id'], PDO::PARAM_INT);
        $useCodeStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
        $useCodeStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $useCodeStmt->execute();

        // Get user data
        $userQuery = "SELECT * FROM users WHERE id = :user_id AND is_active = 1";
        $userStmt = $db->prepare($userQuery);
        $userStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found or inactive');
        }

        // Login successful - update user session
        Auth::loginWithRemember($user, false);

        // Update last login
        $updateLoginQuery = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
        $updateLoginStmt = $db->prepare($updateLoginQuery);
        $updateLoginStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $updateLoginStmt->execute();

        // Log successful 2FA verification
        $successQuery = "
            INSERT INTO activity_logs
            (user_id, action, details, ip_address, user_agent)
            VALUES
            (:user_id, '2fa_verification', '2FA verification successful', :ip_address, :user_agent)
        ";
        $successStmt = $db->prepare($successQuery);
        $successStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $successStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
        $successStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $successStmt->execute();

        // Clear session variables
        unset($_SESSION['requires_2fa']);
        unset($_SESSION['user_id']);

        $db->commit();

        // Return user data (without password)
        unset($user['password_hash']);

        Response::success([
            'user' => $user,
            'session_id' => session_id(),
            'message' => '2FA verification successful - login complete'
        ], 200);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('2FA verification failed');
}
?>


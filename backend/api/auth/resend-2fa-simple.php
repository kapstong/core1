<?php
/**
 * Simple Resend 2FA Code Endpoint
 * POST /backend/api/auth/resend-2fa-simple.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Email.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Check if there's a pending 2FA verification
    if (!isset($_SESSION['2fa_user_id'])) {
        Response::error('No pending 2FA verification. Please login again.', 400);
    }

    $userId = $_SESSION['2fa_user_id'];

    // Get user data
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        Response::error('User not found', 404);
    }

    // Generate new 6-digit code
    $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Store in session (valid for 10 minutes)
    $_SESSION['2fa_code'] = $verificationCode;
    $_SESSION['2fa_expires'] = time() + 600;

    // Try to send verification email
    try {
        $email = new Email();
        $email->sendTwoFactorAuthCode($user, $verificationCode, []);

        // Log resend attempt
        AuditLogger::log(
            '2fa_resend',
            'user',
            $userId,
            '2FA code resent via email'
        );

        Response::success([
            'message' => 'A new verification code has been sent to your email'
        ], 'Code resent successfully');

    } catch (Exception $e) {
        error_log("Failed to send 2FA email: " . $e->getMessage());

        // For development - still return success but show code in response
        Response::success([
            'message' => 'New code generated',
            'debug_code' => $verificationCode // Remove in production!
        ], 'Code generated (email send failed)');
    }

} catch (Exception $e) {
    error_log("2FA resend error: " . $e->getMessage());
    Response::serverError('An error occurred while resending the code');
}


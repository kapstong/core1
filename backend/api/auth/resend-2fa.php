<?php
/**
 * Resend 2FA Code Endpoint
 * POST /backend/api/auth/resend-2fa.php
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

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Check if there's a 2FA user session
    if (!isset($_SESSION['2fa_user_id'])) {
        Response::error('2FA session expired. Please login again', 400);
    }

    $userId = $_SESSION['2fa_user_id'];

    // Get user data
    require_once __DIR__ . '/../../config/database.php';

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

    // Get client info for email
    $clientInfo = [
        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'country' => 'Philippines',
        'city' => 'Unknown'
    ];

    // Send verification email
    try {
        $email = new Email();
        $sent = $email->sendTwoFactorAuthCode($user, $verificationCode, $clientInfo);
        if (!$sent) {
            throw new Exception('Email delivery returned false');
        }

        // Log resend attempt
        AuditLogger::log(
            '2fa_resend',
            'user',
            $userId,
            '2FA code resent'
        );

        Response::success([
            'message' => 'Verification code has been resent to your email'
        ], 'Code resent successfully');

    } catch (Exception $e) {
        error_log("Failed to send 2FA email: " . $e->getMessage());

        // Still return success with code for development
        $isDev = ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

        Response::success([
            'message' => 'A new verification code has been generated' . ($isDev ? ': ' . $verificationCode : ''),
        ], 'Code generated (email may have failed)');
    }

} catch (Exception $e) {
    error_log("2FA resend error: " . $e->getMessage());
    Response::serverError('An error occurred while resending the code');
}


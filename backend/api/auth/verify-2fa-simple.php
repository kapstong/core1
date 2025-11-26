<?php
/**
 * Simple 2FA Verification Endpoint
 * POST /backend/api/auth/verify-2fa-simple.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    $code = trim($input['code'] ?? '');
    $trustDevice = isset($input['trust_device']) && $input['trust_device'] === true;

    if (empty($code)) {
        Response::error('Verification code is required', 400);
    }

    // Check if there's a pending 2FA verification in session
    if (!isset($_SESSION['2fa_code']) || !isset($_SESSION['2fa_user_id'])) {
        Response::error('No pending 2FA verification. Please login again.', 400);
    }

    // Check if code has expired (10 minutes)
    if (isset($_SESSION['2fa_expires']) && time() > $_SESSION['2fa_expires']) {
        // Clean up session
        unset($_SESSION['2fa_code']);
        unset($_SESSION['2fa_expires']);
        unset($_SESSION['2fa_user_id']);

        Response::error('Verification code has expired. Please login again.', 400);
    }

    // Verify the code
    if ($_SESSION['2fa_code'] !== $code) {
        // Log failed attempt
        AuditLogger::log(
            '2fa_failed',
            'user',
            $_SESSION['2fa_user_id'],
            '2FA verification failed - invalid code'
        );

        Response::error('Invalid verification code. Please try again.', 401);
    }

    // Code is valid! Get user and complete login
    $userId = $_SESSION['2fa_user_id'];

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        Response::error('User not found or inactive', 404);
    }

    // Complete the login
    Auth::login($user);

    // If user chose to trust this device, create a bypass record
    if ($trustDevice) {
        // Generate device fingerprint (same as login.php)
        $clientInfo = [
            'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];

        $deviceFingerprint = hash('sha256', implode('|', [
            $clientInfo['ip'],
            substr($clientInfo['user_agent'], 0, 50),
            $_SERVER['HTTP_ACCEPT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''
        ]));

        // Calculate expiry (30 days from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Insert or update bypass record
        $bypassQuery = "
            INSERT INTO 2fa_bypass_records
            (user_id, device_fingerprint, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                expires_at = VALUES(expires_at),
                updated_at = NOW()
        ";

        $bypassStmt = $db->prepare($bypassQuery);
        $bypassStmt->execute([
            $userId,
            $deviceFingerprint,
            $clientInfo['ip'],
            $clientInfo['user_agent'],
            $expiresAt
        ]);

        // Log the device trust action
        AuditLogger::log(
            '2fa_device_trusted',
            'user',
            $userId,
            'Device trusted for 30 days - 2FA bypassed'
        );
    }

    // Clean up 2FA session data
    unset($_SESSION['2fa_code']);
    unset($_SESSION['2fa_expires']);
    unset($_SESSION['2fa_user_id']);

    // Log successful 2FA verification
    AuditLogger::log(
        '2fa_verified',
        'user',
        $userId,
        '2FA verification successful'
    );

    Response::success([
        'message' => '2FA verification successful',
        'redirect' => 'dashboard.php',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name']
        ]
    ], 'Login successful');

} catch (Exception $e) {
    error_log("2FA verification error: " . $e->getMessage());
    Response::serverError('An error occurred during verification');
}

<?php
/**
 * Forgot Password API Endpoint
 * POST /backend/api/auth/forgot-password.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../models/User.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate email
    if (empty($input['email'])) {
        Response::error('Email is required', 400);
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Invalid email format', 400);
    }

    $email = strtolower(trim($input['email']));
    $userType = $input['type'] ?? 'user'; // 'supplier' or default to staff/admin flow
    $isStaffReset = $userType !== 'supplier';
    $genericSuccessMessage = $isStaffReset
        ? 'If an account with that email exists, a 6-digit password reset code has been sent.'
        : 'If an account with that email exists, a password reset link has been sent.';

    // Initialize database and user model
    $db = Database::getInstance()->getConnection();
    $userModel = new User();
    $user = $userModel->findByEmail($email);

    // Handle different user types
    if ($userType === 'supplier') {
        // Check if user exists and is a supplier
        if (!$user || $user['role'] !== 'supplier') {
            // Always return success for security (don't reveal if email exists)
            Response::success([
                'message' => $genericSuccessMessage
            ]);
        }
    } else {
        // Check if user exists and is staff/admin (not customer)
        if (!$user || !in_array($user['role'], ['admin', 'staff', 'inventory_manager', 'purchasing_officer'])) {
            // Always return success for security (don't reveal if email exists)
            Response::success([
                'message' => $genericSuccessMessage
            ]);
        }
    }

    // Generate reset token/code
    $resetToken = $isStaffReset
        ? generateUniqueSixDigitResetCode($db)
        : bin2hex(random_bytes(32));

    try {
        // Delete any existing reset tokens for this user
        $stmt = $db->prepare("DELETE FROM verification_codes WHERE user_id = ? AND code_type = 'password_reset'");
        $stmt->execute([$user['id']]);

        // Use shorter expiry for OTP-style resets.
        $expiryInterval = $isStaffReset ? '15 MINUTE' : '1 HOUR';
        $stmt = $db->prepare("INSERT INTO verification_codes (user_id, code, code_type, expires_at) VALUES (?, ?, 'password_reset', DATE_ADD(NOW(), INTERVAL {$expiryInterval}))");
        $stmt->execute([$user['id'], $resetToken]);
    } catch (PDOException $e) {
        // If verification_codes table doesn't exist, fall back to storing token as a temporary password reset
        // For now, just log the error and continue (table might not be created yet)
        error_log('Verification codes table not available: ' . $e->getMessage() . ' - password reset functionality disabled');
        // Return success to avoid revealing system state
        Response::success([
            'message' => $genericSuccessMessage
        ]);
    }

    // Send reset email - ALWAYS show debug info for troubleshooting
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $emailService = new Email();

        // Debug: Get email settings
        $emailSettings = $emailService->getSettings();

        // Use configured APP_URL instead of building dynamically
        $appUrl = Env::get('APP_URL', 'https://core1.merchandising-c23.com');

        // Build reset URL based on user type
        $resetUrl = '';

        if ($userType === 'supplier') {
            // Supplier reset URL - use root level URL
            $resetUrl = $appUrl . "/reset-password.php?token={$resetToken}&type=supplier";
        } else {
            // Staff reset URL - use root level URL
            $resetUrl = $appUrl . "/reset-password.php?token={$resetToken}&type=staff";
        }

        // Always include reset URL and debug info
        $response = [
            'message' => 'Password reset process completed with debug information.',
            'reset_url' => $resetUrl,
            'debug_info' => [
                'user_type' => $userType,
                'otp_mode' => $isStaffReset,
                'user_email' => $user['email'],
                'token_generated' => true,
                'token_preview' => $isStaffReset ? $resetToken : substr($resetToken, 0, 8) . '...masked...',
                'email_settings' => [
                    'smtp_host' => $emailSettings['smtp_host'] ?: 'NOT CONFIGURED',
                    'smtp_port' => $emailSettings['smtp_port'] ?: 'NOT CONFIGURED',
                    'smtp_username' => substr($emailSettings['smtp_username'] ?: 'NOT CONFIGURED', 0, 3) . '***masked***',
                    'from_email' => $emailSettings['from_email'] ?: 'NOT CONFIGURED',
                    'site_url' => $emailSettings['site_url'] ?: 'NOT CONFIGURED'
                ]
            ]
        ];

        // Try to send email
        $userData = ($userType === 'supplier') ? [
            'email' => $user['email'],
            'first_name' => $user['full_name'] ?? $user['username'] ?? 'Supplier',
            'last_name' => ''
        ] : [
            'email' => $user['email'],
            'first_name' => isset($user['full_name']) ? explode(' ', $user['full_name'], 2)[0] : $user['username'],
            'last_name' => isset($user['full_name']) ? explode(' ', $user['full_name'], 2)[1] ?? '' : ''
        ];

        $emailResult = $emailService->sendPasswordResetEmail($userData, $resetUrl, $isStaffReset ? $resetToken : null);
        $response['debug_info']['email_send_result'] = $emailResult ? 'SUCCESS' : 'FAILED';
        if (!$emailResult) {
            throw new RuntimeException('Email send returned false; SMTP and PHP mail fallback both failed.');
        }

        Response::success($response);

    } catch (Exception $e) {
        // Show detailed error info
        error_log('Failed to send password reset email: ' . $e->getMessage());
        $isDebug = (bool)Env::get('APP_DEBUG', false) || strtolower((string)Env::get('APP_ENV', '')) === 'development';
        $errors = null;
        if ($isDebug) {
            $errors = [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'reset_url' => $resetUrl ?? null,
                'otp_mode' => $isStaffReset
            ];
        }

        $failureMessage = $isStaffReset
            ? 'Unable to send reset code email. Please check SMTP settings and try again.'
            : 'Unable to send password reset email. Please check SMTP settings and try again.';

        Response::error($failureMessage, 500, $errors);
    }

} catch (Exception $e) {
    error_log('Forgot password error: ' . $e->getMessage());
    Response::error('An error occurred. Please try again later.', 500);
}

/**
 * Generate a unique 6-digit code for password reset.
 */
function generateUniqueSixDigitResetCode(PDO $db) {
    for ($i = 0; $i < 20; $i++) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS count
                FROM verification_codes
                WHERE code = ?
                  AND code_type = 'password_reset'
                  AND is_used = 0
                  AND expires_at > NOW()
            ");
            $stmt->execute([$code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If table/columns are missing, return a random 6-digit code.
            return $code;
        }

        if (!$result || (int)($result['count'] ?? 0) === 0) {
            return $code;
        }
    }

    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
?>


<?php
/**
 * Reset Password API Endpoint
 * POST /backend/api/auth/reset-password.php
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

    // Validate required fields
    $required = ['token', 'password'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    $token = trim($input['token']);
    $password = $input['password'];
    $userType = $input['type'] ?? 'customer'; // 'customer', 'staff', or 'supplier'

    // Validate password strength
    if (strlen($password) < 8) {
        Response::error('Password must be at least 8 characters long', 400);
    }

    // Find valid reset token - include user role in the join
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT vc.*, u.id as user_id, u.email, u.username, u.full_name, u.role
        FROM verification_codes vc
        JOIN users u ON vc.user_id = u.id
        WHERE vc.code = ? AND vc.code_type = 'password_reset'
        AND vc.is_used = 0 AND vc.expires_at > NOW()
        AND vc.attempts < vc.max_attempts
    ");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
        Response::error('Invalid or expired reset token', 400);
    }

    $userId = $resetRecord['user_id'];
    $userRole = $resetRecord['role'] ?? '';

    // Check user type matches token's actual user role
    $expectedRoles = [];
    if ($userType === 'customer') {
        $expectedRoles = ['customer'];
    } elseif ($userType === 'staff') {
        $expectedRoles = ['admin', 'staff', 'inventory_manager', 'purchasing_officer'];
    } elseif ($userType === 'supplier') {
        $expectedRoles = ['supplier'];
    }

    if (!in_array($userRole, $expectedRoles)) {
        Response::error("Invalid token type. This token is for {$userRole} accounts but you specified {$userType}.", 400);
    }

    try {
        // Start transaction
        $db->beginTransaction();

        // Update user password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);

        // Mark token as used
        $tokenStmt = $db->prepare("
            UPDATE verification_codes
            SET is_used = 1, used_at = NOW()
            WHERE code = ?
        ");
        $tokenStmt->execute([$token]);

        // Send confirmation email
        try {
            require_once __DIR__ . '/../../utils/Email.php';
            $emailService = new Email();

            $user = [
                'id' => $userId,
                'email' => $resetRecord['email'],
                'username' => $resetRecord['username'],
                'full_name' => $resetRecord['full_name'] ?? $resetRecord['username']
            ];

            $emailService->sendPasswordChangedEmail($user);
        } catch (Exception $e) {
            // Log error but continue (password was successfully changed)
            error_log('Failed to send password changed email: ' . $e->getMessage());
        }

        // Commit transaction
        $db->commit();

        Response::success(['message' => 'Password has been reset successfully']);

    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        Response::error('Failed to reset password', 500);
    }

} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    Response::error('An error occurred. Please try again later.', 500);
}
?>


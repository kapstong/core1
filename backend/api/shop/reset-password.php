<?php
/**
 * Reset Password API Endpoint
 * POST /backend/api/shop/reset-password.php - Reset customer password with token
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        resetPassword();
    } else if ($method === 'GET') {
        // Validate token (for frontend to check if token is valid before showing form)
        validateToken();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Password reset failed: ' . $e->getMessage());
}

function validateToken() {
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        Response::error('Reset token is required', 400);
    }

    $token = trim($_GET['token']);
    $db = Database::getInstance()->getConnection();

    $query = "SELECT id, email, first_name,
                     password_reset_expires,
                     (password_reset_expires > NOW()) as token_valid
              FROM customers
              WHERE password_reset_token = :token";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        Response::error('Invalid reset token', 400);
    }

    if (!$customer['token_valid']) {
        Response::error('Reset token has expired. Please request a new one.', 400);
    }

    Response::success([
        'valid' => true,
        'email' => $customer['email'],
        'expires' => $customer['password_reset_expires']
    ]);
}

function resetPassword() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (!isset($input['token']) || empty($input['token'])) {
        Response::error('Reset token is required', 400);
    }

    if (!isset($input['password']) || empty($input['password'])) {
        Response::error('New password is required', 400);
    }

    if (!isset($input['password_confirm']) || empty($input['password_confirm'])) {
        Response::error('Password confirmation is required', 400);
    }

    // Check passwords match
    if ($input['password'] !== $input['password_confirm']) {
        Response::error('Passwords do not match', 400);
    }

    // Validate password strength
    if (strlen($input['password']) < 8) {
        Response::error('Password must be at least 8 characters long', 400);
    }

    $token = trim($input['token']);
    $db = Database::getInstance()->getConnection();

    // Find customer with valid reset token
    $query = "SELECT id, email, first_name
              FROM customers
              WHERE password_reset_token = :token
              AND password_reset_expires > NOW()
              AND is_active = 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        Response::error('Invalid or expired reset token', 400);
    }

    // Hash new password
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);

    // Update password and clear reset token
    $updateQuery = "UPDATE customers
                    SET password_hash = :password_hash,
                        password_reset_token = NULL,
                        password_reset_expires = NULL
                    WHERE id = :id";

    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':password_hash', $passwordHash);
    $updateStmt->bindParam(':id', $customer['id'], PDO::PARAM_INT);
    $updateStmt->execute();

    // Send confirmation email
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $email = new Email();
        $email->sendPasswordChangedEmail($customer);
    } catch (Exception $e) {
        // Log error but don't fail password reset
        error_log('Failed to send password changed email: ' . $e->getMessage());
    }

    Response::success([
        'message' => 'Password reset successfully! You can now login with your new password.',
        'email' => $customer['email']
    ]);
}

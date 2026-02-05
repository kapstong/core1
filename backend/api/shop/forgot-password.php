<?php
/**
 * Forgot Password API Endpoint
 * POST /backend/api/shop/forgot-password.php - Request password reset
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        requestPasswordReset();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Password reset request failed: ' . $e->getMessage());
}

function requestPasswordReset() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['email']) || empty($input['email'])) {
        Response::error('Email is required', 400);
    }

    $email = strtolower(trim($input['email']));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Invalid email format', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Find customer by email (removed is_active check for compatibility)
    $query = "SELECT id, email, first_name, last_name FROM customers WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always return success even if email not found (security best practice)
    // This prevents email enumeration attacks
    if (!$customer) {
        Response::success([
            'message' => 'If the email exists, a password reset link has been sent.',
            'email' => $email
        ]);
        return;
    }

    // Generate password reset token
    $resetToken = bin2hex(random_bytes(32));
    $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

    // Update customer with reset token
    $updateQuery = "UPDATE customers
                    SET password_reset_token = :token,
                        password_reset_expires = :expires
                    WHERE id = :id";

    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':token', $resetToken);
    $updateStmt->bindParam(':expires', $resetExpires);
    $updateStmt->bindParam(':id', $customer['id'], PDO::PARAM_INT);
    $updateStmt->execute();

    // Send password reset email
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $emailUtil = new Email();

        // Determine base path (local dev vs production)
        $basePath = '';
        if (isset($_SERVER['SERVER_NAME']) &&
            (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
             strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false)) {
            $basePath = '/core1';
        }

        $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                    "://{$_SERVER['HTTP_HOST']}{$basePath}/public/reset-password.php?token={$resetToken}";

        $emailUtil->sendPasswordResetEmail($customer, $resetUrl);
    } catch (Exception $e) {
        // Log error but don't expose to user
        error_log('Failed to send password reset email: ' . $e->getMessage());
    }

    Response::success([
        'message' => 'If the email exists, a password reset link has been sent.',
        'email' => $email
    ]);
}


<?php
/**
 * Email Verification API Endpoint
 * GET /backend/api/shop/verify-email.php?token=xxx - Verify customer email
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
    if ($method === 'GET' || $method === 'POST') {
        verifyEmail();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Email verification failed: ' . $e->getMessage());
}

function verifyEmail() {
    // Accept token from either GET or POST
    $token = null;
    if (isset($_GET['token'])) {
        $token = trim($_GET['token']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = isset($input['token']) ? trim($input['token']) : null;
    }

    if (empty($token)) {
        Response::error('Verification token is required', 400);
    }
    $db = Database::getInstance()->getConnection();

    // Find customer with this token
    $query = "SELECT id, email, first_name, email_verified
              FROM customers
              WHERE email_verification_token = :token";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        Response::error('Invalid or expired verification token', 400);
    }

    // Check if already verified
    if ($customer['email_verified']) {
        Response::success([
            'message' => 'Email already verified',
            'email' => $customer['email'],
            'already_verified' => true
        ]);
    }

    // Mark as verified and clear token
    $updateQuery = "UPDATE customers
                    SET email_verified = 1,
                        email_verification_token = NULL
                    WHERE id = :id";

    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':id', $customer['id'], PDO::PARAM_INT);
    $updateStmt->execute();

    // Send welcome email
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $email = new Email();
        $email->sendWelcomeEmail($customer);
    } catch (Exception $e) {
        // Log error but don't fail verification
        error_log('Failed to send welcome email: ' . $e->getMessage());
    }

    Response::success([
        'message' => 'Email verified successfully! Welcome to our store.',
        'email' => $customer['email'],
        'customer_id' => $customer['id'],
        'verified' => true
    ]);
}

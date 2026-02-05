<?php
/**
 * Resend Email Verification API Endpoint
 * POST /backend/api/shop/resend-verification.php - Resend verification email to customer
 */

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../models/Customer.php';

CORS::handle();

// Start session for customer authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        resendVerification();
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::serverError('Resend verification failed: ' . $e->getMessage());
}

function resendVerification() {
    // Check if customer is logged in
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];
    $db = Database::getInstance()->getConnection();

    // Get customer details
    $query = "SELECT id, email, first_name, last_name, email_verified, email_verification_token
              FROM customers
              WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        Response::error('Customer not found', 404);
    }

    // Check if already verified
    if ($customer['email_verified']) {
        Response::success([
            'message' => 'Your email is already verified',
            'already_verified' => true
        ]);
    }

    // Check if there's an existing token, if not generate new one
    $verificationToken = $customer['email_verification_token'];

    if (empty($verificationToken)) {
        // Generate new verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Update customer with new token
        $updateQuery = "UPDATE customers
                       SET email_verification_token = :token
                       WHERE id = :id";

        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':token', $verificationToken);
        $updateStmt->bindParam(':id', $customerId, PDO::PARAM_INT);
        $updateStmt->execute();
    }

    // Send verification email
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $email = new Email();

        // Build verification URL pointing to public page
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = '';

        // Extract base path (e.g., /core1) from script path
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        if (preg_match('#^(/[^/]+)/#', $scriptPath, $matches)) {
            $basePath = $matches[1];
        }

        $verificationUrl = $protocol . '://' . $host . $basePath . "/public/verify-email.php?token={$verificationToken}";
        $email->sendEmailVerification($customer, $verificationUrl);

        Response::success([
            'message' => 'Verification email has been sent successfully. Please check your inbox.',
            'email' => $customer['email']
        ]);
    } catch (Exception $e) {
        error_log('Failed to send verification email: ' . $e->getMessage());
        Response::error('Failed to send verification email. Please try again later.', 500);
    }
}


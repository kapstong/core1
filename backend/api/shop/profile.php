<?php
/**
 * Customer Profile API Endpoint
 * GET /backend/api/shop/profile.php - Get customer profile
 * PUT /backend/api/shop/profile.php - Update customer profile
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
require_once __DIR__ . '/../../models/Customer.php';

CORS::handle();

// Start session for customer authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$customerModel = new Customer();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getCustomerProfile($customerModel);
            break;

        case 'PUT':
            updateCustomerProfile($customerModel);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Profile operation failed: ' . $e->getMessage());
}

function getCustomerProfile($customerModel) {
    // Check if customer is logged in
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Not authenticated', 401);
    }

    $customer = $customerModel->findById($_SESSION['customer_id']);

    if (!$customer) {
        // Clear invalid session
        unset($_SESSION['customer_id']);
        unset($_SESSION['customer_email']);
        Response::error('Customer not found', 404);
    }

    // Calculate customer stats from orders
    $db = Database::getInstance()->getConnection();
    
    $statsQuery = "SELECT 
                       COUNT(DISTINCT id) as total_orders,
                       COALESCE(SUM(total_amount), 0) as total_spent
                   FROM customer_orders
                   WHERE customer_id = :customer_id AND status IN ('completed', 'shipped', 'delivered')";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':customer_id', $customer['id'], PDO::PARAM_INT);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Add stats to customer data
    $customer['total_orders'] = (int)($stats['total_orders'] ?? 0);
    $customer['total_spent'] = (float)($stats['total_spent'] ?? 0);

    // Return customer data (exclude sensitive fields)
    unset($customer['password_hash']);
    unset($customer['email_verification_token']);
    unset($customer['password_reset_token']);

    Response::success(['customer' => $customer]);
}

function updateCustomerProfile($customerModel) {
    // Check if customer is logged in
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Not authenticated', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    $customerId = $_SESSION['customer_id'];

    // Verify customer exists
    $customer = $customerModel->findById($customerId);
    if (!$customer) {
        unset($_SESSION['customer_id']);
        unset($_SESSION['customer_email']);
        Response::error('Customer not found', 404);
    }

    // Prepare update data
    $updateData = [];

    // Allowed fields for customer to update
    $allowedFields = [
        'first_name', 'last_name', 'phone', 'date_of_birth', 'gender'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = trim($input[$field]);
        }
    }

    // Special handling for email change
    if (isset($input['email']) && !empty($input['email'])) {
        $newEmail = strtolower(trim($input['email']));

        // Validate email format
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format', 400);
        }

        // Check if email is already taken by another customer
        $existing = $customerModel->findByEmail($newEmail);
        if ($existing && $existing['id'] != $customerId) {
            Response::error('Email already registered', 409);
        }

        $updateData['email'] = $newEmail;
        $updateData['email_verified'] = 0; // Require re-verification for email changes
    }

    // Handle password change
    if (isset($input['current_password']) && isset($input['new_password'])) {
        // Verify current password
        if (!password_verify($input['current_password'], $customer['password_hash'])) {
            Response::error('Current password is incorrect', 400);
        }

        // Validate new password
        if (strlen($input['new_password']) < 8) {
            Response::error('New password must be at least 8 characters long', 400);
        }

        $updateData['password_hash'] = password_hash($input['new_password'], PASSWORD_DEFAULT);
    }

    if (empty($updateData)) {
        Response::error('No fields to update', 400);
    }

    // Update customer
    $success = $customerModel->update($customerId, $updateData);

    if (!$success) {
        Response::error('Failed to update profile', 500);
    }

    // Get updated customer data
    $updatedCustomer = $customerModel->findById($customerId);

    // Update session email if it changed
    if (isset($updateData['email'])) {
        $_SESSION['customer_email'] = $updateData['email'];
    }

    // Return updated customer data (exclude sensitive fields)
    unset($updatedCustomer['password_hash']);
    unset($updatedCustomer['email_verification_token']);
    unset($updatedCustomer['password_reset_token']);

    Response::success([
        'message' => 'Profile updated successfully',
        'customer' => $updatedCustomer
    ]);
}

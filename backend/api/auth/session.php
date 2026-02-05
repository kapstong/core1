<?php
/**
 * Customer Session API Endpoint
 * GET /backend/api/auth/session.php - Get current customer session data
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

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::success([
            'authenticated' => false
        ], 'Customer not authenticated');
    }

    // Get customer data
    $customerModel = new Customer();
    $customer = $customerModel->findById($_SESSION['customer_id']);

    if ($customer) {
        // Remove sensitive data
        unset($customer['password_hash']);
        unset($customer['email_verification_token']);

        Response::success($customer, 'Customer session data retrieved successfully');
    } else {
        Response::error('Customer not found', 404);
    }

} catch (Exception $e) {
    error_log('Session API error: ' . $e->getMessage());
    Response::serverError('An error occurred while retrieving session data');
}


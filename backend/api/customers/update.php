<?php
/**
 * Customer Update API Endpoint
 * PUT /backend/api/customers/update.php - Update customer information
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

CORS::handle();

// Require authentication
Auth::requireAuth();

// Check permissions - admin or inventory_manager can manage customers
$user = Auth::user();
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'PUT' || $method === 'PATCH') {
        updateCustomer();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to update customer: ' . $e->getMessage());
}

function updateCustomer() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['id']) || empty($input['id'])) {
        Response::error('Customer ID is required', 400);
    }

    $customerId = (int)$input['id'];
    $db = Database::getInstance()->getConnection();

    // Verify customer exists
    $checkQuery = "SELECT id FROM customers WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $checkStmt->execute();

    if (!$checkStmt->fetch()) {
        Response::error('Customer not found', 404);
    }

    // Build update query dynamically
    $updateFields = [];
    $params = [':id' => $customerId];

    // Allowed fields to update
    $allowedFields = ['first_name', 'last_name', 'phone', 'date_of_birth', 'gender', 'is_active', 'email_verified'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }

    // Special handling for email (check uniqueness)
    if (isset($input['email']) && !empty($input['email'])) {
        $emailCheckQuery = "SELECT id FROM customers WHERE email = :email AND id != :id";
        $emailStmt = $db->prepare($emailCheckQuery);
        $emailStmt->bindParam(':email', $input['email']);
        $emailStmt->bindParam(':id', $customerId, PDO::PARAM_INT);
        $emailStmt->execute();

        if ($emailStmt->fetch()) {
            Response::error('Email already exists', 400);
        }

        $updateFields[] = "email = :email";
        $params[':email'] = $input['email'];
    }

    if (empty($updateFields)) {
        Response::error('No fields to update', 400);
    }

    // Update customer
    $query = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    // Get updated customer
    $getQuery = "SELECT id, email, first_name, last_name, phone, date_of_birth, gender,
                        is_active, email_verified, created_at, updated_at
                 FROM customers
                 WHERE id = :id";

    $getStmt = $db->prepare($getQuery);
    $getStmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $getStmt->execute();

    $customer = $getStmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'message' => 'Customer updated successfully',
        'customer' => $customer
    ]);
}


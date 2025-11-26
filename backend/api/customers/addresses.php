<?php
/**
 * Customer Addresses Management API Endpoint
 * GET    /backend/api/customers/addresses.php?customer_id=123 - List customer addresses
 * POST   /backend/api/customers/addresses.php - Create new address
 * PUT    /backend/api/customers/addresses.php - Update address
 * DELETE /backend/api/customers/addresses.php?id=456 - Delete address
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
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
    switch ($method) {
        case 'GET':
            listAddresses();
            break;

        case 'POST':
            createAddress();
            break;

        case 'PUT':
        case 'PATCH':
            updateAddress();
            break;

        case 'DELETE':
            deleteAddress();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Address operation failed: ' . $e->getMessage());
}

function listAddresses() {
    if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
        Response::error('Customer ID is required', 400);
    }

    $customerId = (int)$_GET['customer_id'];
    $db = Database::getInstance()->getConnection();

    $query = "SELECT * FROM customer_addresses
              WHERE customer_id = :customer_id
              ORDER BY is_default DESC, id DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success([
        'addresses' => $addresses,
        'total' => count($addresses)
    ]);
}

function createAddress() {
    // Permission already checked at top level

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['customer_id', 'address_type', 'first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            Response::error("$field is required", 400);
        }
    }

    $db = Database::getInstance()->getConnection();

    // If marking as default, unset other default addresses
    if (isset($input['is_default']) && $input['is_default']) {
        $unsetQuery = "UPDATE customer_addresses SET is_default = 0
                       WHERE customer_id = :customer_id AND address_type = :address_type";
        $unsetStmt = $db->prepare($unsetQuery);
        $unsetStmt->bindParam(':customer_id', $input['customer_id'], PDO::PARAM_INT);
        $unsetStmt->bindParam(':address_type', $input['address_type']);
        $unsetStmt->execute();
    }

    // Insert new address
    $query = "INSERT INTO customer_addresses
              (customer_id, address_type, is_default, first_name, last_name, company,
               address_line_1, address_line_2, city, state, postal_code, country, phone)
              VALUES
              (:customer_id, :address_type, :is_default, :first_name, :last_name, :company,
               :address_line_1, :address_line_2, :city, :state, :postal_code, :country, :phone)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $input['customer_id'], PDO::PARAM_INT);
    $stmt->bindParam(':address_type', $input['address_type']);
    $stmt->bindParam(':is_default', $isDefault = (isset($input['is_default']) ? (int)$input['is_default'] : 0), PDO::PARAM_INT);
    $stmt->bindParam(':first_name', $input['first_name']);
    $stmt->bindParam(':last_name', $input['last_name']);
    $stmt->bindParam(':company', $company = $input['company'] ?? null);
    $stmt->bindParam(':address_line_1', $input['address_line_1']);
    $stmt->bindParam(':address_line_2', $addressLine2 = $input['address_line_2'] ?? null);
    $stmt->bindParam(':city', $input['city']);
    $stmt->bindParam(':state', $state = $input['state'] ?? null);
    $stmt->bindParam(':postal_code', $input['postal_code']);
    $stmt->bindParam(':country', $input['country']);
    $stmt->bindParam(':phone', $phone = $input['phone'] ?? null);

    $stmt->execute();

    $addressId = $db->lastInsertId();

    // Get created address
    $getQuery = "SELECT * FROM customer_addresses WHERE id = :id";
    $getStmt = $db->prepare($getQuery);
    $getStmt->bindParam(':id', $addressId, PDO::PARAM_INT);
    $getStmt->execute();

    $address = $getStmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'message' => 'Address created successfully',
        'address' => $address
    ], 201);
}

function updateAddress() {
    // Permission already checked at top level

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['id']) || empty($input['id'])) {
        Response::error('Address ID is required', 400);
    }

    $addressId = (int)$input['id'];
    $db = Database::getInstance()->getConnection();

    // Verify address exists
    $checkQuery = "SELECT customer_id, address_type FROM customer_addresses WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $addressId, PDO::PARAM_INT);
    $checkStmt->execute();

    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        Response::error('Address not found', 404);
    }

    // If marking as default, unset other default addresses
    if (isset($input['is_default']) && $input['is_default']) {
        $unsetQuery = "UPDATE customer_addresses SET is_default = 0
                       WHERE customer_id = :customer_id AND address_type = :address_type AND id != :id";
        $unsetStmt = $db->prepare($unsetQuery);
        $unsetStmt->bindParam(':customer_id', $existing['customer_id'], PDO::PARAM_INT);
        $unsetStmt->bindParam(':address_type', $existing['address_type']);
        $unsetStmt->bindParam(':id', $addressId, PDO::PARAM_INT);
        $unsetStmt->execute();
    }

    // Build update query
    $updateFields = [];
    $params = [':id' => $addressId];

    $allowedFields = ['address_type', 'is_default', 'first_name', 'last_name', 'company',
                      'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'phone'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        Response::error('No fields to update', 400);
    }

    $query = "UPDATE customer_addresses SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    // Get updated address
    $getQuery = "SELECT * FROM customer_addresses WHERE id = :id";
    $getStmt = $db->prepare($getQuery);
    $getStmt->bindParam(':id', $addressId, PDO::PARAM_INT);
    $getStmt->execute();

    $address = $getStmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'message' => 'Address updated successfully',
        'address' => $address
    ]);
}

function deleteAddress() {
    // Permission already checked at top level

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        Response::error('Address ID is required', 400);
    }

    $addressId = (int)$_GET['id'];
    $db = Database::getInstance()->getConnection();

    // Check if address is used in any orders
    $orderCheckQuery = "SELECT COUNT(*) as order_count
                        FROM customer_orders
                        WHERE shipping_address_id = :id OR billing_address_id = :id";
    $orderStmt = $db->prepare($orderCheckQuery);
    $orderStmt->bindParam(':id', $addressId, PDO::PARAM_INT);
    $orderStmt->execute();

    $orderCheck = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if ($orderCheck['order_count'] > 0) {
        Response::error('Cannot delete address that is linked to existing orders', 400);
    }

    // Delete address
    $query = "DELETE FROM customer_addresses WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $addressId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        Response::error('Address not found', 404);
    }

    Response::success([
        'message' => 'Address deleted successfully',
        'address_id' => $addressId
    ]);
}

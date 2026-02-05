<?php
/**
 * Shop Customer Addresses API Endpoint
 * GET /backend/api/shop/addresses.php - List customer's saved addresses
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

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get customer ID
$customerId = $_SESSION['customer_id'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (!$customerId) {
            Response::error('Customer authentication required', 401);
        }

        getCustomerAddresses($customerId);
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to retrieve addresses: ' . $e->getMessage());
}

function getCustomerAddresses($customerId) {
    $db = Database::getInstance()->getConnection();

    $query = "SELECT * FROM customer_addresses
              WHERE customer_id = :customer_id
              ORDER BY is_default DESC, address_type ASC, created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group addresses by type
    $groupedAddresses = [
        'shipping' => [],
        'billing' => []
    ];

    foreach ($addresses as $address) {
        $groupedAddresses[$address['address_type']][] = $address;
    }

    Response::success([
        'addresses' => $addresses,
        'grouped' => $groupedAddresses,
        'total' => count($addresses)
    ]);
}


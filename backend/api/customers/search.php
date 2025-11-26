<?php
/**
 * Customer Search API Endpoint
 * GET /backend/api/customers/search.php?q=search_term - Search customers
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';

CORS::handle();

// Check authentication first
Auth::requireAuth();

// Get user data
$user = Auth::user();

// Check if user has permission to search customers (admin or inventory_manager)
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

try {
    $conn = Database::getInstance()->getConnection();

    // Get search query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Prepare base query - Check if customers table uses 'name' or 'first_name/last_name'
    // Try to detect the schema first
    $schemaCheckStmt = $conn->query("SHOW COLUMNS FROM customers LIKE 'name'");
    $hasNameColumn = $schemaCheckStmt->fetch(PDO::FETCH_ASSOC);

    if ($hasNameColumn) {
        // Schema uses single 'name' column
        $sql = "SELECT id, name, email, phone, address FROM customers WHERE 1=1";
        $params = [];

        // Add search condition if query is provided
        if (!empty($query)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= " ORDER BY name ASC LIMIT 50";
    } else {
        // Schema uses 'first_name' and 'last_name' columns
        $sql = "SELECT id, first_name, last_name, email, phone FROM customers WHERE 1=1";
        $params = [];

        // Add search condition if query is provided
        if (!empty($query)) {
            $sql .= " AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= " ORDER BY first_name, last_name ASC LIMIT 50";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['customers' => $customers], 'Customers retrieved successfully');
} catch (Exception $e) {
    Response::error('Failed to search customers: ' . $e->getMessage());
}

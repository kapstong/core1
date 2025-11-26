<?php
/**
 * Sales Show API Endpoint
 * GET /backend/api/sales/show.php?id={id} - Get single sale with items
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../models/Sale.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Validate sale ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    Response::error('Sale ID is required and must be numeric', 400);
}

$saleId = (int)$_GET['id'];

try {
    // Query from customer_orders (shop orders) instead of sales table
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $query = "SELECT co.*,
              CONCAT(c.first_name, ' ', c.last_name) as customer_name,
              c.email as customer_email,
              c.phone as customer_phone
              FROM customer_orders co
              LEFT JOIN customers c ON co.customer_id = c.id
              WHERE co.id = :id";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $stmt->execute();

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        Response::error('Order not found', 404);
    }

    // Get order items
    $itemsQuery = "SELECT coi.*, p.name as product_name, p.sku
                   FROM customer_order_items coi
                   LEFT JOIN products p ON coi.product_id = p.id
                   WHERE coi.order_id = :order_id";

    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bindParam(':order_id', $saleId, PDO::PARAM_INT);
    $itemsStmt->execute();

    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $formattedSale = [
        'id' => $sale['id'],
        'invoice_number' => $sale['order_number'],
        'cashier' => [
            'id' => 0,
            'name' => 'Online Shop'
        ],
        'customer' => [
            'name' => $sale['customer_name'] ?? 'Guest',
            'email' => $sale['customer_email'] ?? '',
            'phone' => $sale['customer_phone'] ?? ''
        ],
        'financials' => [
            'subtotal' => (float)($sale['subtotal'] ?? 0),
            'tax_amount' => (float)($sale['tax_amount'] ?? 0),
            'tax_rate' => 0,
            'discount_amount' => (float)($sale['discount_amount'] ?? 0),
            'total_amount' => (float)($sale['total_amount'] ?? 0)
        ],
        'payment' => [
            'method' => $sale['payment_method'] ?? 'unknown',
            'status' => $sale['payment_status'] ?? 'unknown'
        ],
        'sale_date' => $sale['order_date'] ?? $sale['created_at'],
        'notes' => $sale['status'] ?? '',
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'product' => [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'] ?? $item['product_name'],
                    'sku' => $item['product_sku'] ?? ''
                ],
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['unit_price'],
                'total_price' => (float)$item['total_price']
            ];
        }, $items),
        'items_count' => count($items)
    ];

    Response::success($formattedSale);

} catch (Exception $e) {
    Response::serverError('Failed to fetch sale: ' . $e->getMessage());
}

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
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $sale = null;
    $items = [];
    $saleType = null;

    // First try to get from sales table (POS)
    $posQuery = "SELECT s.*, u.full_name as cashier_name
                 FROM sales s
                 LEFT JOIN users u ON s.cashier_id = u.id
                 WHERE s.id = :id";

    $stmt = $conn->prepare($posQuery);
    $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $stmt->execute();
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sale) {
        $saleType = 'pos';
        // Get POS sale items
        $itemsQuery = "SELECT si.*, p.name as product_name, p.sku
                       FROM sale_items si
                       LEFT JOIN products p ON si.product_id = p.id
                       WHERE si.sale_id = :sale_id";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Try customer_orders table (online)
        $onlineQuery = "SELECT co.*,
                        CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
                        c.email as customer_email,
                        c.phone as customer_phone
                        FROM customer_orders co
                        LEFT JOIN customers c ON co.customer_id = c.id
                        WHERE co.id = :id";

        $stmt = $conn->prepare($onlineQuery);
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            Response::error('Sale not found', 404);
        }

        $saleType = 'online';
        // Get online order items
        $itemsQuery = "SELECT coi.*, p.name as product_name, p.sku
                       FROM customer_order_items coi
                       LEFT JOIN products p ON coi.product_id = p.id
                       WHERE coi.order_id = :order_id";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bindParam(':order_id', $saleId, PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$sale) {
        Response::error('Sale not found', 404);
    }

    // Format the response based on sale type
    $formattedSale = [
        'id' => $sale['id'],
        'sale_type' => $saleType,
        'invoice_number' => $saleType === 'pos' ? $sale['invoice_number'] : $sale['order_number'],
        'sale_number' => $saleType === 'pos' ? $sale['invoice_number'] : $sale['order_number'],
        'cashier' => [
            'id' => $sale['cashier_id'] ?? 0,
            'name' => $sale['cashier_name'] ?? ($saleType === 'online' ? 'Online Shop' : 'Unknown')
        ],
        'customer' => [
            'name' => $sale['customer_name'] ?? 'Walk-in Customer',
            'email' => $sale['customer_email'] ?? '',
            'phone' => $sale['customer_phone'] ?? ''
        ],
        'financials' => [
            'subtotal' => (float)($sale['subtotal'] ?? 0),
            'tax_amount' => (float)($sale['tax_amount'] ?? 0),
            'tax_rate' => (float)($sale['tax_rate'] ?? 0.12),
            'discount_amount' => (float)($sale['discount_amount'] ?? 0),
            'total_amount' => (float)($sale['total_amount'] ?? 0)
        ],
        'payment' => [
            'method' => $sale['payment_method'] ?? 'unknown',
            'status' => $sale['payment_status'] ?? 'unknown'
        ],
        'sale_date' => $saleType === 'pos' ? $sale['sale_date'] : ($sale['order_date'] ?? $sale['created_at']),
        'notes' => $sale['notes'] ?? $sale['status'] ?? '',
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'product' => [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'] ?? 'Unknown Product',
                    'sku' => $item['sku'] ?? ''
                ],
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['unit_price'],
                'total_price' => (float)($item['unit_price'] * $item['quantity'])
            ];
        }, $items),
        'items_count' => count($items)
    ];

    Response::success($formattedSale);

} catch (Exception $e) {
    Response::serverError('Failed to fetch sale: ' . $e->getMessage());
}

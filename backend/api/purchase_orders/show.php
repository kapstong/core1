<?php
/**
 * Get Purchase Order Details API Endpoint
 * GET /backend/api/purchase_orders/show.php?id={id}
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Check authentication first
    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    // Get user data
    $user = Auth::user();

    // Role-based access
    if ($user['role'] === 'staff') {
        Response::error('Access denied', 403);
    }

    if (!isset($_GET['id'])) {
        Response::error('Purchase order ID is required', 400);
    }

    $poId = intval($_GET['id']);

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get purchase order header
    $poQuery = "
        SELECT
            po.*,
            po.supplier_id,
            COALESCE(s.full_name, 'Unknown Supplier') as supplier_name,
            COALESCE(CONCAT('SUP-', LPAD(s.id, 5, '0')), 'N/A') as supplier_code,
            s.email,
            u.full_name as created_by_name,
            ua.full_name as approved_by_name
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        WHERE po.id = :po_id AND po.deleted_at IS NULL
    ";

    $stmt = $conn->prepare($poQuery);
    $stmt->execute([':po_id' => $poId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        Response::error('Purchase order not found', 404);
    }

    // Get purchase order items
    $itemsQuery = "
        SELECT
            poi.*,
            p.name as product_name,
            p.sku,
            c.name as category_name
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE poi.po_id = :po_id
        ORDER BY poi.id
    ";

    $stmt = $conn->prepare($itemsQuery);
    $stmt->execute([':po_id' => $poId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $formattedPO = [
        'id' => $po['id'],
        'po_number' => $po['po_number'],
        'supplier' => [
            'id' => $po['supplier_id'],
            'name' => $po['supplier_name'],
            'code' => $po['supplier_code'],
            'email' => $po['email'] ?? null
        ],
        'status' => $po['status'],
        'order_date' => $po['order_date'],
        'expected_delivery' => $po['expected_delivery_date'] ?? null,
        'total_amount' => floatval($po['total_amount']),
        'notes' => $po['notes'],
        'created_by' => [
            'id' => $po['created_by'],
            'name' => $po['created_by_name']
        ],
        'approved_by' => $po['approved_by'] ? [
            'id' => $po['approved_by'],
            'name' => $po['approved_by_name']
        ] : null,
        'approved_at' => $po['supplier_approved_at'] ?? null,
        'created_at' => $po['created_at'],
        'updated_at' => $po['updated_at'],
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'product' => [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'sku' => $item['sku'],
                    'category' => $item['category_name']
                ],
                'quantity_ordered' => intval($item['quantity_ordered']),
                'quantity_received' => intval($item['quantity_received']),
                'unit_cost' => floatval($item['unit_cost']),
                'total_cost' => floatval($item['total_cost']),
                'notes' => $item['notes']
            ];
        }, $items)
    ];

    Response::success([
        'purchase_order' => $formattedPO
    ], 'Purchase order details retrieved successfully');

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching purchase order details');
}


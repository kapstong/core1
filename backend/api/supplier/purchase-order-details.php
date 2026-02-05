<?php
/**
 * Supplier - Get Purchase Order Details API Endpoint
 * GET /backend/api/supplier/purchase-order-details.php?id={po_id}
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Only suppliers can access this endpoint
if ($user['role'] !== 'supplier') {
    Response::error('Access denied. This endpoint is only for suppliers.', 403);
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Response::error('Purchase order ID is required', 400);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $poId = intval($_GET['id']);

    // Get purchase order details - verify it belongs to this supplier
    $query = "
        SELECT
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery_date,
            po.status,
            po.total_amount,
            po.notes,
            po.created_at,
            po.updated_at,
            u.full_name as created_by_name,
            u.email as created_by_email
        FROM purchase_orders po
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = :po_id AND po.supplier_id = :supplier_id AND po.deleted_at IS NULL
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':po_id' => $poId,
        ':supplier_id' => $user['id']
    ]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        Response::error('Purchase order not found or access denied', 404);
    }

    // Get purchase order items with GRN acceptance/rejection info
    $itemsQuery = "
        SELECT
            poi.id,
            poi.product_id,
            poi.quantity_ordered,
            poi.quantity_received,
            poi.unit_cost,
            poi.notes,
            p.name as product_name,
            p.sku as product_sku,
            p.image_url as product_image,
            COALESCE(SUM(grni.quantity_accepted), 0) as total_accepted,
            COALESCE(SUM(grni.quantity_rejected), 0) as total_rejected
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.id
        LEFT JOIN grn_items grni ON poi.id = grni.po_item_id
        WHERE poi.po_id = :po_id
        GROUP BY poi.id
        ORDER BY poi.id ASC
    ";

    $stmt = $conn->prepare($itemsQuery);
    $stmt->execute([':po_id' => $poId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add items to the PO object
    $po['items'] = $items;

    Response::success([
        'purchase_order' => $po
    ], 'Purchase order details retrieved successfully');

} catch (Exception $e) {
    Response::serverError('Failed to retrieve purchase order details: ' . $e->getMessage());
}

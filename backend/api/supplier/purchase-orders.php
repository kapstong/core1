<?php
/**
 * Supplier - List Purchase Orders API Endpoint
 * GET /backend/api/supplier/purchase-orders.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
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

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get all purchase orders for this supplier
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
            u.email as created_by_email,
            COUNT(poi.id) as item_count
        FROM purchase_orders po
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
        WHERE po.supplier_id = :supplier_id AND po.deleted_at IS NULL
        GROUP BY po.id
        ORDER BY po.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([':supplier_id' => $user['id']]);
    $purchaseOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success([
        'purchase_orders' => $purchaseOrders,
        'total' => count($purchaseOrders)
    ], 'Purchase orders retrieved successfully');

} catch (Exception $e) {
    Response::serverError('Failed to retrieve purchase orders: ' . $e->getMessage());
}


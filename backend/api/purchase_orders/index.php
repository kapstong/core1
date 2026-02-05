<?php
/**
 * Purchase Orders List API Endpoint
 * GET /backend/api/purchase_orders/index.php
 */

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
    if (!$user || $user['role'] === 'staff') {
        Response::error('Access denied', 403);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Build query with filters
    $where = [];
    $params = [];

    $where[] = "po.deleted_at IS NULL";

    if (isset($_GET['status'])) {
        $where[] = "po.status = :status";
        $params[':status'] = $_GET['status'];
    }

    if (isset($_GET['supplier_id'])) {
        $where[] = "po.supplier_id = :supplier_id";
        $params[':supplier_id'] = intval($_GET['supplier_id']);
    }

    // If user is a supplier, restrict to their user record only
    if ($user['role'] === 'supplier') {
        $where[] = "po.supplier_id = :supplier_user_id";
        $params[':supplier_user_id'] = intval($user['id']);
    }

    if (isset($_GET['created_by'])) {
        $where[] = "po.created_by = :created_by";
        $params[':created_by'] = intval($_GET['created_by']);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get purchase orders with supplier and creator info
    $query = "
        SELECT
            po.*,
            po.supplier_id,
            COALESCE(s.full_name, 'Unknown Supplier') as supplier_name,
            COALESCE(CONCAT('SUP-', LPAD(s.id, 5, '0')), 'N/A') as supplier_code,
            u.full_name as created_by_name,
            ua.full_name as approved_by_name,
            COALESCE(COUNT(poi.id), 0) as item_count,
            COALESCE(SUM(poi.quantity_ordered), 0) as total_quantity,
            COALESCE(SUM(poi.total_cost), 0) as calculated_total
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
        {$whereClause}
        GROUP BY po.id
        ORDER BY po.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $purchaseOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response with flat fields for frontend compatibility
    $formattedOrders = array_map(function($po) {
        return [
            'id' => $po['id'],
            'po_number' => $po['po_number'],
            'supplier_id' => $po['supplier_id'],
            'supplier_name' => $po['supplier_name'],
            'supplier_code' => $po['supplier_code'],
            'status' => $po['status'],
            'order_date' => $po['order_date'],
            'expected_delivery_date' => $po['expected_delivery_date'] ?? null,
            'total_amount' => floatval($po['total_amount']),
            'notes' => $po['notes'],
            'created_by' => $po['created_by'],
            'created_by_name' => $po['created_by_name'],
            'approved_by' => $po['approved_by'],
            'approved_by_name' => $po['approved_by_name'],
            'supplier_approved_at' => $po['supplier_approved_at'] ?? null,
            'item_count' => intval($po['item_count']),
            'total_quantity' => intval($po['total_quantity']),
            'created_at' => $po['created_at'],
            'updated_at' => $po['updated_at']
        ];
    }, $purchaseOrders);

    Response::success([
        'purchase_orders' => $formattedOrders,
        'count' => count($formattedOrders)
    ], 'Purchase orders retrieved successfully');

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching purchase orders');
}

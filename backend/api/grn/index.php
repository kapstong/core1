<?php
/**
 * Goods Received Notes List API Endpoint
 * GET /backend/api/grn/index.php
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
    // Require authentication
    Auth::requireAuth();
    $user = Auth::user();

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Build query with filters
    $where = [];
    $params = [];

    // Role-based access
    if ($user['role'] === 'staff') {
        Response::error('Access denied', 403);
    }

    if (isset($_GET['inspection_status'])) {
        $where[] = "grn.inspection_status = :inspection_status";
        $params[':inspection_status'] = $_GET['inspection_status'];
    }

    if (isset($_GET['po_id'])) {
        $where[] = "grn.po_id = :po_id";
        $params[':po_id'] = intval($_GET['po_id']);
    }

    if (isset($_GET['received_by'])) {
        $where[] = "grn.received_by = :received_by";
        $params[':received_by'] = intval($_GET['received_by']);
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get GRNs with related data
    $query = "
        SELECT
            grn.*,
            po.po_number,
            supplier.full_name as supplier_name,
            u.full_name as received_by_name,
            COALESCE(COUNT(grni.id), 0) as item_count,
            COALESCE(SUM(grni.quantity_received), 0) as total_quantity_received,
            COALESCE(SUM(grni.quantity_accepted), 0) as total_quantity_accepted
        FROM goods_received_notes grn
        LEFT JOIN purchase_orders po ON grn.po_id = po.id
        LEFT JOIN users supplier ON po.supplier_id = supplier.id
        LEFT JOIN users u ON grn.received_by = u.id
        LEFT JOIN grn_items grni ON grn.id = grni.grn_id
        {$whereClause}
        GROUP BY grn.id
        ORDER BY grn.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $grns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $formattedGRNs = array_map(function($grn) {
        return [
            'id' => $grn['id'],
            'grn_number' => $grn['grn_number'],
            'po' => [
                'id' => $grn['po_id'],
                'number' => $grn['po_number']
            ],
            'supplier_name' => $grn['supplier_name'],
            'received_date' => $grn['received_date'],
            'inspection_status' => $grn['inspection_status'],
            'notes' => $grn['notes'],
            'received_by' => [
                'id' => $grn['received_by'],
                'name' => $grn['received_by_name']
            ],
            'item_count' => intval($grn['item_count']),
            'total_quantity_received' => intval($grn['total_quantity_received']),
            'total_quantity_accepted' => intval($grn['total_quantity_accepted']),
            'created_at' => $grn['created_at']
        ];
    }, $grns);

    Response::success([
        'grns' => $formattedGRNs,
        'count' => count($formattedGRNs)
    ], 'Goods received notes retrieved successfully');

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching goods received notes');
}

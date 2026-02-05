<?php
/**
 * Get Goods Received Note Details API Endpoint
 * GET /backend/api/grn/show.php?id={id}
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
        Response::error('GRN ID is required', 400);
    }

    $grnId = intval($_GET['id']);

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $hasDeletedAt = $db->columnExists('goods_received_notes', 'deleted_at');

    // Get GRN header with related data
    $grnQuery = "
        SELECT
            grn.*,
            po.po_number,
            po.order_date as po_order_date,
            po.expected_delivery_date as po_expected_delivery,
            COALESCE(s.full_name, 'Unknown Supplier') as supplier_name,
            COALESCE(CONCAT('SUP-', LPAD(s.id, 5, '0')), 'N/A') as supplier_code,
            s.email,
            u.full_name as received_by_name
        FROM goods_received_notes grn
        LEFT JOIN purchase_orders po ON grn.po_id = po.id
        LEFT JOIN users s ON po.supplier_id = s.id
        LEFT JOIN users u ON grn.received_by = u.id
        WHERE grn.id = :grn_id" . ($hasDeletedAt ? " AND grn.deleted_at IS NULL" : "") . "
    ";

    $stmt = $conn->prepare($grnQuery);
    $stmt->execute([':grn_id' => $grnId]);
    $grn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grn) {
        Response::error('Goods received note not found', 404);
    }

    // Get GRN items with product details
    $itemsQuery = "
        SELECT
            grni.*,
            p.name as product_name,
            p.sku,
            p.cost_price,
            p.selling_price,
            c.name as category_name,
            poi.quantity_ordered as po_quantity_ordered,
            poi.unit_cost as po_unit_cost
        FROM grn_items grni
        LEFT JOIN products p ON grni.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN purchase_order_items poi ON grni.po_item_id = poi.id
        WHERE grni.grn_id = :grn_id
        ORDER BY grni.id
    ";

    $stmt = $conn->prepare($itemsQuery);
    $stmt->execute([':grn_id' => $grnId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totals = [
        'quantity_received' => 0,
        'quantity_accepted' => 0,
        'quantity_rejected' => 0,
        'total_value' => 0.0
    ];

    foreach ($items as $item) {
        $totals['quantity_received'] += intval($item['quantity_received']);
        $totals['quantity_accepted'] += intval($item['quantity_accepted']);
        $totals['quantity_rejected'] += intval($item['quantity_rejected']);
        $totals['total_value'] += floatval($item['unit_cost']) * intval($item['quantity_accepted']);
    }

    // Format the response
    $formattedGRN = [
        'id' => $grn['id'],
        'grn_number' => $grn['grn_number'],
        'po' => [
            'id' => $grn['po_id'],
            'number' => $grn['po_number'],
            'order_date' => $grn['po_order_date'],
            'expected_delivery' => $grn['po_expected_delivery']
        ],
        'supplier' => [
            'name' => $grn['supplier_name'],
            'code' => $grn['supplier_code'],
            'email' => $grn['email'] ?? null
        ],
        'received_date' => $grn['received_date'],
        'inspection_status' => $grn['inspection_status'],
        'notes' => $grn['notes'],
        'received_by' => [
            'id' => $grn['received_by'],
            'name' => $grn['received_by_name']
        ],
        'totals' => $totals,
        'created_at' => $grn['created_at'],
        'updated_at' => $grn['updated_at'],
        'items' => array_map(function($item) {
            return [
                'id' => $item['id'],
                'product' => [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'sku' => $item['sku'],
                    'cost_price' => floatval($item['cost_price']),
                    'selling_price' => floatval($item['selling_price']),
                    'category' => $item['category_name']
                ],
                'po_details' => [
                    'quantity_ordered' => intval($item['po_quantity_ordered']),
                    'unit_cost' => floatval($item['po_unit_cost'])
                ],
                'quantity_received' => intval($item['quantity_received']),
                'quantity_accepted' => intval($item['quantity_accepted']),
                'quantity_rejected' => intval($item['quantity_rejected']),
                'unit_cost' => floatval($item['unit_cost']),
                'line_total' => floatval($item['unit_cost']) * intval($item['quantity_accepted']),
                'notes' => $item['notes']
            ];
        }, $items)
    ];

    Response::success([
        'grn' => $formattedGRN
    ], 'Goods received note details retrieved successfully');

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching GRN details');
}


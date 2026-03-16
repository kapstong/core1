<?php
/**
 * Stock Adjustment API Endpoint
 * POST /backend/api/inventory/adjust.php - Create stock adjustment
 * GET /backend/api/inventory/adjust.php - List stock adjustments
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

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
$allowedRoles = ['admin', 'inventory_manager', 'purchasing_officer', 'staff'];

if (!in_array((string)($user['role'] ?? ''), $allowedRoles, true)) {
    Response::error('Access denied', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            listAdjustments();
            break;

        case 'POST':
            createAdjustment($user);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Stock adjustment failed: ' . $e->getMessage());
}

function createAdjustment($user) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['product_id', 'adjustment_type', 'quantity_adjusted', 'reason'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            Response::error("{$field} is required", 400);
        }
    }

    $productId = (int)$input['product_id'];
    $adjustmentType = $input['adjustment_type'];
    $quantityAdjusted = (int)$input['quantity_adjusted'];
    $reason = trim($input['reason']);
    $notes = isset($input['notes']) ? trim($input['notes']) : null;
    $aiResponseId = isset($input['ai_response_id']) ? trim((string)$input['ai_response_id']) : '';
    $aiResponseSource = isset($input['ai_response_source']) ? trim((string)$input['ai_response_source']) : '';
    $aiApprovedBy = isset($input['ai_approved_by']) ? trim((string)$input['ai_approved_by']) : '';

    if ($aiResponseId !== '' && strlen($aiResponseId) > 80) {
        $aiResponseId = substr($aiResponseId, 0, 80);
    }
    if ($aiResponseSource !== '' && strlen($aiResponseSource) > 40) {
        $aiResponseSource = substr($aiResponseSource, 0, 40);
    }
    if ($aiApprovedBy !== '' && strlen($aiApprovedBy) > 120) {
        $aiApprovedBy = substr($aiApprovedBy, 0, 120);
    }

    $aiMetadata = null;
    if ($aiResponseId !== '' || $aiResponseSource !== '' || $aiApprovedBy !== '') {
        $aiMetadata = [
            'response_id' => $aiResponseId,
            'response_source' => $aiResponseSource !== '' ? $aiResponseSource : 'rules',
            'approved_by' => $aiApprovedBy,
            'linked_at' => gmdate('c')
        ];
        $aiAuditNote = '[AI_REF:' . ($aiMetadata['response_id'] !== '' ? $aiMetadata['response_id'] : 'n/a')
            . '|SRC:' . $aiMetadata['response_source']
            . '|APPROVED_BY:' . ($aiMetadata['approved_by'] !== '' ? $aiMetadata['approved_by'] : 'n/a') . ']';
        $notes = trim(($notes ? ($notes . ' ') : '') . $aiAuditNote);
    }

    // Validate adjustment type
    $validTypes = ['add', 'remove', 'recount'];
    if (!in_array($adjustmentType, $validTypes)) {
        Response::error('Invalid adjustment type. Must be: add, remove, or recount', 400);
    }

    // Validate quantity
    if ($adjustmentType !== 'recount' && $quantityAdjusted === 0) {
        Response::error('Quantity adjusted cannot be zero', 400);
    }
    if ($adjustmentType === 'recount' && $quantityAdjusted < 0) {
        Response::error('New stock quantity cannot be negative', 400);
    }

    if ($adjustmentType === 'remove' && $quantityAdjusted > 0) {
        $quantityAdjusted = -$quantityAdjusted; // Ensure negative for removals
    } elseif ($adjustmentType === 'add' && $quantityAdjusted < 0) {
        $quantityAdjusted = abs($quantityAdjusted); // Ensure positive for additions
    }

    // Check if product exists
    $db = Database::getInstance()->getConnection();

    $productQuery = "SELECT p.id, p.name, p.sku, i.quantity_on_hand
                     FROM products p
                     LEFT JOIN inventory i ON p.id = i.product_id
                     WHERE p.id = :product_id AND p.is_active = 1";

    $stmt = $db->prepare($productQuery);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('Product not found or not active', 404);
    }

    $currentStock = (int)$product['quantity_on_hand'];

    // For recount type, quantity_adjusted represents the new total count
    if ($adjustmentType === 'recount') {
        $newStock = abs($quantityAdjusted);
        $quantityAdjusted = $newStock - $currentStock;
    }

    $newStock = $currentStock + $quantityAdjusted;

    // Validate that we don't go below zero for non-recount adjustments
    if ($adjustmentType !== 'recount' && $newStock < 0) {
        Response::error("Cannot remove more items than currently in stock. Current stock: {$currentStock}", 400);
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Generate adjustment number
        $adjustmentNumber = generateAdjustmentNumber($db);

        // Create stock adjustment record
        $adjustmentQuery = "INSERT INTO stock_adjustments
                           (adjustment_number, product_id, adjustment_type, quantity_before,
                            quantity_adjusted, quantity_after, reason, performed_by, notes)
                           VALUES
                           (:adjustment_number, :product_id, :adjustment_type, :quantity_before,
                            :quantity_adjusted, :quantity_after, :reason, :performed_by, :notes)";

        $stmt = $db->prepare($adjustmentQuery);
        $stmt->bindParam(':adjustment_number', $adjustmentNumber);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindParam(':adjustment_type', $adjustmentType);
        $stmt->bindParam(':quantity_before', $currentStock, PDO::PARAM_INT);
        $stmt->bindParam(':quantity_adjusted', $quantityAdjusted, PDO::PARAM_INT);
        $stmt->bindParam(':quantity_after', $newStock, PDO::PARAM_INT);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':performed_by', $user['id'], PDO::PARAM_INT);
        $stmt->bindParam(':notes', $notes);
        $stmt->execute();

        $adjustmentId = $db->lastInsertId();

        // Update inventory (create row if missing)
        $inventoryQuery = "INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                          VALUES (:product_id, :new_quantity, 0)
                          ON DUPLICATE KEY UPDATE quantity_on_hand = VALUES(quantity_on_hand)";
        $invStmt = $db->prepare($inventoryQuery);
        $invStmt->bindParam(':new_quantity', $newStock, PDO::PARAM_INT);
        $invStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $invStmt->execute();

        // Log stock movement
        switch ($adjustmentType) {
            case 'add':
            case 'remove':
            case 'recount':
                $movementType = 'adjustment';
                break;
            default:
                $movementType = 'adjustment';
                break;
        }

        $movementQuery = "INSERT INTO stock_movements
                         (product_id, movement_type, quantity, quantity_before, quantity_after,
                          reference_type, reference_id, performed_by, notes)
                         VALUES
                         (:product_id, :movement_type, :quantity, :quantity_before, :quantity_after,
                          'ADJUSTMENT', :reference_id, :performed_by, :notes)";

        $movStmt = $db->prepare($movementQuery);
        $movStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $movStmt->bindParam(':movement_type', $movementType);
        $movStmt->bindParam(':quantity', $quantityAdjusted, PDO::PARAM_INT);
        $movStmt->bindParam(':quantity_before', $currentStock, PDO::PARAM_INT);
        $movStmt->bindParam(':quantity_after', $newStock, PDO::PARAM_INT);
        $movStmt->bindParam(':reference_id', $adjustmentId, PDO::PARAM_INT);
        $movStmt->bindParam(':performed_by', $user['id'], PDO::PARAM_INT);
        $movStmt->bindParam(':notes', $reason);
        $movStmt->execute();

        // Commit transaction
        $db->commit();

        // Get complete adjustment details
        $adjustment = getAdjustmentDetails($db, $adjustmentId);

        // Log stock adjustment creation
        AuditLogger::logCreate('stock_adjustment', $adjustment['id'], "Stock adjustment {$adjustment['adjustment_number']} created for product ID {$adjustment['product_id']}", [
            'adjustment_number' => $adjustment['adjustment_number'],
            'product_id' => $adjustment['product_id'],
            'product_name' => $adjustment['product_name'],
            'product_sku' => $adjustment['product_sku'],
            'adjustment_type' => $adjustment['adjustment_type'],
            'quantity_before' => $adjustment['quantity_before'],
            'quantity_adjusted' => $adjustment['quantity_adjusted'],
            'quantity_after' => $adjustment['quantity_after'],
            'reason' => $adjustment['reason'],
            'notes' => $adjustment['notes'],
            'ai_assistance' => $aiMetadata
        ]);

        if ($aiMetadata !== null) {
            AuditLogger::log(
                'ai_assisted_action',
                'stock_adjustment',
                $adjustment['id'],
                "AI-assisted stock adjustment {$adjustment['adjustment_number']} approved and applied",
                null,
                [
                    'adjustment_number' => $adjustment['adjustment_number'],
                    'product_id' => $adjustment['product_id'],
                    'ai_response_id' => $aiMetadata['response_id'] ?? '',
                    'ai_response_source' => $aiMetadata['response_source'] ?? 'rules',
                    'ai_approved_by' => $aiMetadata['approved_by'] ?? '',
                    'linked_at' => $aiMetadata['linked_at'] ?? gmdate('c')
                ],
                $user['id']
            );
        }

        Response::success([
            'message' => 'Stock adjustment created successfully',
            'adjustment' => formatAdjustmentForResponse($adjustment)
        ], 'Stock adjustment created successfully', 201);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function listAdjustments() {
    $db = Database::getInstance()->getConnection();

    // Get query parameters
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $adjustmentType = isset($_GET['type']) ? $_GET['type'] : null;
    $reason = isset($_GET['reason']) ? trim($_GET['reason']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : (isset($_GET['date_from']) ? $_GET['date_from'] : null);
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : (isset($_GET['date_to']) ? $_GET['date_to'] : null);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = max(1, min($limit, 500));
    $offset = max(0, $offset);

    // Build query
    $query = "SELECT
                sa.id,
                sa.adjustment_number,
                sa.adjustment_type,
                sa.quantity_before,
                sa.quantity_adjusted,
                sa.quantity_after,
                sa.reason,
                sa.notes,
                sa.adjustment_date,
                p.id as product_id,
                p.name as product_name,
                p.sku as product_sku,
                u.full_name as performed_by_name,
                u.username as performed_by_username
              FROM stock_adjustments sa
              INNER JOIN products p ON sa.product_id = p.id
              LEFT JOIN users u ON sa.performed_by = u.id
              WHERE 1=1";

    $params = [];

    if ($productId) {
        $query .= " AND sa.product_id = :product_id";
        $params[':product_id'] = $productId;
    }

    if ($adjustmentType) {
        $query .= " AND sa.adjustment_type = :adjustment_type";
        $params[':adjustment_type'] = $adjustmentType;
    }

    if ($reason) {
        $query .= " AND sa.reason = :reason";
        $params[':reason'] = $reason;
    }

    if ($search) {
        $query .= " AND (
            p.name LIKE :search
            OR p.sku LIKE :search
            OR sa.adjustment_number LIKE :search
            OR COALESCE(sa.notes, '') LIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }

    if ($startDate) {
        $query .= " AND DATE(sa.adjustment_date) >= :start_date";
        $params[':start_date'] = $startDate;
    }

    if ($endDate) {
        $query .= " AND DATE(sa.adjustment_date) <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $query .= " ORDER BY sa.adjustment_date DESC LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $adjustments = array_map('formatAdjustmentForResponse', $adjustments);

    // Get total count - build a simpler dedicated count query
    $countQuery = "SELECT COUNT(*) as total
                   FROM stock_adjustments sa
                   INNER JOIN products p ON sa.product_id = p.id
                   WHERE 1=1";

    if ($productId) {
        $countQuery .= " AND sa.product_id = :product_id";
    }

    if ($adjustmentType) {
        $countQuery .= " AND sa.adjustment_type = :adjustment_type";
    }

    if ($reason) {
        $countQuery .= " AND sa.reason = :reason";
    }

    if ($search) {
        $countQuery .= " AND (
            p.name LIKE :search
            OR p.sku LIKE :search
            OR sa.adjustment_number LIKE :search
            OR COALESCE(sa.notes, '') LIKE :search
        )";
    }

    if ($startDate) {
        $countQuery .= " AND DATE(sa.adjustment_date) >= :start_date";
    }

    if ($endDate) {
        $countQuery .= " AND DATE(sa.adjustment_date) <= :end_date";
    }

    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    Response::success([
        'adjustments' => $adjustments,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ]
    ]);
}

function generateAdjustmentNumber($db) {
    $prefix = 'ADJ-' . date('Y') . '-';
    $query = "SELECT adjustment_number FROM stock_adjustments
              WHERE adjustment_number LIKE :prefix
              ORDER BY id DESC LIMIT 1";

    $stmt = $db->prepare($query);
    $searchPrefix = $prefix . '%';
    $stmt->bindParam(':prefix', $searchPrefix);
    $stmt->execute();

    $result = $stmt->fetch();

    if ($result) {
        $lastNumber = intval(str_replace($prefix, '', $result['adjustment_number']));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
}

function getAdjustmentDetails($db, $adjustmentId) {
    $query = "SELECT
                sa.*,
                p.name as product_name,
                p.sku as product_sku,
                u.full_name as performed_by_name,
                u.username as performed_by_username
              FROM stock_adjustments sa
              INNER JOIN products p ON sa.product_id = p.id
              LEFT JOIN users u ON sa.performed_by = u.id
              WHERE sa.id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $adjustmentId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function formatAdjustmentForResponse($adjustment) {
    if (!$adjustment || !is_array($adjustment)) {
        return $adjustment;
    }

    $formatted = $adjustment;
    $formatted['quantity_adjusted'] = (int)($formatted['quantity_adjusted'] ?? 0);
    $formatted['quantity_before'] = (int)($formatted['quantity_before'] ?? 0);
    $formatted['quantity_after'] = (int)($formatted['quantity_after'] ?? 0);

    // Keep compatibility with existing dashboard rendering contract.
    $formatted['product'] = [
        'name' => $formatted['product_name'] ?? 'Unknown Product',
        'sku' => $formatted['product_sku'] ?? ''
    ];

    // Existing dashboard expects remove quantity as absolute and adds "-" in UI.
    if (($formatted['adjustment_type'] ?? '') === 'remove') {
        $formatted['quantity_adjusted'] = abs($formatted['quantity_adjusted']);
    }

    return $formatted;
}

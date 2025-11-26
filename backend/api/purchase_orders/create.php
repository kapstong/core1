<?php
/**
 * Create Purchase Order API Endpoint
 * POST /backend/api/purchase_orders/create.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Role-based access
if (!in_array($user['role'], ['admin', 'purchasing_officer'])) {
    Response::error('Access denied', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Validate required fields
    $errors = Validator::required($input, ['supplier_id', 'order_date', 'items']);

    if ($errors) {
        Response::validationError($errors);
    }

    if (!is_array($input['items']) || empty($input['items'])) {
        Response::error('Items array is required and cannot be empty');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Verify supplier exists and is active (suppliers are users with role='supplier')
    $scheck = $conn->prepare("SELECT id, is_active FROM users WHERE id = :sid AND role = 'supplier' LIMIT 1");
    $scheck->execute([':sid' => $input['supplier_id']]);
    $supplierRow = $scheck->fetch(PDO::FETCH_ASSOC);
    if (!$supplierRow || !$supplierRow['is_active']) {
        Response::error('Selected supplier not found or inactive', 400);
    }

    // Start transaction
    $conn->beginTransaction();

    // Generate PO number
    $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'po_auto_number'");
    $prefix = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 'PO-2025-';

    $stmt = $conn->query("SELECT COUNT(*) + 1 as next_num FROM purchase_orders");
    $nextNum = $stmt->fetch(PDO::FETCH_ASSOC)['next_num'];
    $poNumber = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

    // Calculate totals and validate items
    $totalAmount = 0;
    $validatedItems = [];

    foreach ($input['items'] as $item) {
        if (!isset($item['product_id']) || !isset($item['quantity_ordered']) || !isset($item['unit_cost'])) {
            $conn->rollBack();
            Response::error('Each item must have product_id, quantity_ordered, and unit_cost');
        }

        // Validate numeric values
        $quantity = intval($item['quantity_ordered']);
        $unitCost = floatval($item['unit_cost']);

        if ($quantity <= 0) {
            $conn->rollBack();
            Response::error('Quantity ordered must be greater than 0 for product ID ' . $item['product_id']);
        }

        if ($unitCost < 0) {
            $conn->rollBack();
            Response::error('Unit cost cannot be negative for product ID ' . $item['product_id']);
        }

        // Validate product exists
        $stmt = $conn->prepare("SELECT id, name FROM products WHERE id = :product_id AND is_active = 1");
        $stmt->execute([':product_id' => $item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $conn->rollBack();
            Response::error('Product not found or inactive: ID ' . $item['product_id']);
        }

        $totalCost = $quantity * $unitCost;
        $totalAmount += $totalCost;

        $validatedItems[] = [
            'product_id' => $item['product_id'],
            'quantity_ordered' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'notes' => $item['notes'] ?? null
        ];
    }

    // Create purchase order
    $poQuery = "
        INSERT INTO purchase_orders (
            po_number, supplier_id, created_by, status, order_date,
            expected_delivery_date, total_amount, notes
        ) VALUES (
            :po_number, :supplier_id, :created_by, :status, :order_date,
            :expected_delivery_date, :total_amount, :notes
        )
    ";

    $stmt = $conn->prepare($poQuery);
    $stmt->execute([
        ':po_number' => $poNumber,
        ':supplier_id' => $input['supplier_id'],
        ':created_by' => $user['id'],
        // Newly created POs are sent to supplier for approval
        ':status' => 'pending_supplier',
        ':order_date' => $input['order_date'],
        ':expected_delivery_date' => $input['expected_delivery_date'] ?? null,
        ':total_amount' => $totalAmount,
        ':notes' => $input['notes'] ?? null
    ]);

    $poId = $conn->lastInsertId();

    // Create purchase order items
    foreach ($validatedItems as $item) {
        $itemQuery = "
            INSERT INTO purchase_order_items (
                po_id, product_id, quantity_ordered, quantity_received, unit_cost, notes
            ) VALUES (
                :po_id, :product_id, :quantity_ordered, :quantity_received, :unit_cost, :notes
            )
        ";

        $stmt = $conn->prepare($itemQuery);
        $stmt->execute([
            ':po_id' => $poId,
            ':product_id' => $item['product_id'],
            ':quantity_ordered' => $item['quantity_ordered'],
            ':quantity_received' => 0,
            ':unit_cost' => $item['unit_cost'],
            ':notes' => $item['notes'] ?: null
        ]);
    }

    $conn->commit();

    // Fetch created PO with details
    $stmt = $conn->prepare("
        SELECT
            po.*,
            s.full_name as supplier_name,
            s.email as supplier_email,
            u.full_name as created_by_name,
            COUNT(poi.id) as item_count
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
        WHERE po.id = :po_id
        GROUP BY po.id
    ");
    $stmt->execute([':po_id' => $poId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log purchase order creation to audit logs
    AuditLogger::logCreate('purchase_order', $poId, "Purchase order {$po['po_number']} created", [
        'po_number' => $po['po_number'],
        'supplier_id' => $po['supplier_id'],
        'supplier_name' => $po['supplier_name'],
        'order_date' => $po['order_date'],
        'status' => $po['status'],
        'total_amount' => $po['total_amount'],
        'item_count' => $po['item_count'],
        'created_by' => $po['created_by_name']
    ]);

    Response::success([
        'purchase_order' => $po
    ], 'Purchase order created successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to create purchase order: ' . $e->getMessage());
}

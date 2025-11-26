<?php
/**
 * Create Goods Received Note API Endpoint
 * POST /backend/api/grn/create.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Role-based access
if (!in_array($user['role'], ['admin', 'inventory_manager', 'purchasing_officer'])) {
    Response::error('Access denied', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Validate required fields
    $errors = Validator::required($input, ['po_id', 'received_date', 'items']);

    if ($errors) {
        Response::validationError($errors);
    }

    if (!is_array($input['items']) || empty($input['items'])) {
        Response::error('Items array is required and cannot be empty');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    $poId = intval($input['po_id']);
    $grnNumber = $input['grn_number'] ?? null;

    // Verify PO exists and is approved
    $stmt = $conn->prepare("
        SELECT po.*, s.full_name as supplier_name
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
        WHERE po.id = :po_id
    ");
    $stmt->execute([':po_id' => $poId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        $conn->rollBack();
        Response::error('Purchase order not found', 404);
    }

    if ($po['status'] !== 'approved') {
        $conn->rollBack();
        Response::error('Can only create GRN for approved purchase orders', 400);
    }

    // Generate GRN number if not provided
    if (!$grnNumber) {
        $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'grn_auto_number'");
        $prefix = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 'GRN-2025-';

        $stmt = $conn->query("SELECT COUNT(*) + 1 as next_num FROM goods_received_notes");
        $nextNum = $stmt->fetch(PDO::FETCH_ASSOC)['next_num'];
        $grnNumber = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    // Check if GRN number already exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM goods_received_notes WHERE grn_number = :grn_number");
    $stmt->execute([':grn_number' => $grnNumber]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
        $conn->rollBack();
        Response::error('GRN number already exists', 400);
    }

    // Process items and calculate totals
    $totalReceived = 0;
    $totalAccepted = 0;
    $processedItems = [];

    foreach ($input['items'] as $item) {
        if (!isset($item['po_item_id']) || !isset($item['quantity_received']) || !isset($item['quantity_accepted'])) {
            $conn->rollBack();
            Response::error('Each item must have po_item_id, quantity_received, and quantity_accepted');
        }

        $poItemId = intval($item['po_item_id']);
        $quantityReceived = intval($item['quantity_received']);
        $quantityAccepted = intval($item['quantity_accepted']);
        // quantity_rejected is auto-calculated by database (GENERATED column)

        // Get PO item details
        $stmt = $conn->prepare("
            SELECT poi.*, p.name as product_name, p.id as product_id
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.id
            WHERE poi.id = :po_item_id AND poi.po_id = :po_id
        ");
        $stmt->execute([':po_item_id' => $poItemId, ':po_id' => $poId]);
        $poItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$poItem) {
            $conn->rollBack();
            Response::error('PO item not found: ' . $poItemId);
        }

        // Validate quantities
        if ($quantityReceived < 0 || $quantityAccepted < 0 || $quantityAccepted > $quantityReceived) {
            $conn->rollBack();
            Response::error('Invalid quantities for item: ' . $poItem['product_name']);
        }

        $totalReceived += $quantityReceived;
        $totalAccepted += $quantityAccepted;

        $processedItems[] = [
            'po_item_id' => $poItemId,
            'product_id' => $poItem['product_id'],
            'quantity_received' => $quantityReceived,
            'quantity_accepted' => $quantityAccepted,
            // quantity_rejected is auto-calculated by database
            'unit_cost' => floatval($poItem['unit_cost']),
            'notes' => $item['notes'] ?? null
        ];
    }

    // Determine inspection status
    $inspectionStatus = 'passed';
    if ($totalAccepted === 0) {
        $inspectionStatus = 'failed';
    } elseif ($totalAccepted < $totalReceived) {
        $inspectionStatus = 'partial';
    }

    // Create GRN
    $grnQuery = "
        INSERT INTO goods_received_notes (
            grn_number, po_id, received_by, received_date, inspection_status, notes
        ) VALUES (
            :grn_number, :po_id, :received_by, :received_date, :inspection_status, :notes
        )
    ";

    $stmt = $conn->prepare($grnQuery);
    $stmt->execute([
        ':grn_number' => $grnNumber,
        ':po_id' => $poId,
        ':received_by' => $user['id'],
        ':received_date' => $input['received_date'],
        ':inspection_status' => $inspectionStatus,
        ':notes' => $input['notes'] ?? null
    ]);

    $grnId = $conn->lastInsertId();

    // Create GRN items and update inventory for accepted items
    foreach ($processedItems as $item) {
        // Insert GRN item (quantity_rejected is auto-calculated by DB)
        $itemQuery = "
            INSERT INTO grn_items (
                grn_id, po_item_id, product_id, quantity_received,
                quantity_accepted, unit_cost, notes
            ) VALUES (
                :grn_id, :po_item_id, :product_id, :quantity_received,
                :quantity_accepted, :unit_cost, :notes
            )
        ";

        $stmt = $conn->prepare($itemQuery);
        $stmt->execute([
            ':grn_id' => $grnId,
            ':po_item_id' => $item['po_item_id'],
            ':product_id' => $item['product_id'],
            ':quantity_received' => $item['quantity_received'],
            ':quantity_accepted' => $item['quantity_accepted'],
            ':unit_cost' => $item['unit_cost'],
            ':notes' => $item['notes']
        ]);

        // Update PO item received quantity
        $stmt = $conn->prepare("
            UPDATE purchase_order_items
            SET quantity_received = quantity_received + :quantity_received
            WHERE id = :po_item_id
        ");
        $stmt->execute([
            ':quantity_received' => $item['quantity_received'],
            ':po_item_id' => $item['po_item_id']
        ]);

        // Update inventory for accepted items
        if ($item['quantity_accepted'] > 0) {
            // Get current stock
            $stmt = $conn->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id");
            $stmt->execute([':product_id' => $item['product_id']]);
            $currentStock = $stmt->fetch(PDO::FETCH_ASSOC)['quantity_on_hand'] ?? 0;

            // Update inventory
            $stmt = $conn->prepare("
                UPDATE inventory
                SET quantity_on_hand = quantity_on_hand + :quantity
                WHERE product_id = :product_id
            ");
            $stmt->execute([
                ':quantity' => $item['quantity_accepted'],
                ':product_id' => $item['product_id']
            ]);

            // Create stock movement record
            $stmt = $conn->prepare("
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity, quantity_before, quantity_after,
                    reference_type, reference_id, performed_by, notes
                ) VALUES (
                    :product_id, 'purchase', :quantity, :quantity_before, :quantity_after,
                    'GRN', :grn_id, :user_id, :notes
                )
            ");
            $stmt->execute([
                ':product_id' => $item['product_id'],
                ':quantity' => $item['quantity_accepted'],
                ':quantity_before' => $currentStock,
                ':quantity_after' => $currentStock + $item['quantity_accepted'],
                ':grn_id' => $grnId,
                ':user_id' => $user['id'],
                ':notes' => 'GRN: ' . $grnNumber
            ]);
        }
    }

    // Update PO status based on received quantities
    $stmt = $conn->prepare("
        SELECT
            SUM(quantity_ordered) as total_ordered,
            SUM(quantity_received) as total_received
        FROM purchase_order_items
        WHERE po_id = :po_id
    ");
    $stmt->execute([':po_id' => $poId]);
    $poTotals = $stmt->fetch(PDO::FETCH_ASSOC);

    $newStatus = 'partially_received';
    if ($poTotals['total_received'] >= $poTotals['total_ordered']) {
        $newStatus = 'received';
    }

    $stmt = $conn->prepare("UPDATE purchase_orders SET status = :status WHERE id = :po_id");
    $stmt->execute([':status' => $newStatus, ':po_id' => $poId]);

    $conn->commit();

    // Fetch created GRN with details
    $stmt = $conn->prepare("
        SELECT
            grn.*,
            po.po_number,
            po.supplier_id,
            s.full_name as supplier_name,
            u.full_name as received_by_name,
            COUNT(grni.id) as item_count,
            SUM(grni.quantity_received) as total_received,
            SUM(grni.quantity_accepted) as total_accepted,
            SUM(grni.quantity_rejected) as total_rejected
        FROM goods_received_notes grn
        LEFT JOIN purchase_orders po ON grn.po_id = po.id
        LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
        LEFT JOIN users u ON grn.received_by = u.id
        LEFT JOIN grn_items grni ON grn.id = grni.grn_id
        WHERE grn.id = :grn_id
        GROUP BY grn.id
    ");
    $stmt->execute([':grn_id' => $grnId]);
    $grn = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send notification to supplier if any items were rejected
    if ($grn['total_rejected'] > 0 && $grn['supplier_id']) {
        $notificationTitle = '⚠️ Products Rejected - Replacement Required';
        $notificationMessage = "Your delivery for PO #{$grn['po_number']} (GRN #{$grn['grn_number']}) has been inspected. ";
        $notificationMessage .= "{$grn['total_rejected']} item(s) were rejected out of {$grn['total_received']} received. ";
        $notificationMessage .= "Please review the rejection details and arrange for replacement of the rejected items.";

        $notificationType = ($grn['inspection_status'] === 'failed') ? 'danger' : 'warning';

        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, reference_type, reference_id,
                action_required, action_url
            ) VALUES (
                :user_id, :type, :title, :message, :reference_type, :reference_id,
                1, :action_url
            )
        ");
        $stmt->execute([
            ':user_id' => $grn['supplier_id'],
            ':type' => $notificationType,
            ':title' => $notificationTitle,
            ':message' => $notificationMessage,
            ':reference_type' => 'grn',
            ':reference_id' => $grnId,
            ':action_url' => '/purchase-orders?view=' . $grn['po_id']
        ]);
    } elseif ($grn['total_accepted'] === $grn['total_received'] && $grn['supplier_id']) {
        // Send success notification if all items were accepted
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, reference_type, reference_id,
                action_required, action_url
            ) VALUES (
                :user_id, 'success', :title, :message, 'grn', :reference_id, 0, :action_url
            )
        ");
        $stmt->execute([
            ':user_id' => $grn['supplier_id'],
            ':title' => '✅ Products Accepted',
            ':message' => "All items from your delivery for PO #{$grn['po_number']} (GRN #{$grn['grn_number']}) have been inspected and accepted. Thank you for the quality delivery!",
            ':reference_id' => $grnId,
            ':action_url' => '/purchase-orders?view=' . $grn['po_id']
        ]);
    }

    // Log GRN creation
    AuditLogger::logCreate('grn', $grnId, "GRN {$grn['grn_number']} created for PO {$grn['po_number']}", [
        'grn_number' => $grn['grn_number'],
        'po_id' => $grn['po_id'],
        'po_number' => $grn['po_number'],
        'supplier_name' => $grn['supplier_name'],
        'received_date' => $grn['received_date'],
        'inspection_status' => $grn['inspection_status'],
        'item_count' => $grn['item_count'],
        'total_received' => $grn['total_received'],
        'total_accepted' => $grn['total_accepted'],
        'total_rejected' => $grn['total_rejected'],
        'received_by' => $grn['received_by_name']
    ]);

    Response::success([
        'grn' => $grn
    ], 'Goods received note created successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to create goods received note: ' . $e->getMessage());
}

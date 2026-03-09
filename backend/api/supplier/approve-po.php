<?php
/**
 * Supplier - Approve Purchase Order API Endpoint
 * POST /backend/api/supplier/approve-po.php
 * Body: { "po_id": 123, "notes": "Optional supplier notes" }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
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

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Only suppliers can access this endpoint
if ($user['role'] !== 'supplier') {
    Response::error('Access denied. This endpoint is only for suppliers.', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Validate required fields
    $errors = Validator::required($input, ['po_id']);

    if ($errors) {
        Response::validationError($errors);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $poId = intval($input['po_id']);

    // Start transaction
    $conn->beginTransaction();

    // Get the purchase order - verify it belongs to this supplier and is pending
    $stmt = $conn->prepare("
        SELECT id, po_number, status
        FROM purchase_orders
        WHERE id = :po_id AND supplier_id = :supplier_id AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        ':po_id' => $poId,
        ':supplier_id' => $user['id']
    ]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        $conn->rollBack();
        Response::error('Purchase order not found or access denied', 404);
    }

    // Check if PO is in the correct status
    if ($po['status'] !== 'pending_supplier') {
        $conn->rollBack();
        Response::error('Purchase order cannot be approved. Current status: ' . $po['status'], 400);
    }

    // Load PO items and auto-receive all remaining quantities.
    $itemsStmt = $conn->prepare("
        SELECT id, product_id, quantity_ordered, quantity_received, unit_cost
        FROM purchase_order_items
        WHERE po_id = :po_id
        ORDER BY id ASC
    ");
    $itemsStmt->execute([':po_id' => $poId]);
    $poItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($poItems)) {
        $conn->rollBack();
        Response::error('Purchase order has no items', 400);
    }

    // Generate unique GRN number.
    $prefixStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'grn_auto_number' LIMIT 1");
    $prefixStmt->execute();
    $grnPrefix = $prefixStmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? ('GRN-' . date('Y') . '-');

    $grnSequence = 1;
    $grnNumber = null;
    while ($grnSequence <= 99999) {
        $candidate = $grnPrefix . str_pad((string)$grnSequence, 5, '0', STR_PAD_LEFT);
        $existsStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM goods_received_notes WHERE grn_number = :grn_number");
        $existsStmt->execute([':grn_number' => $candidate]);
        $exists = (int)($existsStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
        if (!$exists) {
            $grnNumber = $candidate;
            break;
        }
        $grnSequence++;
    }

    if (!$grnNumber) {
        $conn->rollBack();
        Response::error('Failed to generate GRN number', 500);
    }

    $today = date('Y-m-d');
    $totalItemsReceived = 0;
    $totalValue = 0.0;
    $autoReceivedItems = [];

    foreach ($poItems as $poItem) {
        $ordered = (int)$poItem['quantity_ordered'];
        $alreadyReceived = (int)$poItem['quantity_received'];
        $remaining = max(0, $ordered - $alreadyReceived);
        if ($remaining <= 0) {
            continue;
        }

        $unitCost = (float)$poItem['unit_cost'];
        $totalItemsReceived += $remaining;
        $totalValue += ($remaining * $unitCost);

        $autoReceivedItems[] = [
            'po_item_id' => (int)$poItem['id'],
            'product_id' => (int)$poItem['product_id'],
            'quantity' => $remaining,
            'unit_cost' => $unitCost
        ];
    }

    if (empty($autoReceivedItems)) {
        $conn->rollBack();
        Response::error('No remaining quantities to receive for this purchase order', 400);
    }

    // Create GRN as fully passed (all accepted).
    $createGrnStmt = $conn->prepare("
        INSERT INTO goods_received_notes (
            grn_number, po_id, received_by, received_date, inspection_status, total_items_received, total_value, notes
        ) VALUES (
            :grn_number, :po_id, :received_by, :received_date, 'passed', :total_items_received, :total_value, :notes
        )
    ");
    $createGrnStmt->execute([
        ':grn_number' => $grnNumber,
        ':po_id' => $poId,
        ':received_by' => $user['id'],
        ':received_date' => $today,
        ':total_items_received' => $totalItemsReceived,
        ':total_value' => round($totalValue, 2),
        ':notes' => 'Auto-received from supplier approval'
    ]);
    $grnId = (int)$conn->lastInsertId();

    $grnItemStmt = $conn->prepare("
        INSERT INTO grn_items (
            grn_id, po_item_id, product_id, quantity_received, quantity_accepted, unit_cost, notes
        ) VALUES (
            :grn_id, :po_item_id, :product_id, :quantity_received, :quantity_accepted, :unit_cost, :notes
        )
    ");
    $updatePoItemReceivedStmt = $conn->prepare("
        UPDATE purchase_order_items
        SET quantity_received = quantity_received + :quantity_received
        WHERE id = :po_item_id
    ");
    $inventoryUpsertStmt = $conn->prepare("
        INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
        VALUES (:product_id, :quantity_delta, 0)
        ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)
    ");
    $inventoryReadStmt = $conn->prepare("
        SELECT quantity_on_hand
        FROM inventory
        WHERE product_id = :product_id
        LIMIT 1
    ");
    $movementStmt = $conn->prepare("
        INSERT INTO stock_movements (
            product_id, movement_type, quantity, quantity_before, quantity_after, reference_type, reference_id, performed_by, notes
        ) VALUES (
            :product_id, 'purchase', :quantity, :quantity_before, :quantity_after, 'GRN', :reference_id, :performed_by, :notes
        )
    ");
    $adjustmentStmt = $conn->prepare("
        INSERT INTO stock_adjustments (
            adjustment_number, product_id, adjustment_type, quantity_before, quantity_adjusted, quantity_after, reason, notes, performed_by
        ) VALUES (
            :adjustment_number, :product_id, 'add', :quantity_before, :quantity_adjusted, :quantity_after, :reason, :notes, :performed_by
        )
    ");
    $adjustmentExistsStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM stock_adjustments WHERE adjustment_number = :adjustment_number");

    $generateAdjustmentNumber = static function(int $sequence, PDOStatement $existsStmt): string {
        $attempt = 0;
        do {
            $attempt++;
            $candidate = sprintf('ADJ-%s-%03d-%02d', date('YmdHis'), $sequence, $attempt);
            $existsStmt->execute([':adjustment_number' => $candidate]);
            $exists = (int)($existsStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
            if (!$exists) {
                return $candidate;
            }
        } while ($attempt < 99);

        return 'ADJ-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    };

    foreach ($autoReceivedItems as $idx => $item) {
        $inventoryReadStmt->execute([':product_id' => $item['product_id']]);
        $stockRow = $inventoryReadStmt->fetch(PDO::FETCH_ASSOC);
        $quantityBefore = (int)($stockRow['quantity_on_hand'] ?? 0);
        $quantityAfter = $quantityBefore + $item['quantity'];

        $grnItemStmt->execute([
            ':grn_id' => $grnId,
            ':po_item_id' => $item['po_item_id'],
            ':product_id' => $item['product_id'],
            ':quantity_received' => $item['quantity'],
            ':quantity_accepted' => $item['quantity'],
            ':unit_cost' => $item['unit_cost'],
            ':notes' => 'Auto-accepted from supplier approval'
        ]);

        $updatePoItemReceivedStmt->execute([
            ':quantity_received' => $item['quantity'],
            ':po_item_id' => $item['po_item_id']
        ]);

        $inventoryUpsertStmt->execute([
            ':product_id' => $item['product_id'],
            ':quantity_delta' => $item['quantity']
        ]);

        $movementStmt->execute([
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':quantity_before' => $quantityBefore,
            ':quantity_after' => $quantityAfter,
            ':reference_id' => $grnId,
            ':performed_by' => $user['id'],
            ':notes' => "Auto stock receive via supplier approval - {$po['po_number']}"
        ]);

        // Keep a stock_adjustments trail so stock changes are visible without manual adjustment entries.
        $adjustmentNumber = $generateAdjustmentNumber($idx + 1, $adjustmentExistsStmt);
        $adjustmentStmt->execute([
            ':adjustment_number' => $adjustmentNumber,
            ':product_id' => $item['product_id'],
            ':quantity_before' => $quantityBefore,
            ':quantity_adjusted' => $item['quantity'],
            ':quantity_after' => $quantityAfter,
            ':reason' => 'supplier_auto_approval',
            ':notes' => "Auto stock adjustment from supplier approval for {$po['po_number']}",
            ':performed_by' => $user['id']
        ]);
    }

    // Mark PO approved + received in one step to remove manual inventory approval dependency.
    $hasSupplierApprovedAt = $db->columnExists('purchase_orders', 'supplier_approved_at');
    $updateQuery = "
        UPDATE purchase_orders
        SET
            status = 'received',
            approved_by = :approved_by,
            updated_at = NOW()" . ($hasSupplierApprovedAt ? ",
            supplier_approved_at = NOW()" : "") . "
        WHERE id = :po_id
    ";
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([
        ':po_id' => $poId,
        ':approved_by' => $user['id']
    ]);

    $conn->commit();

    // Fetch updated PO
    $stmt = $conn->prepare("
        SELECT
            po.*,
            u.full_name as created_by_name,
            ua.full_name as approved_by_name
        FROM purchase_orders po
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        WHERE po.id = :po_id AND po.deleted_at IS NULL
    ");
    $stmt->execute([':po_id' => $poId]);
    $updatedPO = $stmt->fetch(PDO::FETCH_ASSOC);

    AuditLogger::logUpdate('purchase_order', $poId, "Purchase order {$po['po_number']} auto-received from supplier approval", [
        'old_status' => $po['status']
    ], [
        'new_status' => 'received',
        'grn_number' => $grnNumber,
        'grn_id' => $grnId,
        'auto_inventory_update' => true,
        'items_received' => count($autoReceivedItems)
    ]);

    Response::success([
        'purchase_order' => $updatedPO,
        'auto_inventory_update' => true,
        'grn' => [
            'id' => $grnId,
            'grn_number' => $grnNumber,
            'total_items_received' => $totalItemsReceived,
            'total_value' => round($totalValue, 2)
        ]
    ], 'Purchase order approved and inventory auto-updated successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to approve purchase order: ' . $e->getMessage());
}


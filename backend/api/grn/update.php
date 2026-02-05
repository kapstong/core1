<?php
/**
 * GRN Update API Endpoint
 * PUT /backend/api/grn/update.php?id={id} - Update GRN status and details
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
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

// Check if user has permission to manage GRN
if (!in_array($user['role'], ['admin', 'inventory_manager', 'purchasing_officer'])) {
    Response::error('Access denied. Admin, inventory manager, or purchasing officer role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'PUT') {
        updateGRN();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('GRN update failed: ' . $e->getMessage());
}

function updateGRN() {
    $grnId = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$grnId) {
        Response::error('GRN ID is required', 400);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    $db = Database::getInstance()->getConnection();

    // Check if GRN exists and can be edited
    $existingQuery = "SELECT grn.*, po.status as po_status FROM goods_received_notes grn
                      INNER JOIN purchase_orders po ON grn.po_id = po.id
                      WHERE grn.id = :id AND grn.deleted_at IS NULL";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':id', $grnId, PDO::PARAM_INT);
    $existingStmt->execute();

    $grn = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$grn) {
        Response::error('GRN not found', 404);
    }

    // Cannot edit completed GRNs
    if ($grn['inspection_status'] === 'completed') {
        Response::error('Cannot edit a completed GRN', 400);
    }

    // Validate GRN number if provided
    $grnNumber = $input['grn_number'] ?? $grn['grn_number'];
    if ($grnNumber !== $grn['grn_number']) {
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM goods_received_notes WHERE grn_number = :grn_number AND id != :id AND deleted_at IS NULL");
        $checkStmt->execute([':grn_number' => $grnNumber, ':id' => $grnId]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            Response::error('GRN number already exists', 400);
        }
    }

    // Validate inspection status if provided
    $inspectionStatus = $input['inspection_status'] ?? $grn['inspection_status'];
    $validStatuses = ['pending', 'in_progress', 'completed', 'rejected'];
    if (!in_array($inspectionStatus, $validStatuses)) {
        Response::error('Invalid inspection status. Must be: pending, in_progress, completed, or rejected', 400);
    }

    // Process items if provided
    $processedItems = [];
    $totalReceived = 0;
    $totalAccepted = 0;

    if (isset($input['items']) && is_array($input['items'])) {
        // First, reverse existing inventory changes
        $revertStmt = $db->prepare("
            SELECT product_id, quantity_accepted
            FROM grn_items
            WHERE grn_id = :grn_id AND quantity_accepted > 0
        ");
        $revertStmt->execute([':grn_id' => $grnId]);
        $existingItems = $revertStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($existingItems as $item) {
            // Reverse inventory
            $invStmt = $db->prepare("UPDATE inventory SET quantity_on_hand = quantity_on_hand - :quantity WHERE product_id = :product_id");
            $invStmt->execute([
                ':quantity' => $item['quantity_accepted'],
                ':product_id' => $item['product_id']
            ]);

            // Reverse PO item received quantity
            $poStmt = $db->prepare("
                UPDATE purchase_order_items
                SET quantity_received = quantity_received - :quantity
                WHERE po_id = :po_id AND product_id = :product_id
            ");
            $poStmt->execute([
                ':quantity' => $item['quantity_accepted'],
                ':po_id' => $grn['po_id'],
                ':product_id' => $item['product_id']
            ]);
        }

        // Delete existing GRN items
        $deleteStmt = $db->prepare("DELETE FROM grn_items WHERE grn_id = :grn_id");
        $deleteStmt->execute([':grn_id' => $grnId]);

        // Process new items
        foreach ($input['items'] as $item) {
            if (!isset($item['po_item_id']) || !isset($item['quantity_received']) || !isset($item['quantity_accepted'])) {
                $db->rollBack();
                Response::error('Each item must have po_item_id, quantity_received, and quantity_accepted');
            }

            $poItemId = intval($item['po_item_id']);
            $quantityReceived = intval($item['quantity_received']);
            $quantityAccepted = intval($item['quantity_accepted']);

            // Get PO item details
            $poItemStmt = $db->prepare("
                SELECT poi.*, p.name as product_name, p.id as product_id
                FROM purchase_order_items poi
                LEFT JOIN products p ON poi.product_id = p.id
                WHERE poi.id = :po_item_id AND poi.po_id = :po_id
            ");
            $poItemStmt->execute([':po_item_id' => $poItemId, ':po_id' => $grn['po_id']]);
            $poItem = $poItemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$poItem) {
                $db->rollBack();
                Response::error('PO item not found: ' . $poItemId);
            }

            // Validate quantities
            if ($quantityReceived < 0 || $quantityAccepted < 0 || $quantityAccepted > $quantityReceived) {
                $db->rollBack();
                Response::error('Invalid quantities for item: ' . $poItem['product_name']);
            }

            $totalReceived += $quantityReceived;
            $totalAccepted += $quantityAccepted;

            $processedItems[] = [
                'po_item_id' => $poItemId,
                'product_id' => $poItem['product_id'],
                'quantity_received' => $quantityReceived,
                'quantity_accepted' => $quantityAccepted,
                'unit_cost' => floatval($poItem['unit_cost']),
                'notes' => $item['notes'] ?? null
            ];
        }
    }

    // Determine inspection status based on items if not explicitly set
    if (!isset($input['inspection_status']) && isset($input['items'])) {
        $inspectionStatus = 'pending';
        if ($totalAccepted === 0) {
            $inspectionStatus = 'rejected';
        } elseif ($totalAccepted < $totalReceived) {
            $inspectionStatus = 'in_progress';
        } else {
            $inspectionStatus = 'completed';
        }
    }

    $db->beginTransaction();

    try {
        // Update GRN header
        $updateStmt = $db->prepare("
            UPDATE goods_received_notes
            SET grn_number = :grn_number,
                received_date = :received_date,
                inspection_status = :inspection_status,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':grn_number' => $grnNumber,
            ':received_date' => $input['received_date'] ?? $grn['received_date'],
            ':inspection_status' => $inspectionStatus,
            ':notes' => $input['notes'] ?? $grn['notes'],
            ':id' => $grnId
        ]);

        // Insert new GRN items if provided
        if (!empty($processedItems)) {
            foreach ($processedItems as $item) {
                $itemStmt = $db->prepare("
                    INSERT INTO grn_items (grn_id, po_item_id, product_id, quantity_received, quantity_accepted, unit_cost, notes)
                    VALUES (:grn_id, :po_item_id, :product_id, :quantity_received, :quantity_accepted, :unit_cost, :notes)
                ");
                $itemStmt->execute([
                    ':grn_id' => $grnId,
                    ':po_item_id' => $item['po_item_id'],
                    ':product_id' => $item['product_id'],
                    ':quantity_received' => $item['quantity_received'],
                    ':quantity_accepted' => $item['quantity_accepted'],
                    ':unit_cost' => $item['unit_cost'],
                    ':notes' => $item['notes']
                ]);

                // Update PO item received quantity
                $poItemStmt = $db->prepare("
                    UPDATE purchase_order_items
                    SET quantity_received = quantity_received + :quantity_received
                    WHERE id = :po_item_id
                ");
                $poItemStmt->execute([
                    ':quantity_received' => $item['quantity_received'],
                    ':po_item_id' => $item['po_item_id']
                ]);

                // Update inventory for accepted items
                if ($item['quantity_accepted'] > 0) {
                    $invStmt = $db->prepare("
                        UPDATE inventory
                        SET quantity_on_hand = quantity_on_hand + :quantity
                        WHERE product_id = :product_id
                    ");
                    $invStmt->execute([
                        ':quantity' => $item['quantity_accepted'],
                        ':product_id' => $item['product_id']
                    ]);

                    // Create stock movement record
                    $stockStmt = $db->prepare("
                        INSERT INTO stock_movements (
                            product_id, movement_type, quantity, quantity_before, quantity_after,
                            reference_type, reference_id, performed_by, notes
                        ) VALUES (
                            :product_id, 'purchase', :quantity, :quantity_before, :quantity_after,
                            'GRN', :grn_id, :user_id, :notes
                        )
                    ");

                    // Get current stock for before/after calculation
                    $stockCheckStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id");
                    $stockCheckStmt->execute([':product_id' => $item['product_id']]);
                    $currentStock = $stockCheckStmt->fetch(PDO::FETCH_ASSOC)['quantity_on_hand'] ?? 0;

                    $stockStmt->execute([
                        ':product_id' => $item['product_id'],
                        ':quantity' => $item['quantity_accepted'],
                        ':quantity_before' => $currentStock - $item['quantity_accepted'],
                        ':quantity_after' => $currentStock,
                        ':grn_id' => $grnId,
                        ':user_id' => $user['id'],
                        ':notes' => 'GRN Updated: ' . $grnNumber
                    ]);
                }
            }
        }

        // Update PO status if items were updated
        if (!empty($processedItems)) {
            $poTotalsStmt = $db->prepare("
                SELECT
                    SUM(quantity_ordered) as total_ordered,
                    SUM(quantity_received) as total_received
                FROM purchase_order_items
                WHERE po_id = :po_id
            ");
            $poTotalsStmt->execute([':po_id' => $grn['po_id']]);
            $poTotals = $poTotalsStmt->fetch(PDO::FETCH_ASSOC);

            $newStatus = 'approved';
            if ($poTotals['total_received'] > 0) {
                $newStatus = $poTotals['total_received'] >= $poTotals['total_ordered'] ? 'received' : 'partially_received';
            }

            $poUpdateStmt = $db->prepare("UPDATE purchase_orders SET status = :status WHERE id = :po_id");
            $poUpdateStmt->execute([':status' => $newStatus, ':po_id' => $grn['po_id']]);
        }

        $db->commit();

        // Log GRN update
        AuditLogger::logUpdate('grn', $grnId, "GRN $grnNumber updated - Status: $inspectionStatus", [
            'old_status' => $grn['inspection_status'],
            'new_status' => $inspectionStatus,
            'grn_number' => $grnNumber,
            'po_id' => $grn['po_id'],
            'updated_by' => $user['full_name']
        ], [
            'grn_number' => $grnNumber,
            'inspection_status' => $inspectionStatus,
            'received_date' => $input['received_date'] ?? $grn['received_date'],
            'notes' => $input['notes'] ?? $grn['notes'],
            'item_count' => count($processedItems)
        ]);

        Response::success([
            'message' => 'GRN updated successfully',
            'grn_id' => $grnId,
            'inspection_status' => $inspectionStatus
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function processPassedInspection($db, $grnId, $userId) {
    // Get GRN items
    $itemsQuery = "SELECT * FROM grn_items WHERE grn_id = :grn_id";
    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->bindParam(':grn_id', $grnId, PDO::PARAM_INT);
    $itemsStmt->execute();
    $grnItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($grnItems as $item) {
        // Update inventory
        $inventoryQuery = "UPDATE inventory SET
                           quantity_on_hand = quantity_on_hand + :quantity,
                           last_stock_check = NOW()
                           WHERE product_id = :product_id";
        $invStmt = $db->prepare($inventoryQuery);
        $invStmt->bindParam(':quantity', $item['quantity_received'], PDO::PARAM_INT);
        $invStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $invStmt->execute();

        // Log stock movement
        $movementQuery = "INSERT INTO stock_movements
                         (product_id, movement_type, quantity, reference_type, reference_id, performed_by, notes)
                         VALUES
                         (:product_id, 'purchase', :quantity, 'GRN', :reference_id, :performed_by, 'GRN inspection passed')";

        $movStmt = $db->prepare($movementQuery);
        $movStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $movStmt->bindParam(':quantity', $item['quantity_received'], PDO::PARAM_INT);
        $movStmt->bindParam(':reference_id', $grnId, PDO::PARAM_INT);
        $movStmt->bindParam(':performed_by', $userId, PDO::PARAM_INT);
        $movStmt->execute();

        // Update purchase order item
        $poUpdateQuery = "UPDATE purchase_order_items SET
                         quantity_received = quantity_received + :quantity
                         WHERE id = :po_item_id";
        $poStmt = $db->prepare($poUpdateQuery);
        $poStmt->bindParam(':quantity', $item['quantity_received'], PDO::PARAM_INT);
        $poStmt->bindParam(':po_item_id', $item['po_item_id'], PDO::PARAM_INT);
        $poStmt->execute();
    }

    // Check if PO is fully received
    updatePurchaseOrderStatus($db, $grnId);
}

function processPartialInspection($db, $grnId, $userId, $input) {
    // For partial inspection, we need item-level details
    // This would typically involve updating individual GRN items with accepted/rejected quantities
    // For now, we'll mark as partial but require manual processing

    // Log that partial inspection requires manual review
    $logger = new Logger($userId);
    $logger->log('grn_partial_inspection', 'grn', $grnId, [
        'status' => 'requires_manual_review',
        'notes' => $input['notes'] ?? 'Partial inspection completed, requires manual processing'
    ]);
}

function updatePurchaseOrderStatus($db, $grnId) {
    // Get PO ID from GRN
    $poQuery = "SELECT po_id FROM goods_received_notes WHERE id = :grn_id";
    $poStmt = $db->prepare($poQuery);
    $poStmt->bindParam(':grn_id', $grnId, PDO::PARAM_INT);
    $poStmt->execute();
    $poResult = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$poResult) return;

    $poId = $poResult['po_id'];

    // Check if all items are fully received
    $checkQuery = "SELECT
                   SUM(poi.quantity_ordered) as total_ordered,
                   SUM(poi.quantity_received) as total_received
                   FROM purchase_order_items poi
                   WHERE poi.po_id = :po_id";

    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':po_id', $poId, PDO::PARAM_INT);
    $checkStmt->execute();
    $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

    $newStatus = 'partially_received';
    if ($checkResult['total_received'] >= $checkResult['total_ordered']) {
        $newStatus = 'received';
    }

    // Update PO status
    $updatePoQuery = "UPDATE purchase_orders SET status = :status, updated_at = NOW() WHERE id = :po_id";
    $updatePoStmt = $db->prepare($updatePoQuery);
    $updatePoStmt->bindParam(':status', $newStatus);
    $updatePoStmt->bindParam(':po_id', $poId, PDO::PARAM_INT);
    $updatePoStmt->execute();
}

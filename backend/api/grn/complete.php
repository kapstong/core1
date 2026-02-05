<?php
/**
 * Complete GRN API Endpoint
 * POST /backend/api/grn/complete.php?id={id}
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Role-based access
if (!in_array($user['role'], ['admin', 'inventory_manager', 'purchasing_officer'])) {
    Response::error('Access denied', 403);
}

try {
    $grnId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$grnId) {
        Response::error('GRN ID is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Check if GRN exists and can be completed
    $grnCheckStmt = $db->prepare("
        SELECT grn.*, po.po_number, s.full_name as supplier_name
        FROM goods_received_notes grn
        LEFT JOIN purchase_orders po ON grn.po_id = po.id
        LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
        WHERE grn.id = :id AND grn.deleted_at IS NULL
    ");
    $grnCheckStmt->execute([':id' => $grnId]);
    $grn = $grnCheckStmt->fetch(PDO::FETCH_ASSOC);

    if (!$grn) {
        Response::error('GRN not found', 404);
    }

    if ($grn['inspection_status'] === 'completed') {
        Response::error('GRN is already completed', 400);
    }

    // Get GRN items that need to be processed
    $itemsStmt = $db->prepare("
        SELECT grni.*, p.name as product_name, p.sku
        FROM grn_items grni
        LEFT JOIN products p ON grni.product_id = p.id
        WHERE grni.grn_id = :grn_id
        ORDER BY grni.id
    ");
    $itemsStmt->execute([':grn_id' => $grnId]);
    $grnItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($grnItems)) {
        Response::error('GRN has no items', 400);
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        $processedItems = 0;
        $totalReceived = 0;
        $totalAccepted = 0;
        $totalRejected = 0;

        foreach ($grnItems as $item) {
            $quantityReceived = intval($item['quantity_received']);
            $quantityAccepted = intval($item['quantity_accepted']);
            $quantityRejected = intval($item['quantity_rejected']);

            $totalReceived += $quantityReceived;
            $totalAccepted += $quantityAccepted;
            $totalRejected += $quantityRejected;

            // Only update inventory for accepted items
            if ($quantityAccepted > 0) {
                // Get current stock before update
                $stockCheckStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id");
                $stockCheckStmt->execute([':product_id' => $item['product_id']]);
                $currentStock = $stockCheckStmt->fetch(PDO::FETCH_ASSOC)['quantity_on_hand'] ?? 0;

                // Update inventory
                $inventoryStmt = $db->prepare("
                    UPDATE inventory
                    SET quantity_on_hand = quantity_on_hand + :quantity,
                        last_stock_check = NOW()
                    WHERE product_id = :product_id
                ");
                $inventoryStmt->execute([
                    ':quantity' => $quantityAccepted,
                    ':product_id' => $item['product_id']
                ]);

                // Create stock movement record
                $movementStmt = $db->prepare("
                    INSERT INTO stock_movements (
                        product_id, movement_type, quantity, quantity_before, quantity_after,
                        reference_type, reference_id, performed_by, notes
                    ) VALUES (
                        :product_id, 'purchase', :quantity, :quantity_before, :quantity_after,
                        'GRN', :grn_id, :user_id, :notes
                    )
                ");
                $movementStmt->execute([
                    ':product_id' => $item['product_id'],
                    ':quantity' => $quantityAccepted,
                    ':quantity_before' => $currentStock,
                    ':quantity_after' => $currentStock + $quantityAccepted,
                    ':grn_id' => $grnId,
                    ':user_id' => $user['id'],
                    ':notes' => "GRN Completed - {$grn['grn_number']}"
                ]);
                $processedItems++;
            }

            // Update PO item received quantity if not already updated
            $poItemStmt = $db->prepare("
                UPDATE purchase_order_items
                SET quantity_received = quantity_received + :quantity_received
                WHERE id = :po_item_id
            ");
            $poItemStmt->execute([
                ':quantity_received' => $quantityReceived,
                ':po_item_id' => $item['po_item_id']
            ]);
        }

        // Update PO status based on total received quantities
        $poTotalsStmt = $db->prepare("
            SELECT
                SUM(quantity_ordered) as total_ordered,
                SUM(quantity_received) as total_received
            FROM purchase_order_items
            WHERE po_id = :po_id
        ");
        $poTotalsStmt->execute([':po_id' => $grn['po_id']]);
        $poTotals = $poTotalsStmt->fetch(PDO::FETCH_ASSOC);

        $newPOStatus = 'received';
        if ($poTotals['total_received'] >= $poTotals['total_ordered']) {
            $newPOStatus = 'received';
        } elseif ($poTotals['total_received'] > 0) {
            $newPOStatus = 'partially_received';
        } else {
            $newPOStatus = 'approved';
        }

        $poUpdateStmt = $db->prepare("UPDATE purchase_orders SET status = :status, updated_at = NOW() WHERE id = :po_id");
        $poUpdateStmt->execute([':status' => $newPOStatus, ':po_id' => $grn['po_id']]);

        // Update GRN status to completed
        $grnUpdateStmt = $db->prepare("
            UPDATE goods_received_notes
            SET inspection_status = 'completed',
                updated_at = NOW()
            WHERE id = :id
        ");
        $grnUpdateStmt->execute([':id' => $grnId]);

        $db->commit();

        // Log GRN completion
        AuditLogger::logUpdate('grn', $grnId, "GRN {$grn['grn_number']} completed - Inventory updated", [
            'old_status' => $grn['inspection_status'],
            'new_status' => 'completed',
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'supplier_name' => $grn['supplier_name'],
            'completed_by' => $user['full_name']
        ], [
            'inspection_status' => 'completed',
            'items_processed' => $processedItems,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'po_status_updated' => $newPOStatus
        ]);

        Response::success([
            'id' => $grnId,
            'status' => 'completed',
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'supplier_name' => $grn['supplier_name'],
            'items_processed' => $processedItems,
            'total_received' => $totalReceived,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'inventory_updated' => true,
            'po_status_updated' => $newPOStatus,
            'completed_by' => $user['full_name'],
            'completed_at' => date('Y-m-d H:i:s')
        ], 'GRN completed successfully and inventory updated');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("GRN Complete Error: " . $e->getMessage());
    Response::error('An error occurred while completing GRN: ' . $e->getMessage(), 500);
}


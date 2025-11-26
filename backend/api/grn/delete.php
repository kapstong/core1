<?php
/**
 * GRN Delete API Endpoint
 * DELETE /backend/api/grn/delete.php - Delete GRN and reverse inventory changes
 */


// TEMPORARY: Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Start session before auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

// Get user data
$user = Auth::user();

try {
    // Accept POST or DELETE methods
    if ($method === 'POST' || $method === 'DELETE') {
        deleteGRN($user);
    } else {
        Response::error("Method $method not allowed. Use POST or DELETE.", 405);
    }

} catch (Exception $e) {
    error_log("GRN Delete Error: " . $e->getMessage());
    Response::serverError('Failed to delete GRN: ' . $e->getMessage());
}

function deleteGRN($user) {
    // Accept ID from either POST body or query string
    if (!isset($_POST['id']) && !isset($_GET['id'])) {
        Response::error('GRN ID is required', 400);
    }

    $grnId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $reason = $_POST['reason'] ?? $_GET['reason'] ?? 'Deleted by ' . $user['full_name'];

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Get GRN details
        $grnQuery = "SELECT g.*, po.po_number
                     FROM goods_received_notes g
                     INNER JOIN purchase_orders po ON g.po_id = po.id
                     WHERE g.id = :id";
        $grnStmt = $db->prepare($grnQuery);
        $grnStmt->bindParam(':id', $grnId, PDO::PARAM_INT);
        $grnStmt->execute();

        $grn = $grnStmt->fetch(PDO::FETCH_ASSOC);

        if (!$grn) {
            throw new Exception('GRN not found');
        }

        // Get GRN items
        $itemsQuery = "SELECT * FROM grn_items WHERE grn_id = :grn_id";
        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->bindParam(':grn_id', $grnId, PDO::PARAM_INT);
        $itemsStmt->execute();

        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            throw new Exception('GRN has no items');
        }

        // Reverse inventory for each accepted item
        $warnings = [];
        foreach ($items as $item) {
            if ($item['quantity_accepted'] > 0) {
                // Get current inventory
                $checkQuery = "SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $checkStmt->execute();
                $currentInventory = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$currentInventory) {
                    throw new Exception("Cannot delete GRN: Inventory record not found for product ID {$item['product_id']}");
                }

                // Calculate before/after quantities BEFORE updating
                $quantityBefore = $currentInventory['quantity_on_hand'];
                $quantityAfter = $quantityBefore - $item['quantity_accepted'];

                // Warn if inventory will go negative (but allow it)
                if ($quantityAfter < 0) {
                    $warnings[] = "Product ID {$item['product_id']} inventory will become negative ({$quantityAfter}). This indicates inventory was consumed after GRN creation.";
                }

                // Reduce inventory
                $inventoryQuery = "UPDATE inventory SET quantity_on_hand = quantity_on_hand - :quantity
                                  WHERE product_id = :product_id";
                $inventoryStmt = $db->prepare($inventoryQuery);
                $inventoryStmt->bindParam(':quantity', $item['quantity_accepted'], PDO::PARAM_INT);
                $inventoryStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $inventoryStmt->execute();

                // Create stock movement record (reversal) - simple INSERT
                $movementQuery = "
                    INSERT INTO stock_movements (
                        product_id, movement_type, quantity, quantity_before, quantity_after,
                        reference_type, reference_id, performed_by, notes
                    ) VALUES (
                        :product_id, 'adjustment', :quantity_neg, :quantity_before, :quantity_after,
                        'GRN_DELETED', :grn_id, :user_id, :notes
                    )
                ";

                $movementStmt = $db->prepare($movementQuery);
                $movementStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $negativeQty = -$item['quantity_accepted'];
                $movementStmt->bindParam(':quantity_neg', $negativeQty, PDO::PARAM_INT);
                $movementStmt->bindParam(':quantity_before', $quantityBefore, PDO::PARAM_INT);
                $movementStmt->bindParam(':quantity_after', $quantityAfter, PDO::PARAM_INT);
                $movementStmt->bindParam(':grn_id', $grnId, PDO::PARAM_INT);
                $movementStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $notes = "GRN Deleted - {$grn['grn_number']} - PO: {$grn['po_number']} - Reason: {$reason}";
                $movementStmt->bindParam(':notes', $notes);
                $movementStmt->execute();

                // Update purchase order item received quantities
                $poItemQuery = "UPDATE purchase_order_items
                               SET quantity_received = quantity_received - :quantity
                               WHERE po_id = :po_id AND product_id = :product_id";
                $poItemStmt = $db->prepare($poItemQuery);
                $poItemStmt->bindParam(':quantity', $item['quantity_accepted'], PDO::PARAM_INT);
                $poItemStmt->bindParam(':po_id', $grn['po_id'], PDO::PARAM_INT);
                $poItemStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $poItemStmt->execute();
            }
        }

        // Update purchase order status
        $checkPOQuery = "SELECT
                            SUM(poi.quantity_ordered) as total_ordered,
                            SUM(poi.quantity_received) as total_received
                         FROM purchase_order_items poi
                         WHERE poi.po_id = :po_id";
        $checkPOStmt = $db->prepare($checkPOQuery);
        $checkPOStmt->bindParam(':po_id', $grn['po_id'], PDO::PARAM_INT);
        $checkPOStmt->execute();
        $poStatus = $checkPOStmt->fetch(PDO::FETCH_ASSOC);

        $newPOStatus = 'approved'; // Default back to approved
        if ($poStatus['total_received'] > 0 && $poStatus['total_received'] < $poStatus['total_ordered']) {
            $newPOStatus = 'partially_received';
        } elseif ($poStatus['total_received'] >= $poStatus['total_ordered']) {
            $newPOStatus = 'received';
        }

        $updatePOQuery = "UPDATE purchase_orders SET status = :status WHERE id = :po_id";
        $updatePOStmt = $db->prepare($updatePOQuery);
        $updatePOStmt->bindParam(':status', $newPOStatus);
        $updatePOStmt->bindParam(':po_id', $grn['po_id'], PDO::PARAM_INT);
        $updatePOStmt->execute();

        // Delete GRN items
        $deleteItemsQuery = "DELETE FROM grn_items WHERE grn_id = :grn_id";
        $deleteItemsStmt = $db->prepare($deleteItemsQuery);
        $deleteItemsStmt->bindParam(':grn_id', $grnId, PDO::PARAM_INT);
        $deleteItemsStmt->execute();

        // Delete GRN
        $deleteGRNQuery = "DELETE FROM goods_received_notes WHERE id = :id";
        $deleteGRNStmt = $db->prepare($deleteGRNQuery);
        $deleteGRNStmt->bindParam(':id', $grnId, PDO::PARAM_INT);
        $deleteGRNStmt->execute();

        $db->commit();

        // Log GRN deletion
        AuditLogger::logDelete('grn', $grnId, "GRN {$grn['grn_number']} deleted - Inventory reversed", [
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'items_reversed' => count($items),
            'inventory_reversed' => array_sum(array_column($items, 'quantity_accepted')),
            'reason' => $reason,
            'deleted_by' => $user['full_name']
        ]);

        $responseData = [
            'message' => 'GRN deleted successfully and inventory reversed',
            'grn_id' => $grnId,
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'items_reversed' => count($items),
            'po_status_updated' => $newPOStatus,
            'deleted_by' => $user['full_name']
        ];

        if (!empty($warnings)) {
            $responseData['warnings'] = $warnings;
        }

        Response::success($responseData);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

<?php
/**
 * GRN Delete API Endpoint
 * DELETE /backend/api/grn/delete.php - Delete GRN and reverse inventory changes
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer']);

$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

try {
    if ($method === 'POST' || $method === 'DELETE') {
        deleteGRN($user);
    } else {
        Response::error("Method $method not allowed. Use POST or DELETE.", 405);
    }
} catch (Exception $e) {
    error_log("GRN Delete Error: " . $e->getMessage());
    Response::serverError('Failed to delete GRN: ' . $e->getMessage());
}

function deleteGRN(array $user): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $grnId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if ($grnId <= 0) {
        Response::error('GRN ID is required', 400);
    }

    $reason = trim((string)($input['reason'] ?? $_GET['reason'] ?? ('Deleted by ' . $user['full_name'])));

    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();
    $hasDeletedAt = $dbInstance->columnExists('goods_received_notes', 'deleted_at');

    $db->beginTransaction();

    try {
        $grnQuery = "
            SELECT g.*, po.po_number
            FROM goods_received_notes g
            INNER JOIN purchase_orders po ON g.po_id = po.id
            WHERE g.id = :id" . ($hasDeletedAt ? " AND g.deleted_at IS NULL" : "");
        $grnStmt = $db->prepare($grnQuery);
        $grnStmt->execute([':id' => $grnId]);
        $grn = $grnStmt->fetch(PDO::FETCH_ASSOC);

        if (!$grn) {
            throw new Exception('GRN not found');
        }

        $itemsStmt = $db->prepare("
            SELECT id, po_item_id, product_id, quantity_received, quantity_accepted
            FROM grn_items
            WHERE grn_id = :grn_id
            ORDER BY id
        ");
        $itemsStmt->execute([':grn_id' => $grnId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            throw new Exception('GRN has no items');
        }

        $warnings = [];

        foreach ($items as $item) {
            $acceptedQuantity = (int)$item['quantity_accepted'];
            $receivedQuantity = (int)$item['quantity_received'];
            $productId = (int)$item['product_id'];

            if ($acceptedQuantity > 0) {
                $inventoryStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id LIMIT 1");
                $inventoryStmt->execute([':product_id' => $productId]);
                $quantityBefore = $inventoryStmt->fetchColumn();

                if ($quantityBefore === false) {
                    throw new Exception("Cannot delete GRN: Inventory record not found for product ID {$productId}");
                }

                $quantityBefore = (int)$quantityBefore;
                $quantityAfter = $quantityBefore - $acceptedQuantity;

                if ($quantityAfter < 0) {
                    $warnings[] = "Product ID {$productId} inventory will become negative ({$quantityAfter}). This indicates inventory was consumed after GRN creation.";
                }

                $updateInventoryStmt = $db->prepare("
                    UPDATE inventory
                    SET quantity_on_hand = quantity_on_hand - :quantity
                    WHERE product_id = :product_id
                ");
                $updateInventoryStmt->execute([
                    ':quantity' => $acceptedQuantity,
                    ':product_id' => $productId
                ]);

                $movementStmt = $db->prepare("
                    INSERT INTO stock_movements (
                        product_id, movement_type, quantity, quantity_before, quantity_after,
                        reference_type, reference_id, performed_by, notes
                    ) VALUES (
                        :product_id, 'adjustment', :quantity, :quantity_before, :quantity_after,
                        'GRN', :reference_id, :performed_by, :notes
                    )
                ");
                $movementStmt->execute([
                    ':product_id' => $productId,
                    ':quantity' => -$acceptedQuantity,
                    ':quantity_before' => $quantityBefore,
                    ':quantity_after' => $quantityAfter,
                    ':reference_id' => $grnId,
                    ':performed_by' => $user['id'],
                    ':notes' => "GRN Deleted - {$grn['grn_number']} - PO: {$grn['po_number']} - Reason: {$reason}"
                ]);
            }

            $poItemStmt = $db->prepare("
                UPDATE purchase_order_items
                SET quantity_received = GREATEST(0, quantity_received - :quantity)
                WHERE id = :po_item_id
            ");
            $poItemStmt->execute([
                ':quantity' => $receivedQuantity,
                ':po_item_id' => $item['po_item_id']
            ]);
        }

        deletePurchaseReceivedAdjustments($db, $grnId, $grn['grn_number']);

        $newPOStatus = updatePurchaseOrderStatusAfterDelete($db, (int)$grn['po_id']);

        if ($hasDeletedAt) {
            $deleteGRNStmt = $db->prepare("
                UPDATE goods_received_notes
                SET deleted_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $deleteGRNStmt->execute([':id' => $grnId]);
        } else {
            $deleteItemsStmt = $db->prepare("DELETE FROM grn_items WHERE grn_id = :grn_id");
            $deleteItemsStmt->execute([':grn_id' => $grnId]);

            $deleteGRNStmt = $db->prepare("DELETE FROM goods_received_notes WHERE id = :id");
            $deleteGRNStmt->execute([':id' => $grnId]);
        }

        $db->commit();

        AuditLogger::logDelete('grn', $grnId, "GRN {$grn['grn_number']} deleted - Inventory reversed", [
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'items_reversed' => count($items),
            'inventory_reversed' => array_sum(array_map(static function ($item) {
                return (int)$item['quantity_accepted'];
            }, $items)),
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function updatePurchaseOrderStatusAfterDelete(PDO $db, int $poId): string {
    $checkPOStmt = $db->prepare("
        SELECT
            COALESCE(SUM(quantity_ordered), 0) AS total_ordered,
            COALESCE(SUM(quantity_received), 0) AS total_received
        FROM purchase_order_items
        WHERE po_id = :po_id
    ");
    $checkPOStmt->execute([':po_id' => $poId]);
    $poStatus = $checkPOStmt->fetch(PDO::FETCH_ASSOC);

    $totalOrdered = (int)($poStatus['total_ordered'] ?? 0);
    $totalReceived = (int)($poStatus['total_received'] ?? 0);

    $newPOStatus = 'approved';
    if ($totalReceived > 0 && $totalReceived < $totalOrdered) {
        $newPOStatus = 'partially_received';
    } elseif ($totalOrdered > 0 && $totalReceived >= $totalOrdered) {
        $newPOStatus = 'received';
    }

    $updatePOStmt = $db->prepare("
        UPDATE purchase_orders
        SET status = :status,
            updated_at = NOW()
        WHERE id = :po_id
    ");
    $updatePOStmt->execute([
        ':status' => $newPOStatus,
        ':po_id' => $poId
    ]);

    return $newPOStatus;
}

function deletePurchaseReceivedAdjustments(PDO $db, int $grnId, string $grnNumber): void {
    $legacyNotesPattern = 'Auto-generated from GRN ' . $grnNumber . '%';
    $tagPattern = '%[GRN_ID:' . $grnId . ']%';

    $stmt = $db->prepare("
        DELETE FROM stock_adjustments
        WHERE reason = 'purchase_received'
          AND (
              notes LIKE :legacy_notes
              OR notes LIKE :tag_pattern
          )
    ");
    $stmt->execute([
        ':legacy_notes' => $legacyNotesPattern,
        ':tag_pattern' => $tagPattern
    ]);
}

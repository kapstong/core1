<?php
/**
 * GRN Update API Endpoint
 * PUT /backend/api/grn/update.php?id={id} - Update GRN status and details
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

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();

if (!in_array($user['role'], ['admin', 'inventory_manager', 'purchasing_officer'], true)) {
    Response::error('Access denied. Admin, inventory manager, or purchasing officer role required', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error('Method not allowed', 405);
}

try {
    updateGRN($user);
} catch (Exception $e) {
    Response::serverError('GRN update failed: ' . $e->getMessage());
}

function updateGRN(array $user): void {
    $grnId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($grnId <= 0) {
        Response::error('GRN ID is required', 400);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();
    $hasDeletedAt = $dbInstance->columnExists('goods_received_notes', 'deleted_at');

    $existingQuery = "
        SELECT grn.*, po.po_number, po.status AS po_status
        FROM goods_received_notes grn
        INNER JOIN purchase_orders po ON grn.po_id = po.id
        WHERE grn.id = :id" . ($hasDeletedAt ? " AND grn.deleted_at IS NULL" : "");
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->execute([':id' => $grnId]);
    $grn = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$grn) {
        Response::error('GRN not found', 404);
    }

    if (($grn['inspection_status'] ?? null) === 'completed') {
        Response::error('Cannot edit a completed GRN', 400);
    }

    $grnNumber = trim((string)($input['grn_number'] ?? $grn['grn_number']));
    if ($grnNumber === '') {
        Response::error('GRN number is required', 400);
    }

    if ($grnNumber !== $grn['grn_number']) {
        $checkStmt = $db->prepare("
            SELECT COUNT(*) AS count
            FROM goods_received_notes
            WHERE grn_number = :grn_number
              AND id != :id" . ($hasDeletedAt ? " AND deleted_at IS NULL" : "")
        );
        $checkStmt->execute([
            ':grn_number' => $grnNumber,
            ':id' => $grnId
        ]);

        if ((int)$checkStmt->fetchColumn() > 0) {
            Response::error('GRN number already exists', 400);
        }
    }

    $existingItemsStmt = $db->prepare("
        SELECT id, po_item_id, product_id, quantity_received, quantity_accepted, unit_cost, notes
        FROM grn_items
        WHERE grn_id = :grn_id
        ORDER BY id
    ");
    $existingItemsStmt->execute([':grn_id' => $grnId]);
    $existingItems = $existingItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $existingReceivedByPoItem = [];
    foreach ($existingItems as $existingItem) {
        $existingReceivedByPoItem[(int)$existingItem['po_item_id']] = (int)$existingItem['quantity_received'];
    }

    $processedItems = [];
    $itemCount = count($existingItems);
    $totalReceived = 0;
    $totalAccepted = 0;

    if (array_key_exists('items', $input)) {
        if (!is_array($input['items']) || empty($input['items'])) {
            Response::error('Items array is required and cannot be empty', 400);
        }

        foreach ($input['items'] as $item) {
            if (!isset($item['po_item_id'], $item['quantity_received'], $item['quantity_accepted'])) {
                Response::error('Each item must have po_item_id, quantity_received, and quantity_accepted', 400);
            }

            $poItemId = (int)$item['po_item_id'];
            $quantityReceived = (int)$item['quantity_received'];
            $quantityAccepted = (int)$item['quantity_accepted'];

            $poItemStmt = $db->prepare("
                SELECT poi.*, p.name AS product_name, p.id AS product_id, p.sku AS product_sku
                FROM purchase_order_items poi
                LEFT JOIN products p ON poi.product_id = p.id
                WHERE poi.id = :po_item_id AND poi.po_id = :po_id
            ");
            $poItemStmt->execute([
                ':po_item_id' => $poItemId,
                ':po_id' => $grn['po_id']
            ]);
            $poItem = $poItemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$poItem) {
                Response::error('PO item not found: ' . $poItemId, 400);
            }

            if ($quantityReceived < 0 || $quantityAccepted < 0 || $quantityAccepted > $quantityReceived) {
                Response::error('Invalid quantities for item: ' . $poItem['product_name'], 400);
            }

            $orderedQuantity = (int)$poItem['quantity_ordered'];
            $currentReceived = (int)$poItem['quantity_received'];
            $oldReceivedForThisGrn = (int)($existingReceivedByPoItem[$poItemId] ?? 0);
            $remainingQuantity = max(0, $orderedQuantity - max(0, $currentReceived - $oldReceivedForThisGrn));

            if ($quantityReceived > $remainingQuantity) {
                Response::error(
                    'Cannot receive more than the remaining quantity for item: ' .
                    $poItem['product_name'] . '. Remaining quantity: ' . $remainingQuantity,
                    400
                );
            }

            $processedItems[] = [
                'po_item_id' => $poItemId,
                'product_id' => (int)$poItem['product_id'],
                'product_name' => $poItem['product_name'] ?? ('Product #' . $poItem['product_id']),
                'product_sku' => $poItem['product_sku'] ?? null,
                'quantity_received' => $quantityReceived,
                'quantity_accepted' => $quantityAccepted,
                'unit_cost' => (float)$poItem['unit_cost'],
                'notes' => $item['notes'] ?? null
            ];

            $totalReceived += $quantityReceived;
            $totalAccepted += $quantityAccepted;
        }

        $itemCount = count($processedItems);
    } else {
        foreach ($existingItems as $existingItem) {
            $totalReceived += (int)$existingItem['quantity_received'];
            $totalAccepted += (int)$existingItem['quantity_accepted'];
        }
    }

    $inspectionStatus = null;
    if (array_key_exists('inspection_status', $input)) {
        $inspectionStatus = normalizeInspectionStatus($input['inspection_status']);
        if ($inspectionStatus === null) {
            Response::error('Invalid inspection status. Must be pending, passed, partial, or failed', 400);
        }
    } elseif (array_key_exists('items', $input)) {
        $inspectionStatus = deriveInspectionStatus($totalReceived, $totalAccepted);
    } else {
        $inspectionStatus = normalizeInspectionStatus($grn['inspection_status']) ?? $grn['inspection_status'];
    }

    $db->beginTransaction();

    try {
        if (array_key_exists('items', $input)) {
            deletePurchaseReceivedAdjustments($db, $grnId, $grn['grn_number']);

            foreach ($existingItems as $existingItem) {
                $existingAccepted = (int)$existingItem['quantity_accepted'];
                $existingReceived = (int)$existingItem['quantity_received'];

                if ($existingAccepted > 0) {
                    $inventoryStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id LIMIT 1");
                    $inventoryStmt->execute([':product_id' => $existingItem['product_id']]);
                    $quantityBefore = $inventoryStmt->fetchColumn();

                    if ($quantityBefore === false) {
                        throw new Exception('Inventory record not found for product ID ' . $existingItem['product_id']);
                    }

                    $quantityBefore = (int)$quantityBefore;
                    $quantityAfter = $quantityBefore - $existingAccepted;

                    $reverseInventoryStmt = $db->prepare("
                        UPDATE inventory
                        SET quantity_on_hand = quantity_on_hand - :quantity
                        WHERE product_id = :product_id
                    ");
                    $reverseInventoryStmt->execute([
                        ':quantity' => $existingAccepted,
                        ':product_id' => $existingItem['product_id']
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
                        ':product_id' => $existingItem['product_id'],
                        ':quantity' => -$existingAccepted,
                        ':quantity_before' => $quantityBefore,
                        ':quantity_after' => $quantityAfter,
                        ':reference_id' => $grnId,
                        ':performed_by' => $user['id'],
                        ':notes' => 'GRN update reversal: ' . $grn['grn_number']
                    ]);
                }

                $reversePoStmt = $db->prepare("
                    UPDATE purchase_order_items
                    SET quantity_received = GREATEST(0, quantity_received - :quantity_received)
                    WHERE id = :po_item_id
                ");
                $reversePoStmt->execute([
                    ':quantity_received' => $existingReceived,
                    ':po_item_id' => $existingItem['po_item_id']
                ]);
            }

            $deleteItemsStmt = $db->prepare("DELETE FROM grn_items WHERE grn_id = :grn_id");
            $deleteItemsStmt->execute([':grn_id' => $grnId]);
        }

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

        if (array_key_exists('items', $input)) {
            foreach ($processedItems as $item) {
                $itemStmt = $db->prepare("
                    INSERT INTO grn_items (
                        grn_id, po_item_id, product_id, quantity_received,
                        quantity_accepted, unit_cost, notes
                    ) VALUES (
                        :grn_id, :po_item_id, :product_id, :quantity_received,
                        :quantity_accepted, :unit_cost, :notes
                    )
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

                $poItemStmt = $db->prepare("
                    UPDATE purchase_order_items
                    SET quantity_received = quantity_received + :quantity_received
                    WHERE id = :po_item_id
                ");
                $poItemStmt->execute([
                    ':quantity_received' => $item['quantity_received'],
                    ':po_item_id' => $item['po_item_id']
                ]);

                if ($item['quantity_accepted'] > 0) {
                    $inventoryStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id LIMIT 1");
                    $inventoryStmt->execute([':product_id' => $item['product_id']]);
                    $quantityBefore = (int)($inventoryStmt->fetchColumn() ?: 0);

                    $applyInventoryStmt = $db->prepare("
                        INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                        VALUES (:product_id, :quantity, 0)
                        ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)
                    ");
                    $applyInventoryStmt->execute([
                        ':product_id' => $item['product_id'],
                        ':quantity' => $item['quantity_accepted']
                    ]);

                    $adjustmentId = createPurchaseReceivedAdjustment(
                        $db,
                        $item,
                        $quantityBefore,
                        (int)$user['id'],
                        $grnId,
                        $grnNumber,
                        $grn['po_number']
                    );

                    $movementStmt = $db->prepare("
                        INSERT INTO stock_movements (
                            product_id, movement_type, quantity, quantity_before, quantity_after,
                            reference_type, reference_id, performed_by, notes
                        ) VALUES (
                            :product_id, 'purchase', :quantity, :quantity_before, :quantity_after,
                            'GRN', :reference_id, :performed_by, :notes
                        )
                    ");
                    $movementStmt->execute([
                        ':product_id' => $item['product_id'],
                        ':quantity' => $item['quantity_accepted'],
                        ':quantity_before' => $quantityBefore,
                        ':quantity_after' => $quantityBefore + $item['quantity_accepted'],
                        ':reference_id' => $grnId,
                        ':performed_by' => $user['id'],
                        ':notes' => 'GRN Updated: ' . $grnNumber . ' | Adjustment ID: ' . $adjustmentId
                    ]);
                }
            }
        }

        $newPoStatus = updatePurchaseOrderStatusForGrn($db, (int)$grn['po_id']);

        $db->commit();

        AuditLogger::logUpdate('grn', $grnId, "GRN {$grnNumber} updated - Status: {$inspectionStatus}", [
            'old_status' => $grn['inspection_status'],
            'new_status' => $inspectionStatus,
            'grn_number' => $grnNumber,
            'po_id' => $grn['po_id'],
            'po_number' => $grn['po_number'],
            'updated_by' => $user['full_name']
        ], [
            'received_date' => $input['received_date'] ?? $grn['received_date'],
            'notes' => $input['notes'] ?? $grn['notes'],
            'item_count' => $itemCount,
            'total_received' => $totalReceived,
            'total_accepted' => $totalAccepted,
            'po_status' => $newPoStatus
        ]);

        Response::success([
            'message' => 'GRN updated successfully',
            'grn_id' => $grnId,
            'inspection_status' => $inspectionStatus,
            'po_status' => $newPoStatus
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function normalizeInspectionStatus($status): ?string {
    if ($status === null) {
        return null;
    }

    $normalized = strtolower(trim((string)$status));
    $map = [
        'pending' => 'pending',
        'passed' => 'passed',
        'partial' => 'partial',
        'failed' => 'failed',
        'completed' => 'passed',
        'in_progress' => 'partial',
        'rejected' => 'failed'
    ];

    return $map[$normalized] ?? null;
}

function deriveInspectionStatus(int $totalReceived, int $totalAccepted): string {
    if ($totalReceived <= 0) {
        return 'pending';
    }

    if ($totalAccepted <= 0) {
        return 'failed';
    }

    if ($totalAccepted < $totalReceived) {
        return 'partial';
    }

    return 'passed';
}

function updatePurchaseOrderStatusForGrn(PDO $db, int $poId): string {
    $poTotalsStmt = $db->prepare("
        SELECT
            COALESCE(SUM(quantity_ordered), 0) AS total_ordered,
            COALESCE(SUM(quantity_received), 0) AS total_received
        FROM purchase_order_items
        WHERE po_id = :po_id
    ");
    $poTotalsStmt->execute([':po_id' => $poId]);
    $poTotals = $poTotalsStmt->fetch(PDO::FETCH_ASSOC);

    $totalOrdered = (int)($poTotals['total_ordered'] ?? 0);
    $totalReceived = (int)($poTotals['total_received'] ?? 0);

    $newStatus = 'approved';
    if ($totalReceived >= $totalOrdered && $totalOrdered > 0) {
        $newStatus = 'received';
    } elseif ($totalReceived > 0) {
        $newStatus = 'partially_received';
    }

    $poUpdateStmt = $db->prepare("
        UPDATE purchase_orders
        SET status = :status,
            updated_at = NOW()
        WHERE id = :po_id
    ");
    $poUpdateStmt->execute([
        ':status' => $newStatus,
        ':po_id' => $poId
    ]);

    return $newStatus;
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

function createPurchaseReceivedAdjustment(
    PDO $db,
    array $item,
    int $currentStock,
    int $userId,
    int $grnId,
    string $grnNumber,
    string $poNumber
): int {
    $adjustmentNumber = generateStockAdjustmentNumber($db);
    $quantityAdjusted = (int)$item['quantity_accepted'];
    $quantityAfter = $currentStock + $quantityAdjusted;
    $reason = 'purchase_received';
    $notes = 'Auto-generated from GRN ' . $grnNumber . ' for PO ' . $poNumber . ' [GRN_ID:' . $grnId . ']';

    $stmt = $db->prepare("
        INSERT INTO stock_adjustments (
            adjustment_number, product_id, adjustment_type, quantity_before,
            quantity_adjusted, quantity_after, reason, performed_by, notes
        ) VALUES (
            :adjustment_number, :product_id, 'add', :quantity_before,
            :quantity_adjusted, :quantity_after, :reason, :performed_by, :notes
        )
    ");
    $stmt->execute([
        ':adjustment_number' => $adjustmentNumber,
        ':product_id' => $item['product_id'],
        ':quantity_before' => $currentStock,
        ':quantity_adjusted' => $quantityAdjusted,
        ':quantity_after' => $quantityAfter,
        ':reason' => $reason,
        ':performed_by' => $userId,
        ':notes' => $notes
    ]);

    $adjustmentId = (int)$db->lastInsertId();

    AuditLogger::logCreate(
        'stock_adjustment',
        $adjustmentId,
        "Stock adjustment {$adjustmentNumber} auto-created from GRN {$grnNumber}",
        [
            'adjustment_number' => $adjustmentNumber,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'] ?? null,
            'product_sku' => $item['product_sku'] ?? null,
            'adjustment_type' => 'add',
            'quantity_before' => $currentStock,
            'quantity_adjusted' => $quantityAdjusted,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'grn_id' => $grnId,
            'grn_number' => $grnNumber,
            'po_number' => $poNumber,
            'auto_generated' => true
        ]
    );

    return $adjustmentId;
}

function generateStockAdjustmentNumber(PDO $db): string {
    $prefix = 'ADJ-' . date('Ymd') . '-';

    $stmt = $db->prepare("
        SELECT adjustment_number
        FROM stock_adjustments
        WHERE adjustment_number LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':prefix' => $prefix . '%']);
    $lastAdjustmentNumber = $stmt->fetchColumn();

    $nextNumber = 1;
    if (is_string($lastAdjustmentNumber) && preg_match('/(\d+)\s*$/', $lastAdjustmentNumber, $matches)) {
        $nextNumber = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
}

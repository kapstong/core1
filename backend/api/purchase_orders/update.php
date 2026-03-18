<?php
/**
 * Update Purchase Order API Endpoint
 * PUT /backend/api/purchase_orders/update.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
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

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error('Method not allowed', 405);
}

// Role-based access
if (!in_array($user['role'], ['admin', 'purchasing_officer', 'supplier'])) {
    Response::error('Access denied', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    if (!isset($input['id'])) {
        Response::error('Purchase order ID is required', 400);
    }

    $poId = intval($input['id']);

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $hasDeletedAt = $db->columnExists('purchase_orders', 'deleted_at');

    // Check if PO exists
    $stmt = $conn->prepare("SELECT * FROM purchase_orders WHERE id = :id" . ($hasDeletedAt ? " AND deleted_at IS NULL" : ""));
    $stmt->execute([':id' => $poId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        Response::error('Purchase order not found', 404);
    }

    $hasExpectedDeliveryDate = array_key_exists('expected_delivery_date', $input) || array_key_exists('expected_delivery', $input);
    if ($hasExpectedDeliveryDate) {
        $input['expected_delivery_date'] = trim((string)($input['expected_delivery_date'] ?? $input['expected_delivery'] ?? ''));

        if ($input['expected_delivery_date'] === '') {
            Response::validationError([
                'expected_delivery_date' => 'Expected delivery date is required'
            ]);
        }

        if ($input['expected_delivery_date'] < $po['order_date']) {
            Response::error('Expected delivery date cannot be earlier than the order date', 422);
        }
    }

    // Start transaction
    $conn->beginTransaction();

    $updateData = [];
    $updateFields = [];

    // Handle status changes with business logic
    if (isset($input['status'])) {
        $newStatus = $input['status'];

        // Validate status transition
    $validStatuses = ['draft', 'submitted', 'pending_approval', 'approved', 'ordered', 'partially_received', 'received', 'cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            $conn->rollBack();
            Response::error('Invalid status', 400);
        }

        // Business rules for status changes
        switch ($newStatus) {
                case 'approved':
                    // Admins and purchasing officers approve internally submitted POs
                    if ($user['role'] === 'admin' || $user['role'] === 'purchasing_officer') {
                        if ($po['status'] !== 'submitted' && $po['status'] !== 'pending_approval') {
                            $conn->rollBack();
                            Response::error('Can only approve submitted or pending_approval purchase orders', 400);
                        }
                        $updateData['approved_by'] = $user['id'];
                        $updateData['approved_at'] = date('Y-m-d H:i:s');
                    } elseif ($user['role'] === 'supplier') {
                        // Suppliers can approve POs that are pending_supplier and belong to them
                        if ($po['status'] !== 'pending_supplier') {
                            $conn->rollBack();
                            Response::error('Can only approve purchase orders pending supplier approval', 400);
                        }

                        // Verify PO is assigned to this supplier (supplier_id = user_id)
                        if ($po['supplier_id'] != $user['id']) {
                            $conn->rollBack();
                            Response::error('Access denied. PO not assigned to this supplier.', 403);
                        }

                        $updateData['supplier_approved_at'] = date('Y-m-d H:i:s');
                    } else {
                        $conn->rollBack();
                        Response::error('Only admin, purchasing officer or supplier can approve POs', 403);
                    }
                    break;

            case 'cancelled':
                // Allow cancellation when not yet received
                if (in_array($po['status'], ['received', 'partially_received'])) {
                    $conn->rollBack();
                    Response::error('Cannot cancel received purchase orders', 400);
                }

                // If supplier is cancelling (i.e., rejecting), ensure it is their PO and status pending_supplier
                if ($user['role'] === 'supplier') {
                    if ($po['status'] !== 'pending_supplier') {
                        $conn->rollBack();
                        Response::error('Suppliers can only reject purchase orders pending supplier approval', 400);
                    }
                    // Verify PO is assigned to this supplier (supplier_id = user_id)
                    if ($po['supplier_id'] != $user['id']) {
                        $conn->rollBack();
                        Response::error('Access denied. PO not assigned to this supplier.', 403);
                    }
                }
                break;

            case 'submitted':
                if ($po['status'] !== 'draft') {
                    $conn->rollBack();
                    Response::error('Can only submit draft purchase orders', 400);
                }
                break;
        }

        $updateFields[] = "status = :status";
        $updateData['status'] = $newStatus;
    }

    // Handle other updatable fields
    $allowedFields = ['expected_delivery_date', 'notes'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "{$field} = :{$field}";
            $updateData[$field] = $input[$field];
        }
    }

    // Handle item updates (auto-adjust PO totals when items are added/removed/edited)
    if (array_key_exists('items', $input)) {
        if (!is_array($input['items']) || empty($input['items'])) {
            $conn->rollBack();
            Response::error('Items array is required and cannot be empty', 400);
        }

        // Prevent item edits once receiving has started or PO lifecycle is finalized
        if (in_array($po['status'], ['approved', 'ordered', 'partially_received', 'received', 'cancelled'], true)) {
            $conn->rollBack();
            Response::error('Cannot update PO items for this status', 400);
        }

        $receivedCheckStmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity_received), 0) AS total_received
            FROM purchase_order_items
            WHERE po_id = :po_id
        ");
        $receivedCheckStmt->execute([':po_id' => $poId]);
        $receivedTotals = $receivedCheckStmt->fetch(PDO::FETCH_ASSOC);
        $hasReceivedQty = ((int)($receivedTotals['total_received'] ?? 0)) > 0;

        if ($hasReceivedQty) {
            $conn->rollBack();
            Response::error('Cannot update PO items after receiving has started', 400);
        }

        $validatedItems = [];
        $seenProductIds = [];
        $recalculatedTotal = 0.0;

        foreach ($input['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity_ordered']) || !isset($item['unit_cost'])) {
                $conn->rollBack();
                Response::error('Each item must have product_id, quantity_ordered, and unit_cost', 400);
            }

            $productId = (int)$item['product_id'];
            $quantity = (int)$item['quantity_ordered'];
            $unitCost = (float)$item['unit_cost'];

            if ($productId <= 0) {
                $conn->rollBack();
                Response::error('Invalid product_id in items', 400);
            }

            if ($quantity <= 0) {
                $conn->rollBack();
                Response::error('Quantity ordered must be greater than 0 for product ID ' . $productId, 400);
            }

            if ($unitCost < 0) {
                $conn->rollBack();
                Response::error('Unit cost cannot be negative for product ID ' . $productId, 400);
            }

            if (isset($seenProductIds[$productId])) {
                $conn->rollBack();
                Response::error('Duplicate product in items is not allowed (product ID ' . $productId . ')', 400);
            }
            $seenProductIds[$productId] = true;

            $productStmt = $conn->prepare("SELECT id FROM products WHERE id = :product_id AND is_active = 1");
            $productStmt->execute([':product_id' => $productId]);
            $productExists = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$productExists) {
                $conn->rollBack();
                Response::error('Product not found or inactive: ID ' . $productId, 400);
            }

            $lineTotal = $quantity * $unitCost;
            $recalculatedTotal += $lineTotal;

            $validatedItems[] = [
                'product_id' => $productId,
                'quantity_ordered' => $quantity,
                'unit_cost' => $unitCost,
                'notes' => $item['notes'] ?? null
            ];
        }

        // Replace existing items with the validated set
        $deleteItemsStmt = $conn->prepare("DELETE FROM purchase_order_items WHERE po_id = :po_id");
        $deleteItemsStmt->execute([':po_id' => $poId]);

        $insertItemStmt = $conn->prepare("
            INSERT INTO purchase_order_items (
                po_id, product_id, quantity_ordered, quantity_received, unit_cost, notes
            ) VALUES (
                :po_id, :product_id, :quantity_ordered, :quantity_received, :unit_cost, :notes
            )
        ");

        foreach ($validatedItems as $validatedItem) {
            $insertItemStmt->execute([
                ':po_id' => $poId,
                ':product_id' => $validatedItem['product_id'],
                ':quantity_ordered' => $validatedItem['quantity_ordered'],
                ':quantity_received' => 0,
                ':unit_cost' => $validatedItem['unit_cost'],
                ':notes' => $validatedItem['notes']
            ]);
        }

        $updateFields[] = "total_amount = :total_amount";
        $updateData['total_amount'] = round($recalculatedTotal, 2);
    }

    if (empty($updateFields)) {
        $conn->rollBack();
        Response::error('No valid fields to update', 400);
    }

    // Update the purchase order
    $updateQuery = "UPDATE purchase_orders SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
    $updateData['id'] = $poId;

    $stmt = $conn->prepare($updateQuery);
    $stmt->execute($updateData);

    $conn->commit();

    // Fetch updated PO
    $stmt = $conn->prepare("
        SELECT
            po.*,
            s.full_name as supplier_name,
            u.full_name as created_by_name,
            ua.full_name as approved_by_name
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        WHERE po.id = :po_id
    ");
    $stmt->execute([':po_id' => $poId]);
    $updatedPO = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log purchase order update to audit logs
    AuditLogger::logUpdate('purchase_order', $poId, "Purchase order {$updatedPO['po_number']} updated", $po, $updatedPO);

    Response::success([
        'purchase_order' => $updatedPO
    ], 'Purchase order updated successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to update purchase order: ' . $e->getMessage());
}


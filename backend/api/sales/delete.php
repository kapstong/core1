<?php
/**
 * Sales Delete/Void API Endpoint
 * DELETE /backend/api/sales/delete.php - Void a sale and restore inventory
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer', 'staff']);

$method = $_SERVER['REQUEST_METHOD'];

// Get authenticated user
$user = Auth::user();

try {
    if ($method === 'POST' || $method === 'DELETE') {
        voidSale($user);
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to void sale: ' . $e->getMessage());
}

function voidSale($user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $saleId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if ($saleId <= 0) {
        Response::error('Sale ID is required', 400);
    }

    $reason = trim($input['reason'] ?? $_GET['reason'] ?? ('Voided by ' . $user['full_name']));

    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();
    $hasStatusColumn = $dbInstance->columnExists('sales', 'status');
    $db->beginTransaction();

    try {
        // Get sale details
        $saleQuery = "SELECT * FROM sales WHERE id = :id";
        $saleStmt = $db->prepare($saleQuery);
        $saleStmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $saleStmt->execute();

        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            throw new Exception('Sale not found');
        }

        // Check if already voided/refunded
        $alreadyVoided = $hasStatusColumn && (($sale['status'] ?? null) === 'voided');
        $alreadyVoided = $alreadyVoided || (($sale['payment_status'] ?? null) === 'refunded');
        $alreadyVoided = $alreadyVoided || (is_string($sale['notes'] ?? null) && strpos($sale['notes'], '[VOIDED]') !== false);

        if ($alreadyVoided) {
            throw new Exception('Sale is already voided');
        }

        // Get sale items
        $itemsQuery = "SELECT * FROM sale_items WHERE sale_id = :sale_id";
        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $itemsStmt->execute();

        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            throw new Exception('Sale has no items');
        }

        // Restore inventory for each item
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $productId = (int)$item['product_id'];

            $inventoryQuery = "SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id LIMIT 1";
            $inventoryStmt = $db->prepare($inventoryQuery);
            $inventoryStmt->execute([':product_id' => $productId]);
            $quantityBefore = (int)($inventoryStmt->fetchColumn() ?: 0);

            $restoreStmt = $db->prepare("
                INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                VALUES (:product_id, :quantity, 0)
                ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)
            ");
            $restoreStmt->execute([
                ':product_id' => $productId,
                ':quantity' => $quantity
            ]);

            // Create stock movement record (return)
            $movementQuery = "
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity, quantity_before, quantity_after,
                    reference_type, reference_id, performed_by, notes
                ) VALUES (
                    :product_id, 'return', :quantity, :quantity_before, :quantity_after,
                    'SALE', :sale_id, :user_id, :notes
                )
            ";

            $movementStmt = $db->prepare($movementQuery);
            $movementStmt->execute([
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':quantity_before' => $quantityBefore,
                ':quantity_after' => $quantityBefore + $quantity,
                ':sale_id' => $saleId,
                ':user_id' => $user['id'],
                ':notes' => "Sale Voided - Invoice: {$sale['invoice_number']} - Reason: {$reason}"
            ]);
        }

        // Update sale record to prevent repeat inventory restoration
        $updateQuery = "UPDATE sales SET payment_status = 'refunded', notes = CONCAT(COALESCE(notes, ''), '\n[VOIDED] ', :reason), updated_at = NOW()";
        if ($hasStatusColumn) {
            $updateQuery .= ", status = 'voided'";
        }
        $updateQuery .= " WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':reason', $reason);
        $updateStmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $updateStmt->execute();

        // Log activity
        $activityQuery = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
                         VALUES (:user_id, 'void', 'sale', :sale_id, :description)";
        $activityStmt = $db->prepare($activityQuery);
        $activityStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
        $activityStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $description = "Voided sale {$sale['invoice_number']} - Reason: {$reason} - Restored " . count($items) . " items to inventory";
        $activityStmt->bindParam(':description', $description);
        $activityStmt->execute();

        $db->commit();

        Response::success([
            'message' => 'Sale voided successfully and inventory restored',
            'sale_id' => $saleId,
            'invoice_number' => $sale['invoice_number'],
            'items_restored' => count($items),
            'total_amount' => $sale['total_amount'],
            'voided_by' => $user['full_name']
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}


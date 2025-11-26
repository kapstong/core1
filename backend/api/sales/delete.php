<?php
/**
 * Sales Delete/Void API Endpoint
 * DELETE /backend/api/sales/delete.php - Void a sale and restore inventory
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

Auth::requireAuth();

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
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        Response::error('Sale ID is required', 400);
    }

    $saleId = (int)$_GET['id'];
    $reason = $_GET['reason'] ?? 'Voided by ' . $user['full_name'];

    $db = Database::getInstance()->getConnection();
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

        // Check if already voided
        if ($sale['status'] === 'voided') {
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
            // Restore stock
            $inventoryQuery = "UPDATE inventory SET quantity_on_hand = quantity_on_hand + :quantity
                              WHERE product_id = :product_id";
            $inventoryStmt = $db->prepare($inventoryQuery);
            $inventoryStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
            $inventoryStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
            $inventoryStmt->execute();

            // Create stock movement record (return)
            $movementQuery = "
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity, quantity_before, quantity_after,
                    reference_type, reference_id, performed_by, notes
                )
                SELECT
                    :product_id, 'return', :quantity, i.quantity_on_hand - :quantity,
                    i.quantity_on_hand, 'SALE_VOIDED', :sale_id, :user_id, :notes
                FROM inventory i
                WHERE i.product_id = :product_id
            ";

            $movementStmt = $db->prepare($movementQuery);
            $movementStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
            $movementStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
            $movementStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
            $movementStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $notes = "Sale Voided - Invoice: {$sale['invoice_number']} - Reason: {$reason}";
            $movementStmt->bindParam(':notes', $notes);
            $movementStmt->execute();
        }

        // Update sale status to voided
        $updateQuery = "UPDATE sales SET status = 'voided', notes = CONCAT(COALESCE(notes, ''), '\n[VOIDED] ', :reason)
                       WHERE id = :id";
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

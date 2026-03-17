<?php
/**
 * Sales Refund API Endpoint
 * POST /backend/api/sales/refund.php
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
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer', 'staff']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['sale_id'])) {
        Response::error('Sale ID is required');
    }

    $saleId = intval($input['sale_id']);
    $refundAmount = isset($input['refund_amount']) ? floatval($input['refund_amount']) : null;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';

    $db = Database::getInstance()->getConnection();
    $dbInstance = Database::getInstance();
    $user = Auth::user();
    $hasStatusColumn = $dbInstance->columnExists('sales', 'status');

    // Get sale details
    $stmt = $db->prepare("SELECT * FROM sales WHERE id = :id");
    $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $stmt->execute();
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        Response::error('Sale not found', 404);
    }

    if (($sale['payment_status'] ?? null) === 'refunded') {
        Response::error('Sale has already been refunded', 400);
    }

    if ($hasStatusColumn && ($sale['status'] ?? null) === 'voided') {
        Response::error('Voided sales cannot be refunded again', 400);
    }

    // If no refund amount specified, refund full amount
    if ($refundAmount === null) {
        $refundAmount = (float)$sale['total_amount'];
    }

    // Validate refund amount
    if ($refundAmount <= 0) {
        Response::error('Refund amount must be greater than zero', 400);
    }

    if ($refundAmount > (float)$sale['total_amount']) {
        Response::error('Refund amount cannot exceed sale total');
    }

    if (abs($refundAmount - (float)$sale['total_amount']) > 0.01) {
        Response::error('Partial refunds are not supported by this endpoint', 400);
    }

    // Get sale items to restore inventory
    $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = :sale_id");
    $stmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        Response::error('Sale has no items', 400);
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Restore inventory for each item
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $productId = (int)$item['product_id'];

            $inventoryStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id LIMIT 1");
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

            // Log stock movement
            $stmt = $db->prepare("
                INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reference_type, reference_id, performed_by, notes, created_at)
                VALUES (:product_id, 'return', :quantity, :quantity_before, :quantity_after, 'SALE', :sale_id, :user_id, :notes, NOW())
            ");
            $stmt->execute([
                'product_id' => $productId,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityBefore + $quantity,
                'sale_id' => $saleId,
                'user_id' => $user['id'],
                'notes' => "Refund - {$reason}"
            ]);
        }

        // Update sale status
        $updateSql = "UPDATE sales SET payment_status = 'refunded', notes = CONCAT(COALESCE(notes, ''), '\nRefunded: ', :reason, ' (Amount: ', :amount, ')'), updated_at = NOW() WHERE id = :id";

        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            'id' => $saleId,
            'reason' => $reason,
            'amount' => $refundAmount
        ]);

        $db->commit();

        Response::success([
            'sale_id' => $saleId,
            'refund_amount' => $refundAmount,
            'status' => 'refunded'
        ], 'Sale refunded successfully');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Sales Refund Error: " . $e->getMessage());
    Response::error('An error occurred while processing refund: ' . $e->getMessage(), 500);
}


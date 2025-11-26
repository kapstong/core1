<?php
/**
 * Sales Refund API Endpoint
 * POST /backend/api/sales/refund.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
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

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['sale_id'])) {
        Response::error('Sale ID is required');
    }

    $saleId = intval($input['sale_id']);
    $refundAmount = isset($input['refund_amount']) ? floatval($input['refund_amount']) : null;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';

    $db = Database::getInstance()->getConnection();
    $user = Auth::user();

    // Get sale details
    $stmt = $db->prepare("SELECT * FROM sales WHERE id = :id");
    $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $stmt->execute();
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        Response::error('Sale not found', 404);
    }

    // If no refund amount specified, refund full amount
    if ($refundAmount === null) {
        $refundAmount = $sale['total_amount'];
    }

    // Validate refund amount
    if ($refundAmount > $sale['total_amount']) {
        Response::error('Refund amount cannot exceed sale total');
    }

    // Get sale items to restore inventory
    $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = :sale_id");
    $stmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Begin transaction
    $db->beginTransaction();

    try {
        // Restore inventory for each item
        foreach ($items as $item) {
            $stmt = $db->prepare("UPDATE inventory SET quantity_on_hand = quantity_on_hand + :quantity WHERE product_id = :product_id");
            $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
            $stmt->execute();

            // Log stock movement
            $stmt = $db->prepare("
                INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reference_type, reference_id, performed_by, notes, created_at)
                SELECT :product_id, 'return', :quantity, quantity_on_hand - :quantity, quantity_on_hand, 'REFUND', :sale_id, :user_id, :notes, NOW()
                FROM inventory WHERE product_id = :product_id
            ");
            $stmt->execute([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'sale_id' => $saleId,
                'user_id' => $user['id'],
                'notes' => "Refund - {$reason}"
            ]);
        }

        // Update sale status
        $stmt = $db->prepare("UPDATE sales SET payment_status = 'refunded', notes = CONCAT(COALESCE(notes, ''), '\nRefunded: ', :reason, ' (Amount: ', :amount, ')'), updated_at = NOW() WHERE id = :id");
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

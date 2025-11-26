<?php
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../utils/Logger.php';

header('Content-Type: application/json; charset=UTF-8');

CORS::handle();

// Check authentication using session-based auth
Auth::requireAuth();

// Get user data
$user = Auth::user();

// Check permissions - staff, admin, or inventory_manager can use POS
$allowedRoles = ['admin', 'staff', 'inventory_manager'];
if (!in_array($user['role'], $allowedRoles)) {
    Response::error('Access denied', 403);
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Parse JSON input
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::error('Invalid JSON data', 400);
            }

            // Validate required fields
            $required = ['items', 'total_amount', 'payment_method'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    Response::error("Missing required field: $field", 400);
                }
            }

            $customerId = $data['customer_id'] ?? null;
            $items = $data['items'];
            $totalAmount = floatval($data['total_amount']);
            $paymentMethod = $data['payment_method'];
            $cashReceived = isset($data['cash_received']) ? floatval($data['cash_received']) : null;
            $changeGiven = isset($data['change_given']) ? floatval($data['change_given']) : null;
            $notes = $data['notes'] ?? null;

            // Validate items
            if (empty($items) || !is_array($items)) {
                Response::error('Items must be a non-empty array', 400);
            }

            // Begin transaction
            $db->beginTransaction();

            try {
                // Check stock availability for all items
                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    $quantity = intval($item['quantity']);

                    // Get current stock
                    $productQuery = "SELECT stock_quantity, name FROM products WHERE id = ? FOR UPDATE";
                    $productStmt = $db->prepare($productQuery);
                    $productStmt->execute([$productId]);
                    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        throw new Exception("Product with ID $productId not found");
                    }

                    if ($product['stock_quantity'] < $quantity) {
                        throw new Exception("Insufficient stock for {$product['name']}. Available: {$product['stock_quantity']}, Requested: $quantity");
                    }
                }

                // Generate unique transaction number
                $transactionNumber = generateUniqueTransactionNumber($db);

                // Create POS transaction
                $transactionQuery = "INSERT INTO pos_transactions (
                    transaction_number,
                    customer_id,
                    cashier_id,
                    total_amount,
                    payment_method,
                    cash_received,
                    change_given,
                    notes,
                    status,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())";

                $transactionStmt = $db->prepare($transactionQuery);
                $transactionStmt->execute([
                    $transactionNumber,
                    $customerId,
                    $user['id'],
                    $totalAmount,
                    $paymentMethod,
                    $cashReceived,
                    $changeGiven,
                    $notes
                ]);

                $transactionId = $db->lastInsertId();

                // Process transaction items and inventory
                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    $quantity = intval($item['quantity']);
                    $unitPrice = floatval($item['unit_price']);
                    $totalPrice = floatval($item['total_price']);

                    // Insert transaction item
                    $itemQuery = "INSERT INTO pos_transaction_items (
                        transaction_id,
                        product_id,
                        quantity,
                        unit_price,
                        total_price,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())";

                    $itemStmt = $db->prepare($itemQuery);
                    $itemStmt->execute([
                        $transactionId,
                        $productId,
                        $quantity,
                        $unitPrice,
                        $totalPrice
                    ]);

                    // Update inventory - Note: products table uses stock_quantity field, not inventory table
                    $inventoryQuery = "UPDATE products SET
                        stock_quantity = stock_quantity - ?,
                        updated_at = NOW()
                        WHERE id = ?";

                    $inventoryStmt = $db->prepare($inventoryQuery);
                    $inventoryStmt->execute([$quantity, $productId]);

                    // Create stock movement record for audit trail
                    $movementQuery = "INSERT INTO stock_movements (
                        product_id,
                        transaction_id,
                        movement_type,
                        quantity,
                        reason,
                        reference_type,
                        reference_id,
                        created_by,
                        created_at,
                        notes
                    ) VALUES (?, ?, 'sale', ?, 'POS Sale', 'pos_transaction', ?, ?, NOW(), ?)";

                    $movementStmt = $db->prepare($movementQuery);
                    $movementStmt->execute([
                        $productId,
                        $transactionId,
                        -$quantity, // Negative for out movement
                        $transactionId,
                        $user['id'],
                        "POS Transaction #$transactionNumber"
                    ]);
                }

                // Log the transaction
                Logger::info("POS transaction created", [
                    'transaction_id' => $transactionId,
                    'transaction_number' => $transactionNumber,
                    'cashier_id' => $user['id'],
                    'total_amount' => $totalAmount,
                    'item_count' => count($items)
                ]);

                // Commit transaction
                $db->commit();

                // Return success response
                Response::success('POS transaction created successfully', [
                    'transaction_id' => $transactionId,
                    'transaction_number' => $transactionNumber,
                    'total_amount' => $totalAmount,
                    'item_count' => count($items),
                    'payment_method' => $paymentMethod,
                    'processed_by' => $user['full_name'] ?? $user['username']
                ]);

            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                throw $e;
            }

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Logger::error("POS transaction error", [
        'error' => $e->getMessage(),
        'user_id' => $user['id'] ?? null
    ]);

    Response::error('Transaction failed: ' . $e->getMessage(), 500);
}

function generateUniqueTransactionNumber($db) {
    do {
        $today = date('Y-m-d');
        $timestamp = date('His');
        $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $number = "POS-" . date('Ymd') . "-" . $timestamp . $random;

        // Check if number already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM pos_transactions WHERE transaction_number = ?");
        $checkStmt->execute([$number]);
        $count = $checkStmt->fetchColumn();

    } while ($count > 0);

    return $number;
}
?>

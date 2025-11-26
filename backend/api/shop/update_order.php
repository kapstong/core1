<?php
/**
 * Update Customer Order Status API Endpoint
 * PUT /backend/api/shop/update_order.php - Update order status and details
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

CORS::handle();

// Require authentication
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        updateOrder();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to update order: ' . $e->getMessage());
}

function updateOrder() {
    $user = Auth::user();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['id']) || empty($input['id'])) {
        Response::error('Order ID is required', 400);
    }

    $orderId = (int)$input['id'];
    $db = Database::getInstance()->getConnection();

    // Get current order
    $checkQuery = "SELECT * FROM customer_orders WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $orderId, PDO::PARAM_INT);
    $checkStmt->execute();

    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        Response::error('Order not found', 404);
    }

    // Cannot update cancelled or refunded orders
    if (in_array($order['status'], ['cancelled', 'refunded'])) {
        Response::error('Cannot update cancelled or refunded orders. Use cancel or return endpoints instead.', 400);
    }

    $db->beginTransaction();

    try {
        // Build update query
        $updateFields = [];
        $params = [':id' => $orderId];

        // Status update with validation
        if (isset($input['status'])) {
            $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed'];
            if (!in_array($input['status'], $validStatuses)) {
                throw new Exception('Invalid status. Valid statuses: ' . implode(', ', $validStatuses));
            }

            $updateFields[] = "status = :status";
            $params[':status'] = $input['status'];

            // Auto-set dates based on status
            if ($input['status'] === 'shipped' && !$order['shipped_date']) {
                $updateFields[] = "shipped_date = NOW()";
            }
            if ($input['status'] === 'delivered' && !$order['delivered_date']) {
                $updateFields[] = "delivered_date = NOW()";
            }
        }

        // Payment status update
        if (isset($input['payment_status'])) {
            $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
            if (!in_array($input['payment_status'], $validPaymentStatuses)) {
                throw new Exception('Invalid payment status');
            }

            $updateFields[] = "payment_status = :payment_status";
            $params[':payment_status'] = $input['payment_status'];
        }

        // Transaction ID
        if (isset($input['transaction_id'])) {
            $updateFields[] = "transaction_id = :transaction_id";
            $params[':transaction_id'] = $input['transaction_id'];
        }

        // Tracking number (for shipping)
        if (isset($input['tracking_number'])) {
            $updateFields[] = "notes = CONCAT(COALESCE(notes, ''), '\nTracking: ', :tracking_number)";
            $params[':tracking_number'] = $input['tracking_number'];
        }

        // Notes
        if (isset($input['notes']) && !empty($input['notes'])) {
            $updateFields[] = "notes = CONCAT(COALESCE(notes, ''), '\n', :notes)";
            $params[':notes'] = $input['notes'];
        }

        // Manual date overrides (admin only)
        if (isset($input['shipped_date']) && !empty($input['shipped_date'])) {
            $updateFields[] = "shipped_date = :shipped_date";
            $params[':shipped_date'] = $input['shipped_date'];
        }

        if (isset($input['delivered_date']) && !empty($input['delivered_date'])) {
            $updateFields[] = "delivered_date = :delivered_date";
            $params[':delivered_date'] = $input['delivered_date'];
        }

        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }

        // Update order
        $query = "UPDATE customer_orders SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        // Send email notification if status changed
        if (isset($input['status']) && $input['status'] !== $order['status']) {
            try {
                require_once __DIR__ . '/../../utils/Email.php';
                $email = new Email();

                // Get customer and updated order info
                $customerQuery = "SELECT c.*, co.* FROM customers c
                                 INNER JOIN customer_orders co ON c.id = co.customer_id
                                 WHERE co.id = :order_id";
                $customerStmt = $db->prepare($customerQuery);
                $customerStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
                $customerStmt->execute();
                $orderData = $customerStmt->fetch(PDO::FETCH_ASSOC);

                if ($orderData) {
                    // Extract customer info
                    $customerData = [
                        'first_name' => $orderData['first_name'],
                        'last_name' => $orderData['last_name'],
                        'email' => $orderData['email']
                    ];
                    $email->sendOrderStatusUpdate($orderData, $customerData, $order['status'], $input['status']);
                }
            } catch (Exception $e) {
                // Log error but don't fail update
                error_log('Failed to send order status email: ' . $e->getMessage());
            }
        }

        // Log activity
        $activityQuery = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
                         VALUES (:user_id, 'update', 'customer_order', :order_id, :description)";
        $activityStmt = $db->prepare($activityQuery);
        $activityStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
        $activityStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $description = "Updated order {$order['order_number']} - " . implode(', ', array_keys($params));
        $activityStmt->bindParam(':description', $description);
        $activityStmt->execute();

        $db->commit();

        // Get updated order
        $getQuery = "SELECT co.*, c.email, c.first_name, c.last_name
                     FROM customer_orders co
                     INNER JOIN customers c ON co.customer_id = c.id
                     WHERE co.id = :id";
        $getStmt = $db->prepare($getQuery);
        $getStmt->bindParam(':id', $orderId, PDO::PARAM_INT);
        $getStmt->execute();

        $updatedOrder = $getStmt->fetch(PDO::FETCH_ASSOC);

        Response::success([
            'message' => 'Order updated successfully',
            'order' => $updatedOrder,
            'changes' => array_keys($params)
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

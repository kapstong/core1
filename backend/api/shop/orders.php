<?php
/**
 * Customer Orders API Endpoint
 * GET /backend/api/shop/orders.php - Get customer's orders
 * POST /backend/api/shop/orders.php - Create order (checkout)
 *
 * Actions:
 * - list: Get customer's orders with filtering
 * - create: Create new order from cart
 * - cancel: Cancel an order
 * - details: Get detailed order information
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../models/Product.php';

CORS::handle();

// Start session for customer authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;

        case 'POST':
            handlePostRequest($action);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Order error: ' . $e->getMessage());
}

function handleGetRequest($action) {
    // Check if user is authenticated (customer or staff)
    $isStaff = Auth::check() && in_array(Auth::user()['role'], ['staff', 'inventory_manager', 'purchasing_officer', 'admin']);
    $isCustomer = isset($_SESSION['customer_id']);

    if (!$isStaff && !$isCustomer) {
        Response::error('Authentication required', 401);
    }

    // If staff, allow viewing pending orders; if customer, show their orders
    $customerId = $isCustomer ? $_SESSION['customer_id'] : null;

    // Handle different actions
    if ($action === '' && isset($_GET['status']) && $_GET['status'] === 'pending') {
        if (!$isStaff) {
            error_log('DEBUG: User not staff. isStaff=' . ($isStaff ? 'true' : 'false') . ', isCustomer=' . ($isCustomer ? 'true' : 'false'));
            Response::error('Staff access required for pending orders', 403);
        }
        // Staff viewing pending orders
        error_log('DEBUG: Fetching pending orders for user: ' . json_encode(Auth::user()));
        getPendingOrders();
        return;
    }

    switch ($action) {
        case 'list':
            if (!$isCustomer) {
                Response::error('Customer authentication required', 401);
            }
            getCustomerOrders($customerId);
            break;

        case 'details':
            getOrderDetails($customerId, $isStaff);
            break;

        default:
            Response::error('Invalid action. Supported actions: list, details', 400);
    }
}

function handlePostRequest($action) {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    switch ($action) {
        case 'create':
            createOrder($customerId, $input);
            break;

        case 'cancel':
            cancelOrder($customerId, $input);
            break;

        default:
            Response::error('Invalid action. Supported actions: create, cancel', 400);
    }
}

function getCustomerOrders($customerId) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    $db = Database::getInstance()->getConnection();

    // Build WHERE clause
    $whereConditions = ["co.customer_id = ?"];
    $params = [$customerId];

    if (!empty($status)) {
        $whereConditions[] = "co.status = ?";
        $params[] = $status;
    }

    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(co.order_date) >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(co.order_date) <= ?";
        $params[] = $dateTo;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get orders with basic info
    $stmt = $db->prepare("
        SELECT
            co.id,
            co.order_number,
            co.status,
            co.order_date,
            co.subtotal,
            co.tax_amount,
            co.shipping_amount,
            co.discount_amount,
            co.total_amount,
            co.tracking_number,
            co.shipping_method
        FROM customer_orders co
        WHERE {$whereClause}
        ORDER BY co.order_date DESC
        LIMIT ? OFFSET ?
    ");

    $params[] = $limit;
    $params[] = $offset;

    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM customer_orders co WHERE {$whereClause}");
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get order items for each order
    foreach ($orders as &$order) {
        $itemsStmt = $db->prepare("
            SELECT
                coi.product_name,
                coi.product_sku,
                coi.quantity,
                coi.unit_price,
                coi.total_price,
                p.image_url as product_image
            FROM customer_order_items coi
            LEFT JOIN products p ON coi.product_id = p.id
            WHERE coi.order_id = ?
            ORDER BY coi.id
        ");
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    Response::success([
        'orders' => $orders,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function getPendingOrders() {
    $db = Database::getInstance()->getConnection();

    // Get all pending customer orders for staff review
    $stmt = $db->prepare("
        SELECT
            co.id,
            co.order_number,
            co.status,
            co.created_at,
            co.order_date,
            co.subtotal,
            co.tax_amount,
            co.shipping_amount,
            co.total_amount,
            co.payment_method,
            co.payment_status,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email,
            c.phone
        FROM customer_orders co
        INNER JOIN customers c ON co.customer_id = c.id
        WHERE co.status = 'pending'
        ORDER BY co.created_at DESC
    ");

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('DEBUG: getPendingOrders query result count: ' . count($orders));
    error_log('DEBUG: getPendingOrders query result: ' . json_encode($orders));

    // Get order items count for each order
    foreach ($orders as &$order) {
        $countStmt = $db->prepare("SELECT COUNT(*) as items_count FROM customer_order_items WHERE order_id = ?");
        $countStmt->execute([$order['id']]);
        $order['items_count'] = $countStmt->fetch(PDO::FETCH_ASSOC)['items_count'];
    }

    Response::success(['orders' => $orders, 'pagination' => ['total' => count($orders)]]);
}

function getOrderDetails($customerId, $isStaff = false) {
    $orderId = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$orderId) {
        Response::error('id parameter is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Build query based on user role
    $whereClause = $isStaff ? "WHERE co.id = ?" : "WHERE co.id = ? AND co.customer_id = ?";

    // Get order details with customer info
    $stmt = $db->prepare("
        SELECT
            co.*,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            ca1.first_name as shipping_first_name,
            ca1.last_name as shipping_last_name,
            ca1.address_line_1 as shipping_address_1,
            ca1.address_line_2 as shipping_address_2,
            ca1.city as shipping_city,
            ca1.state as shipping_state,
            ca1.postal_code as shipping_postal_code,
            ca1.country as shipping_country,
            ca1.phone as shipping_phone,
            ca2.first_name as billing_first_name,
            ca2.last_name as billing_last_name,
            ca2.address_line_1 as billing_address_1,
            ca2.address_line_2 as billing_address_2,
            ca2.city as billing_city,
            ca2.state as billing_state,
            ca2.postal_code as billing_postal_code,
            ca2.country as billing_country,
            ca2.phone as billing_phone
        FROM customer_orders co
        INNER JOIN customers c ON co.customer_id = c.id
        LEFT JOIN customer_addresses ca1 ON co.shipping_address_id = ca1.id
        LEFT JOIN customer_addresses ca2 ON co.billing_address_id = ca2.id
        {$whereClause}
    ");

    $params = $isStaff ? [$orderId] : [$orderId, $customerId];
    $stmt->execute($params);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add customer_name field for consistency
    if ($order && $isStaff) {
        $order['customer_name'] = $order['first_name'] . ' ' . $order['last_name'];
    }

    if (!$order) {
        Response::error('Order not found', 404);
    }

    // Get order items
    $itemsStmt = $db->prepare("
        SELECT
            coi.*,
            p.image_url as product_image,
            p.brand,
            p.warranty_months
        FROM customer_order_items coi
        LEFT JOIN products p ON coi.product_id = p.id
        WHERE coi.order_id = ?
        ORDER BY coi.id
    ");
    $itemsStmt->execute([$orderId]);
    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format addresses
    $order['shipping_address'] = [
        'first_name' => $order['shipping_first_name'],
        'last_name' => $order['shipping_last_name'],
        'address_line_1' => $order['shipping_address_1'],
        'address_line_2' => $order['shipping_address_2'],
        'city' => $order['shipping_city'],
        'state' => $order['shipping_state'],
        'postal_code' => $order['shipping_postal_code'],
        'country' => $order['shipping_country'],
        'phone' => $order['shipping_phone']
    ];

    $order['billing_address'] = [
        'first_name' => $order['billing_first_name'],
        'last_name' => $order['billing_last_name'],
        'address_line_1' => $order['billing_address_1'],
        'address_line_2' => $order['billing_address_2'],
        'city' => $order['billing_city'],
        'state' => $order['billing_state'],
        'postal_code' => $order['billing_postal_code'],
        'country' => $order['billing_country'],
        'phone' => $order['billing_phone']
    ];

    // Remove raw address fields
    unset($order['shipping_first_name'], $order['shipping_last_name'], $order['shipping_address_1'],
          $order['shipping_address_2'], $order['shipping_city'], $order['shipping_state'],
          $order['shipping_postal_code'], $order['shipping_country'], $order['shipping_phone'],
          $order['billing_first_name'], $order['billing_last_name'], $order['billing_address_1'],
          $order['billing_address_2'], $order['billing_city'], $order['billing_state'],
          $order['billing_postal_code'], $order['billing_country'], $order['billing_phone']);

    Response::success(['order' => $order]);
}

function createOrder($customerId, $data) {
    $db = Database::getInstance()->getConnection();

    // Start transaction
    $db->beginTransaction();

    try {
        // Get cart items
        $cartStmt = $db->prepare("
            SELECT
                sc.product_id,
                sc.quantity,
                p.name,
                p.sku,
                p.selling_price,
                p.cost_price,
                i.quantity_available
            FROM shopping_cart sc
            JOIN products p ON sc.product_id = p.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE sc.customer_id = ?
        ");
        $cartStmt->execute([$customerId]);
        $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cartItems)) {
            throw new Exception('Cart is empty');
        }

        // Validate stock availability
        foreach ($cartItems as $item) {
            if ($item['quantity_available'] < $item['quantity']) {
                throw new Exception("Insufficient stock for product: {$item['name']}");
            }
        }

        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Calculate totals
        $subtotal = 0;
        $orderItems = [];

        foreach ($cartItems as $item) {
            $itemTotal = $item['selling_price'] * $item['quantity'];
            $subtotal += $itemTotal;

            $orderItems[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['selling_price'],
                'total_price' => $itemTotal
            ];
        }

        $taxRate = 0.12; // 12% VAT
        $taxAmount = $subtotal * $taxRate;
        $shippingAmount = $subtotal > 1000 ? 0 : 150; // Free shipping over ₱1000
        $totalAmount = $subtotal + $taxAmount + $shippingAmount;

        // Create order
        $orderStmt = $db->prepare("
            INSERT INTO customer_orders (
                order_number, customer_id, status, order_date,
                shipping_address_id, billing_address_id,
                subtotal, tax_amount, shipping_amount, total_amount,
                payment_method, created_at
            ) VALUES (?, ?, 'pending', NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $shippingAddressId = $data['shipping_address_id'] ?? null;
        $billingAddressId = $data['billing_address_id'] ?? $shippingAddressId;
        $paymentMethod = $data['payment_method'] ?? 'cash_on_delivery';

        $orderStmt->execute([
            $orderNumber, $customerId, $shippingAddressId, $billingAddressId,
            $subtotal, $taxAmount, $shippingAmount, $totalAmount, $paymentMethod
        ]);

        $orderId = $db->lastInsertId();

        // Insert order items
        $itemStmt = $db->prepare("
            INSERT INTO customer_order_items (
                order_id, product_id, product_name, product_sku,
                quantity, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($orderItems as $item) {
            $itemStmt->execute([
                $orderId, $item['product_id'], $item['product_name'], $item['product_sku'],
                $item['quantity'], $item['unit_price'], $item['total_price']
            ]);
        }

        // Update inventory
        $inventoryStmt = $db->prepare("
            UPDATE inventory
            SET quantity_on_hand = quantity_on_hand - ?
            WHERE product_id = ?
        ");

        foreach ($cartItems as $item) {
            $inventoryStmt->execute([$item['quantity'], $item['product_id']]);
        }

        // Clear cart
        $clearCartStmt = $db->prepare("DELETE FROM shopping_cart WHERE customer_id = ?");
        $clearCartStmt->execute([$customerId]);

        // Log stock movements
        $movementStmt = $db->prepare("
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, quantity_before, quantity_after,
                reference_type, reference_id, performed_by, notes
            ) VALUES (?, 'customer_order', ?, ?, ?, 'CUSTOMER_ORDER', ?, NULL, ?)
        ");

        foreach ($cartItems as $item) {
            $movementStmt->execute([
                $item['product_id'], $item['quantity'],
                $item['quantity_available'], $item['quantity_available'] - $item['quantity'],
                $orderId, "Order {$orderNumber}"
            ]);
        }

        $db->commit();

        // Send order confirmation email
        try {
            require_once __DIR__ . '/../../utils/Email.php';
            require_once __DIR__ . '/../../models/Customer.php';

            $customerModel = new Customer();
            $customer = $customerModel->findById($customerId);

            if ($customer) {
                $email = new Email();
                $orderData = [
                    'order_number' => $orderNumber,
                    'order_date' => date('Y-m-d H:i:s'),
                    'items' => $orderItems,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'shipping_amount' => $shippingAmount,
                    'total_amount' => $totalAmount
                ];

                $email->sendOrderConfirmation($orderData, $customer);
            }
        } catch (Exception $e) {
            // Log email error but don't fail the order
            error_log('Failed to send order confirmation email: ' . $e->getMessage());
        }

        Response::success([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'message' => 'Order created successfully'
        ], 'Order created', 201);

    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to create order: ' . $e->getMessage(), 500);
    }
}

function cancelOrder($customerId, $data) {
    $orderId = $data['order_id'] ?? null;
    if (!$orderId) {
        Response::error('order_id is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Check if order exists and belongs to customer
    $checkStmt = $db->prepare("
        SELECT id, status, order_number FROM customer_orders
        WHERE id = ? AND customer_id = ?
    ");
    $checkStmt->execute([$orderId, $customerId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        Response::error('Order not found', 404);
    }

    // Check if order can be cancelled
    if (!in_array($order['status'], ['pending', 'confirmed'])) {
        Response::error('Order cannot be cancelled at this stage', 400);
    }

    // Start transaction
    $db->beginTransaction();

    try {
        // Update order status
        $updateStmt = $db->prepare("UPDATE customer_orders SET status = 'cancelled' WHERE id = ?");
        $updateStmt->execute([$orderId]);

        // Return items to inventory
        $itemsStmt = $db->prepare("SELECT product_id, quantity FROM customer_order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $inventoryStmt = $db->prepare("
            UPDATE inventory
            SET quantity_on_hand = quantity_on_hand + ?
            WHERE product_id = ?
        ");

        foreach ($orderItems as $item) {
            $inventoryStmt->execute([$item['quantity'], $item['product_id']]);
        }

        // Log stock movements
        $movementStmt = $db->prepare("
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, quantity_before, quantity_after,
                reference_type, reference_id, performed_by, notes
            ) VALUES (?, 'customer_order', ?, ?, ?, 'CUSTOMER_ORDER', ?, NULL, ?)
        ");

        foreach ($orderItems as $item) {
            // Get current stock level
            $stockStmt = $db->prepare("SELECT quantity_on_hand FROM inventory WHERE product_id = ?");
            $stockStmt->execute([$item['product_id']]);
            $currentStock = $stockStmt->fetch(PDO::FETCH_ASSOC)['quantity_on_hand'];

            $movementStmt->execute([
                $item['product_id'], $item['quantity'],
                $currentStock - $item['quantity'], $currentStock,
                $orderId, "Order cancellation {$order['order_number']}"
            ]);
        }

        $db->commit();

        Response::success([
            'message' => 'Order cancelled successfully',
            'order_id' => $orderId
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to cancel order: ' . $e->getMessage(), 500);
    }
}


<?php
/**
 * Shop Checkout and Order Processing API Endpoint
 * POST /backend/api/shop/checkout.php - Process checkout and create orders
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
require_once __DIR__ . '/../../models/Customer.php';

CORS::handle();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$customerModel = new Customer();

// Get customer ID and session ID
$customerId = $_SESSION['customer_id'] ?? null;
$sessionId = $_SESSION['guest_session_id'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    processCheckout($customerModel, $customerId, $sessionId);
} else {
    Response::error('Method not allowed', 405);
}

function processCheckout($customerModel, $customerId, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Log the request for debugging
    error_log('Checkout attempt - Customer ID: ' . ($customerId ?? 'null') . ' | Session ID: ' . ($sessionId ?? 'null'));
    error_log('Checkout input data: ' . json_encode($input));

    // Validate customer authentication (require login for checkout)
    if (!$customerId) {
        error_log('Checkout failed: No customer ID in session');
        Response::error('Customer authentication required for checkout. Please log in to continue.', 401);
    }

    // Get cart items
    $cartItems = $customerModel->getCart($customerId, $sessionId);

    if (empty($cartItems)) {
        error_log('Checkout failed: Empty cart for customer ' . $customerId);
        Response::error('Shopping cart is empty. Please add items to your cart before checkout.', 400);
    }

    // Validate cart items (check stock availability)
    $validationErrors = validateCartItems($cartItems);
    if (!empty($validationErrors)) {
        error_log('Checkout failed: Cart validation errors - ' . implode(', ', $validationErrors));
        Response::error('Cart validation failed: ' . implode(', ', $validationErrors), 400);
    }

    // Validate checkout data
    $validationResult = validateCheckoutData($input);
    if (!$validationResult['valid']) {
        error_log('Checkout failed: Invalid checkout data - ' . implode(', ', $validationResult['errors']));
        Response::error('Validation failed: ' . implode(', ', $validationResult['errors']), 400);
    }

    $checkoutData = $validationResult['data'];

    // Calculate order totals
    $orderTotals = calculateOrderTotals($cartItems, $checkoutData);

    // Begin transaction
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Generate order number
        $orderNumber = $customerModel->generateOrderNumber();

        // Create order
        $orderId = createOrder($db, $customerId, $orderNumber, $checkoutData, $orderTotals);

        // Create order items
        createOrderItems($db, $orderId, $cartItems);

        // Create or update customer addresses
        $shippingAddressId = handleCustomerAddress($db, $customerId, $checkoutData['shipping_address'], 'shipping');
        $billingAddressId = handleCustomerAddress($db, $customerId, $checkoutData['billing_address'], 'billing');

        // Update order with address IDs
        updateOrderAddresses($db, $orderId, $shippingAddressId, $billingAddressId);

        // Process payment using payment gateway
        $paymentResult = processPayment($orderTotals['total_amount'], $checkoutData['payment_method'], $checkoutData);

        if (!$paymentResult['success']) {
            throw new Exception('Payment processing failed: ' . $paymentResult['error']);
        }

        // Update order payment status
        updateOrderPaymentStatus($db, $orderId, 'paid', $checkoutData['payment_method'], $paymentResult);

        // Update inventory (reduce stock, release reservations) and create audit trail
        updateInventoryStock($db, $cartItems, $orderId, $customerId);

        // Release stock reservations (converting to actual sale)
        releaseStockReservations($db, $cartItems);

        // Clear shopping cart
        $customerModel->clearCart($customerId, $sessionId);

        // Commit transaction
        $db->commit();

        // Get complete order details
        $orderDetails = getOrderDetails($db, $orderId);

        // Send order confirmation email (BEFORE response to prevent HTML output corruption)
        $emailSent = false;
        if (ob_get_level()) ob_clean(); // Clear any buffered output

        // Suppress email-related warnings to prevent JSON corruption
        $originalErrorReporting = error_reporting();
        error_reporting($originalErrorReporting & ~E_WARNING);

        try {
            require_once __DIR__ . '/../../utils/Email.php';
            $email = new Email();
            // Get customer data from order details
            $customerData = [
                'first_name' => $orderDetails['first_name'],
                'last_name' => $orderDetails['last_name'],
                'email' => $orderDetails['email']
            ];
            // Send purchase confirmation email to customer
            $emailSent = @$email->sendCustomerPurchaseNotification($customerData, $orderDetails);
        } catch (Exception $e) {
            // Log email error but don't fail the order or corrupt JSON response
            error_log('Failed to send order confirmation email: ' . $e->getMessage());
        } finally {
            // Restore original error reporting
            error_reporting($originalErrorReporting);
        }

        Response::success([
            'message' => 'Order placed successfully',
            'order' => $orderDetails,
            'payment' => $paymentResult
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Checkout error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        throw $e;
    }
}

function validateCartItems($cartItems) {
    $errors = [];

    foreach ($cartItems as $item) {
        if ($item['quantity_available'] <= 0) {
            $errors[] = "{$item['name']} is out of stock";
        } elseif ($item['quantity'] > $item['quantity_available']) {
            $errors[] = "Only {$item['quantity_available']} units of {$item['name']} available";
        }
    }

    return $errors;
}

function validateCheckoutData($input) {
    $errors = [];
    $data = [];

    // Required fields
    $required = ['shipping_address', 'billing_address', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $errors[] = "$field is required";
        }
    }

    // Validate that address fields are arrays
    if (isset($input['shipping_address']) && !is_array($input['shipping_address'])) {
        $errors[] = "shipping_address must be an array";
    }

    if (isset($input['billing_address']) && !is_array($input['billing_address'])) {
        $errors[] = "billing_address must be an array";
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }

    // Validate addresses
    $shippingAddress = validateAddress($input['shipping_address'], 'shipping');
    if (!$shippingAddress['valid']) {
        $errors = array_merge($errors, $shippingAddress['errors']);
    }

    $billingAddress = validateAddress($input['billing_address'], 'billing');
    if (!$billingAddress['valid']) {
        $errors = array_merge($errors, $billingAddress['errors']);
    }

    // Validate payment method
    $validPaymentMethods = ['credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery'];
    if (!in_array($input['payment_method'], $validPaymentMethods)) {
        $errors[] = 'Invalid payment method';
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }

    $data = [
        'shipping_address' => $shippingAddress['address'],
        'billing_address' => $billingAddress['address'],
        'payment_method' => $input['payment_method'],
        'notes' => $input['notes'] ?? null
    ];

    return ['valid' => true, 'data' => $data];
}

function validateAddress($addressData, $type) {
    $errors = [];
    $address = [];

    $required = ['first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country'];
    foreach ($required as $field) {
        if (!isset($addressData[$field]) || empty(trim($addressData[$field]))) {
            $errors[] = "$type address $field is required";
        }
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }

    $address = [
        'address_type' => $type,
        'is_default' => $addressData['is_default'] ?? false,
        'first_name' => trim($addressData['first_name']),
        'last_name' => trim($addressData['last_name']),
        'company' => isset($addressData['company']) ? trim($addressData['company']) : null,
        'address_line_1' => trim($addressData['address_line_1']),
        'address_line_2' => isset($addressData['address_line_2']) ? trim($addressData['address_line_2']) : null,
        'city' => trim($addressData['city']),
        'state' => isset($addressData['state']) ? trim($addressData['state']) : null,
        'postal_code' => trim($addressData['postal_code']),
        'country' => trim($addressData['country']),
        'phone' => isset($addressData['phone']) ? trim($addressData['phone']) : null
    ];

    return ['valid' => true, 'address' => $address];
}

function calculateOrderTotals($cartItems, $checkoutData) {
    $subtotal = 0.00;
    $totalQuantity = 0;

    foreach ($cartItems as $item) {
        $subtotal += $item['quantity'] * $item['selling_price'];
        $totalQuantity += $item['quantity'];
    }

    // Fetch tax rate from settings table, fallback to 0.08 (8%)
    $db = Database::getInstance()->getConnection();
    $taxRateQuery = "SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'";
    $taxRateStmt = $db->prepare($taxRateQuery);
    $taxRateStmt->execute();
    $taxRateResult = $taxRateStmt->fetch(PDO::FETCH_ASSOC);
    $taxRate = $taxRateResult ? (float)$taxRateResult['setting_value'] : 0.08;

    $taxAmount = round($subtotal * $taxRate, 2);

    $shippingAmount = 0.00; // Free shipping for now - could be calculated based on rules

    $discountAmount = 0.00; // No discounts for now - could be applied based on coupons

    $totalAmount = $subtotal + $taxAmount + $shippingAmount - $discountAmount;

    return [
        'subtotal' => round($subtotal, 2),
        'tax_amount' => $taxAmount,
        'tax_rate' => $taxRate,
        'shipping_amount' => $shippingAmount,
        'discount_amount' => $discountAmount,
        'total_amount' => round($totalAmount, 2),
        'total_quantity' => $totalQuantity
    ];
}

function createOrder($db, $customerId, $orderNumber, $checkoutData, $totals) {
    $query = "INSERT INTO customer_orders
              (order_number, customer_id, status, subtotal, tax_amount, shipping_amount,
               discount_amount, total_amount, payment_method, notes)
              VALUES
              (:order_number, :customer_id, 'pending', :subtotal, :tax_amount, :shipping_amount,
               :discount_amount, :total_amount, :payment_method, :notes)";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':order_number', $orderNumber);
    $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->bindValue(':subtotal', $totals['subtotal']);
    $stmt->bindValue(':tax_amount', $totals['tax_amount']);
    $stmt->bindValue(':shipping_amount', $totals['shipping_amount']);
    $stmt->bindValue(':discount_amount', $totals['discount_amount']);
    $stmt->bindValue(':total_amount', $totals['total_amount']);
    $stmt->bindValue(':payment_method', $checkoutData['payment_method']);
    $stmt->bindValue(':notes', $checkoutData['notes']);

    $stmt->execute();

    return $db->lastInsertId();
}

function createOrderItems($db, $orderId, $cartItems) {
    $query = "INSERT INTO customer_order_items
              (order_id, product_id, product_name, product_sku, quantity, unit_price)
              VALUES
              (:order_id, :product_id, :product_name, :product_sku, :quantity, :unit_price)";

    $stmt = $db->prepare($query);

    foreach ($cartItems as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];
        $unitPrice = (float)$item['selling_price'];

        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':product_name', $item['name']);
        $stmt->bindValue(':product_sku', $item['sku']);
        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':unit_price', $unitPrice);

        $stmt->execute();
    }
}

function handleCustomerAddress($db, $customerId, $addressData, $type) {
    // Check if this exact address already exists
    $existingQuery = "SELECT id FROM customer_addresses
                      WHERE customer_id = :customer_id
                        AND address_type = :address_type
                        AND address_line_1 = :address_line_1
                        AND city = :city
                        AND postal_code = :postal_code";

    $stmt = $db->prepare($existingQuery);
    $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->bindValue(':address_type', $type);
    $stmt->bindValue(':address_line_1', $addressData['address_line_1']);
    $stmt->bindValue(':city', $addressData['city']);
    $stmt->bindValue(':postal_code', $addressData['postal_code']);
    $stmt->execute();

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return $existing['id'];
    }

    // Create new address
    $customerModel = new Customer();
    return $customerModel->addAddress($customerId, $addressData);
}

function updateOrderAddresses($db, $orderId, $shippingAddressId, $billingAddressId) {
    $query = "UPDATE customer_orders
              SET shipping_address_id = :shipping_id, billing_address_id = :billing_id
              WHERE id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':shipping_id', $shippingAddressId, PDO::PARAM_INT);
    $stmt->bindValue(':billing_id', $billingAddressId, PDO::PARAM_INT);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

function processPayment($amount, $method, $checkoutData) {
    require_once __DIR__ . '/../../utils/PaymentGateway.php';

    // Get currency from settings
    $db = Database::getInstance()->getConnection();
    $currencyQuery = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
    $currencyStmt = $db->prepare($currencyQuery);
    $currencyStmt->execute();
    $currencyResult = $currencyStmt->fetch(PDO::FETCH_ASSOC);
    $currency = $currencyResult ? $currencyResult['setting_value'] : 'PHP';

    // Initialize payment gateway (default to Stripe, fallback to simulation)
    $gateway = new PaymentGateway('stripe', true); // true for test mode

    // If Stripe is not configured, use a fallback simulation
    if (!$gateway->isConfigured()) {
        return processPaymentFallback($amount, $method, $currency);
    }

    // Prepare payment data based on method
    $paymentData = [];
    if (isset($checkoutData['payment_data'])) {
        $paymentData = $checkoutData['payment_data'];
    }

    // Process payment
    $result = $gateway->processPayment($amount, $currency, $method, $paymentData);

    return $result;
}

function processPaymentFallback($amount, $method, $currency) {
    // Fallback simulation when payment gateway is not configured
    if ($method === 'cash_on_delivery') {
        return [
            'success' => true,
            'message' => 'Payment will be collected on delivery',
            'transaction_id' => null,
            'payment_status' => 'pending',
            'gateway' => 'cash_on_delivery'
        ];
    }

    // Simulate payment gateway processing
    $transactionId = 'TXN_' . time() . '_' . rand(1000, 9999);

    return [
        'success' => true,
        'message' => 'Payment processed successfully',
        'transaction_id' => $transactionId,
        'payment_status' => 'paid',
        'amount' => $amount,
        'currency' => $currency,
        'method' => $method,
        'gateway' => 'simulation'
    ];
}

function updateOrderPaymentStatus($db, $orderId, $status, $method, $paymentResult = null) {
    // Simple update without transaction_id to avoid schema issues
    $query = "UPDATE customer_orders
              SET payment_status = :status, status = 'confirmed'
              WHERE id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

function updateInventoryStock($db, $cartItems, $orderId = null, $customerId = null) {
    // Update inventory quantity
    $query = "UPDATE inventory SET quantity_on_hand = quantity_on_hand - :quantity WHERE product_id = :product_id";
    $stmt = $db->prepare($query);

    foreach ($cartItems as $item) {
        $quantity = (int)$item['quantity'];
        $productId = (int)$item['product_id'];

        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        // Get current inventory levels for audit trail
        $inventoryQuery = "SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id";
        $inventoryStmt = $db->prepare($inventoryQuery);
        $inventoryStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $inventoryStmt->execute();
        $inventoryData = $inventoryStmt->fetch(PDO::FETCH_ASSOC);

        $quantityBefore = $inventoryData ? $inventoryData['quantity_on_hand'] + $quantity : $quantity;
        $quantityAfter = $inventoryData ? $inventoryData['quantity_on_hand'] : 0;

        // Create stock movement record for audit trail
        $movementQuery = "
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, quantity_before, quantity_after,
                reference_type, reference_id, performed_by, notes
            ) VALUES (
                :product_id, 'sale', :quantity_neg, :quantity_before, :quantity_after,
                'CUSTOMER_ORDER', :order_id, :performed_by, :notes
            )
        ";

        $movementStmt = $db->prepare($movementQuery);
        $movementStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_neg', -$quantity, PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_before', $quantityBefore, PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_after', $quantityAfter, PDO::PARAM_INT);
        $movementStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $movementStmt->bindValue(':performed_by', 1, PDO::PARAM_INT); // Use admin user for customer orders
        $notes = 'Customer Order - ' . $item['name'];
        $movementStmt->bindValue(':notes', $notes);
        $movementStmt->execute();
    }
}

function releaseStockReservations($db, $cartItems) {
    $query = "UPDATE inventory
              SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity)
              WHERE product_id = :product_id";

    $stmt = $db->prepare($query);

    foreach ($cartItems as $item) {
        $quantity = (int)$item['quantity'];
        $productId = (int)$item['product_id'];

        $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function getOrderDetails($db, $orderId) {
    $query = "SELECT
                co.*,
                c.first_name, c.last_name, c.email,
                sa.first_name as shipping_first_name, sa.last_name as shipping_last_name,
                sa.address_line_1 as shipping_address_1, sa.address_line_2 as shipping_address_2,
                sa.city as shipping_city, sa.state as shipping_state, sa.postal_code as shipping_postal,
                sa.country as shipping_country,
                ba.first_name as billing_first_name, ba.last_name as billing_last_name,
                ba.address_line_1 as billing_address_1, ba.address_line_2 as billing_address_2,
                ba.city as billing_city, ba.state as billing_state, ba.postal_code as billing_postal,
                ba.country as billing_country
              FROM customer_orders co
              INNER JOIN customers c ON co.customer_id = c.id
              LEFT JOIN customer_addresses sa ON co.shipping_address_id = sa.id
              LEFT JOIN customer_addresses ba ON co.billing_address_id = ba.id
              WHERE co.id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Get order items
        $itemsQuery = "SELECT * FROM customer_order_items WHERE order_id = :order_id ORDER BY id";
        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $itemsStmt->execute();
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $order;
}

<?php
/**
 * Create Sale API Endpoint
 * POST /backend/api/sales/create.php - Create a new sale
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Validate required fields
    $errors = Validator::required($input, ['items', 'payment_method']);

    if ($errors) {
        Response::validationError($errors);
    }

    if (!is_array($input['items']) || empty($input['items'])) {
        Response::error('Items array is required and cannot be empty');
    }

    // Handle POS-specific data structure
    $customerName = $input['customer_name'] ?? 'Walk-in Customer';
    $paymentMethod = $input['payment_method'];
    $paymentDetails = $input['payment_details'] ?? null;

    // Use POS-provided totals if available, otherwise calculate
    $usePOSTotals = isset($input['subtotal']) && isset($input['tax_amount']) && isset($input['total_amount']);

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Generate invoice number
    $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_auto_number'");
    $prefix = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 'INV-2025-';

    $stmt = $conn->query("SELECT COUNT(*) + 1 as next_num FROM sales");
    $nextNum = $stmt->fetch(PDO::FETCH_ASSOC)['next_num'];
    $invoiceNumber = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

    // Process items and calculate/validate totals
    $calculatedSubtotal = 0;
    $items = [];

    foreach ($input['items'] as $item) {
        if (!isset($item['product_id']) || !isset($item['quantity'])) {
            $conn->rollBack();
            Response::error('Each item must have product_id and quantity');
        }

        // Get product details
        $stmt = $conn->prepare("
            SELECT p.*, i.quantity_available
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.id = :product_id AND p.is_active = 1
        ");
        $stmt->execute([':product_id' => $item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $conn->rollBack();
            Response::error('Product not found or inactive: ID ' . $item['product_id']);
        }

        // Check stock
        if (($product['quantity_available'] ?? 0) < $item['quantity']) {
            $conn->rollBack();
            Response::error('Insufficient stock for product: ' . $product['name']);
        }

        // Use POS-provided unit_price if available, otherwise use product selling price
        $unitPrice = $item['unit_price'] ?? $product['selling_price'];
        $totalPrice = $unitPrice * $item['quantity'];
        $calculatedSubtotal += $totalPrice;

        $items[] = [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice
        ];
    }

    // Use POS totals or calculate them
    if ($usePOSTotals) {
        $subtotal = $input['subtotal'];
        $taxAmount = $input['tax_amount'];
        $totalAmount = $input['total_amount'];
        $discountAmount = $input['discount_amount'] ?? 0;
        $taxRate = $taxAmount > 0 ? $taxAmount / $subtotal : 0.12;
    } else {
        // Calculate tax and total
        $taxRate = $input['tax_rate'] ?? 0.12; // Default 12%
        $taxAmount = $calculatedSubtotal * $taxRate;
        $discountAmount = $input['discount_amount'] ?? 0;
        $subtotal = $calculatedSubtotal;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
    }

    // Create sale record
    $saleQuery = "
        INSERT INTO sales (
            invoice_number, cashier_id, customer_name, customer_email, customer_phone,
            subtotal, tax_amount, tax_rate, discount_amount, total_amount,
            payment_method, payment_status, notes
        ) VALUES (
            :invoice_number, :cashier_id, :customer_name, :customer_email, :customer_phone,
            :subtotal, :tax_amount, :tax_rate, :discount_amount, :total_amount,
            :payment_method, :payment_status, :notes
        )
    ";

    $stmt = $conn->prepare($saleQuery);
    $stmt->execute([
        ':invoice_number' => $invoiceNumber,
        ':cashier_id' => $user['id'],
        ':customer_name' => $input['customer_name'] ?? null,
        ':customer_email' => $input['customer_email'] ?? null,
        ':customer_phone' => $input['customer_phone'] ?? null,
        ':subtotal' => $subtotal,
        ':tax_amount' => $taxAmount,
        ':tax_rate' => $taxRate,
        ':discount_amount' => $discountAmount,
        ':total_amount' => $totalAmount,
        ':payment_method' => $input['payment_method'],
        ':payment_status' => 'paid',
        ':notes' => $input['notes'] ?? null
    ]);

    $saleId = $conn->lastInsertId();

    // Create sale items and update inventory
    foreach ($items as $item) {
        // Insert sale item
        $itemQuery = "
            INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
            VALUES (:sale_id, :product_id, :quantity, :unit_price)
        ";

        $stmt = $conn->prepare($itemQuery);
        $stmt->execute([
            ':sale_id' => $saleId,
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price']
        ]);

        // Update inventory
        $invQuery = "
            UPDATE inventory
            SET quantity_on_hand = quantity_on_hand - :quantity
            WHERE product_id = :product_id
        ";

        $stmt = $conn->prepare($invQuery);
        $stmt->execute([
            ':quantity' => $item['quantity'],
            ':product_id' => $item['product_id']
        ]);

        // Create stock movement record
        $movementQuery = "
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, quantity_before, quantity_after,
                reference_type, reference_id, performed_by, notes
            )
            SELECT
                :product_id, 'sale', :quantity_neg,
                i.quantity_on_hand + :quantity,
                i.quantity_on_hand,
                'SALE', :sale_id, :user_id, :notes
            FROM inventory i
            WHERE i.product_id = :product_id2
        ";

        $stmt = $conn->prepare($movementQuery);
        $stmt->execute([
            ':product_id' => $item['product_id'],
            ':product_id2' => $item['product_id'],
            ':quantity_neg' => -$item['quantity'],
            ':quantity' => $item['quantity'],
            ':sale_id' => $saleId,
            ':user_id' => $user['id'],
            ':notes' => 'Sale: ' . $invoiceNumber
        ]);
    }

    $conn->commit();

    // Fetch created sale with details
    $stmt = $conn->prepare("
        SELECT
            s.*,
            u.full_name as cashier_name,
            GROUP_CONCAT(
                CONCAT(p.name, ' x', si.quantity)
                SEPARATOR ', '
            ) as items_summary
        FROM sales s
        INNER JOIN users u ON s.cashier_id = u.id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        WHERE s.id = :sale_id
        GROUP BY s.id
    ");
    $stmt->execute([':sale_id' => $saleId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'sale_id' => $saleId,
        'sale' => $sale
    ], 'Sale created successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Sale creation error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    Response::serverError('Failed to create sale: ' . $e->getMessage());
}

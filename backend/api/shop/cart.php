<?php
/**
 * Shop Shopping Cart API Endpoint
 * GET/POST/PUT/DELETE /backend/api/shop/cart.php - Manage shopping cart
 *
 * Actions:
 * - GET: View cart contents
 * - POST: Add item to cart
 * - PUT: Update cart item quantity
 * - DELETE: Remove item from cart or clear cart
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

// Start session for cart management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$customerModel = new Customer();

// Get customer ID and session ID
$customerId = $_SESSION['customer_id'] ?? null;
$sessionId = $_SESSION['guest_session_id'] ?? null;

// Generate guest session ID if not logged in and no existing session
if (!$customerId && !$sessionId) {
    $sessionId = session_id() . '_guest_' . time();
    $_SESSION['guest_session_id'] = $sessionId;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getCart($customerModel, $customerId, $sessionId);
            break;

        case 'POST':
            addToCart($customerModel, $customerId, $sessionId);
            break;

        case 'PUT':
            updateCartItem($customerModel, $customerId, $sessionId);
            break;

        case 'DELETE':
            removeFromCart($customerModel, $customerId, $sessionId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Cart operation failed: ' . $e->getMessage());
}

function getCart($customerModel, $customerId, $sessionId) {
    $cartItems = $customerModel->getCart($customerId, $sessionId);

    // Calculate cart totals
    $totals = calculateCartTotals($cartItems);

    // Format cart items
    $formattedItems = array_map(function($item) {
        return [
            'id' => $item['id'],
            'product_id' => $item['product_id'],
            'product' => [
                'name' => $item['name'],
                'image_url' => $item['image_url']
            ],
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['selling_price'],
            'total_price' => (float)($item['quantity'] * $item['selling_price']),
            'available_stock' => (int)$item['quantity_available'],
            'in_stock' => $item['quantity_available'] > 0,
            'sufficient_stock' => $item['quantity_available'] >= $item['quantity']
        ];
    }, $cartItems);

    Response::success([
        'items' => $formattedItems,
        'totals' => $totals,
        'item_count' => count($formattedItems),
        'customer_id' => $customerId,
        'session_id' => $sessionId
    ]);
}

function addToCart($customerModel, $customerId, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (!isset($input['product_id']) || !isset($input['quantity'])) {
        Response::error('product_id and quantity are required', 400);
    }

    $productId = (int)$input['product_id'];
    $quantity = (int)$input['quantity'];

    // Validate quantity
    if ($quantity <= 0) {
        Response::error('Quantity must be greater than 0', 400);
    }

    if ($quantity > 99) {
        Response::error('Maximum quantity per item is 99', 400);
    }

    // Check if product exists and is active
    $db = Database::getInstance()->getConnection();
    $productQuery = "SELECT p.id, p.name, p.selling_price, i.quantity_available
                     FROM products p
                     LEFT JOIN inventory i ON p.id = i.product_id
                     WHERE p.id = :product_id AND p.is_active = 1 AND p.deleted_at IS NULL";

    $stmt = $db->prepare($productQuery);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        Response::error('Product not found or not available', 404);
    }

    // Check stock availability
    if ($product['quantity_available'] <= 0) {
        Response::error('Product is out of stock', 400);
    }

    // Check if adding this quantity would exceed available stock
    $currentCartQuantity = 0;
    if ($customerId) {
        $cartQuery = "SELECT quantity FROM shopping_cart WHERE customer_id = :customer_id AND product_id = :product_id";
        $cartStmt = $db->prepare($cartQuery);
        $cartStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $cartStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $cartStmt->execute();
        $cartItem = $cartStmt->fetch(PDO::FETCH_ASSOC);
        $currentCartQuantity = $cartItem ? $cartItem['quantity'] : 0;
    } elseif ($sessionId) {
        $cartQuery = "SELECT quantity FROM shopping_cart WHERE session_id = :session_id AND product_id = :product_id";
        $cartStmt = $db->prepare($cartQuery);
        $cartStmt->bindParam(':session_id', $sessionId);
        $cartStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $cartStmt->execute();
        $cartItem = $cartStmt->fetch(PDO::FETCH_ASSOC);
        $currentCartQuantity = $cartItem ? $cartItem['quantity'] : 0;
    }

    $newTotalQuantity = $currentCartQuantity + $quantity;
    if ($newTotalQuantity > $product['quantity_available']) {
        Response::error("Only {$product['quantity_available']} items available in stock", 400);
    }

    // Add to cart (no inventory reservation on cart add)
    $success = $customerModel->addToCart($customerId, $sessionId, $productId, $quantity);

    if (!$success) {
        Response::error('Failed to add item to cart', 500);
    }

    // Get updated cart
    $cartItems = $customerModel->getCart($customerId, $sessionId);
    $totals = calculateCartTotals($cartItems);

    Response::success([
        'message' => 'Item added to cart successfully',
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'quantity_added' => $quantity
        ],
        'cart_totals' => $totals,
        'cart_item_count' => count($cartItems)
    ], 201);
}

function updateCartItem($customerModel, $customerId, $sessionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (!isset($input['product_id']) || !isset($input['quantity'])) {
        Response::error('product_id and quantity are required', 400);
    }

    $productId = (int)$input['product_id'];
    $quantity = (int)$input['quantity'];

    // Validate quantity
    if ($quantity < 0) {
        Response::error('Quantity cannot be negative', 400);
    }

    if ($quantity > 99) {
        Response::error('Maximum quantity per item is 99', 400);
    }

    // If quantity is 0, remove the item
    if ($quantity === 0) {
        return removeFromCart($customerModel, $customerId, $sessionId);
    }

    // Check stock availability
    $db = Database::getInstance()->getConnection();
    $productQuery = "SELECT i.quantity_available FROM inventory i WHERE i.product_id = :product_id";
    $stmt = $db->prepare($productQuery);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inventory && $quantity > $inventory['quantity_available']) {
        Response::error("Only {$inventory['quantity_available']} items available in stock", 400);
    }

    // Update cart item (no inventory reservation on cart update)
    $success = $customerModel->updateCartItem($customerId, $sessionId, $productId, $quantity);

    if (!$success) {
        Response::error('Failed to update cart item', 500);
    }

    // Get updated cart
    $cartItems = $customerModel->getCart($customerId, $sessionId);
    $totals = calculateCartTotals($cartItems);

    Response::success([
        'message' => 'Cart item updated successfully',
        'product_id' => $productId,
        'new_quantity' => $quantity,
        'cart_totals' => $totals,
        'cart_item_count' => count($cartItems)
    ]);
}

function removeFromCart($customerModel, $customerId, $sessionId) {
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $clearAll = isset($_GET['clear']) && $_GET['clear'] === 'true';

    if ($clearAll) {
        // Clear entire cart (no inventory reservation on cart clear)
        $success = $customerModel->clearCart($customerId, $sessionId);

        if (!$success) {
            Response::error('Failed to clear cart', 500);
        }

        Response::success([
            'message' => 'Cart cleared successfully',
            'cart_totals' => [
                'item_count' => 0,
                'total_quantity' => 0,
                'subtotal' => 0.00,
                'tax_amount' => 0.00,
                'total_amount' => 0.00
            ],
            'cart_item_count' => 0
        ]);
    } else {
        // Remove specific item
        if (!$productId) {
            Response::error('product_id is required to remove item', 400);
        }

        // Remove from cart (no inventory reservation on cart remove)
        $success = $customerModel->removeFromCart($customerId, $sessionId, $productId);

        if (!$success) {
            Response::error('Failed to remove item from cart', 500);
        }

        // Get updated cart
        $cartItems = $customerModel->getCart($customerId, $sessionId);
        $totals = calculateCartTotals($cartItems);

        Response::success([
            'message' => 'Item removed from cart successfully',
            'product_id' => $productId,
            'cart_totals' => $totals,
            'cart_item_count' => count($cartItems)
        ]);
    }
}

function calculateCartTotals($cartItems) {
    $itemCount = count($cartItems);
    $totalQuantity = 0;
    $subtotal = 0.00;

    // Fetch tax rate from settings table, fallback to 0.08 (8%)
    $db = Database::getInstance()->getConnection();
    $taxRateQuery = "SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'";
    $taxRateStmt = $db->prepare($taxRateQuery);
    $taxRateStmt->execute();
    $taxRateResult = $taxRateStmt->fetch(PDO::FETCH_ASSOC);
    $taxRate = $taxRateResult ? (float)$taxRateResult['setting_value'] : 0.08;

    foreach ($cartItems as $item) {
        $totalQuantity += $item['quantity'];
        $subtotal += $item['quantity'] * $item['selling_price'];
    }

    $taxAmount = round($subtotal * $taxRate, 2);
    $totalAmount = $subtotal + $taxAmount;

    return [
        'item_count' => $itemCount,
        'total_quantity' => $totalQuantity,
        'subtotal' => round($subtotal, 2),
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total_amount' => round($totalAmount, 2)
    ];
}


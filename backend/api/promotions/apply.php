<?php
/**
 * Promotion Apply API Endpoint
 * POST /backend/api/promotions/apply.php - Validate and apply promotion code
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

CORS::handle();

// Start session for customer tracking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        applyPromotion();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Promotion application failed: ' . $e->getMessage());
}

function applyPromotion() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (!isset($input['code']) || empty($input['code'])) {
        Response::error('Promotion code is required', 400);
    }

    if (!isset($input['order_amount']) || $input['order_amount'] <= 0) {
        Response::error('Order amount is required', 400);
    }

    $code = strtoupper(trim($input['code']));
    $orderAmount = (float)$input['order_amount'];
    $cartItems = $input['items'] ?? [];
    $customerId = $_SESSION['customer_id'] ?? null;

    $db = Database::getInstance()->getConnection();

    // Find promotion by code
    $query = "SELECT * FROM promotions
              WHERE code = :code
              AND is_active = 1
              AND start_date <= NOW()
              AND end_date >= NOW()";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':code', $code);
    $stmt->execute();

    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        Response::error('Invalid or expired promotion code', 400);
    }

    // Check usage limit
    if ($promotion['usage_limit'] > 0 && $promotion['usage_count'] >= $promotion['usage_limit']) {
        Response::error('This promotion has reached its usage limit', 400);
    }

    // Check minimum order amount
    if ($promotion['minimum_order_amount'] && $orderAmount < $promotion['minimum_order_amount']) {
        Response::error("Minimum order amount of $" . number_format($promotion['minimum_order_amount'], 2) . " required", 400);
    }

    // Check customer usage limit (once per customer for WELCOME10, etc.)
    if ($customerId && strpos($code, 'WELCOME') !== false) {
        $usageQuery = "SELECT COUNT(*) as usage_count
                       FROM promotion_usage
                       WHERE promotion_id = :promotion_id
                       AND customer_id = :customer_id";

        $usageStmt = $db->prepare($usageQuery);
        $usageStmt->bindParam(':promotion_id', $promotion['id'], PDO::PARAM_INT);
        $usageStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $usageStmt->execute();

        $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);

        if ($usage['usage_count'] > 0) {
            Response::error('You have already used this promotion code', 400);
        }
    }

    // Check applicable products/categories
    $applicableItems = filterApplicableItems($cartItems, $promotion);

    if (empty($applicableItems) && !empty($cartItems)) {
        Response::error('This promotion is not applicable to any items in your cart', 400);
    }

    // Calculate discount
    $discount = calculateDiscount($promotion, $orderAmount, $applicableItems);

    // Prepare response
    $response = [
        'valid' => true,
        'promotion' => [
            'id' => $promotion['id'],
            'code' => $promotion['code'],
            'name' => $promotion['name'],
            'description' => $promotion['description'],
            'discount_type' => $promotion['discount_type']
        ],
        'discount_amount' => round($discount['amount'], 2),
        'discount_type' => $promotion['discount_type'],
        'original_amount' => $orderAmount,
        'final_amount' => round($orderAmount - $discount['amount'], 2),
        'applicable_items' => count($applicableItems),
        'free_shipping' => $discount['free_shipping']
    ];

    Response::success($response);
}

function filterApplicableItems($cartItems, $promotion) {
    // If no restrictions, all items are applicable
    if (empty($promotion['applicable_products']) && empty($promotion['applicable_categories'])) {
        return $cartItems;
    }

    $applicableItems = [];

    // Decode JSON restrictions
    $applicableProducts = $promotion['applicable_products'] ? json_decode($promotion['applicable_products'], true) : [];
    $applicableCategories = $promotion['applicable_categories'] ? json_decode($promotion['applicable_categories'], true) : [];

    foreach ($cartItems as $item) {
        $productId = $item['product_id'] ?? null;
        $categoryId = $item['category_id'] ?? null;

        // Check if product or category is in applicable list
        if ((!empty($applicableProducts) && in_array($productId, $applicableProducts)) ||
            (!empty($applicableCategories) && in_array($categoryId, $applicableCategories))) {
            $applicableItems[] = $item;
        }
    }

    return $applicableItems;
}

function calculateDiscount($promotion, $orderAmount, $applicableItems) {
    $discountAmount = 0.00;
    $freeShipping = false;

    switch ($promotion['discount_type']) {
        case 'percentage':
            // Calculate percentage of applicable items only
            if (!empty($applicableItems)) {
                $applicableTotal = array_sum(array_map(function($item) {
                    return ($item['quantity'] ?? 1) * ($item['unit_price'] ?? $item['selling_price'] ?? 0);
                }, $applicableItems));
                $discountAmount = $applicableTotal * ($promotion['discount_value'] / 100);
            } else {
                // Apply to entire order if no item restrictions
                $discountAmount = $orderAmount * ($promotion['discount_value'] / 100);
            }
            break;

        case 'fixed':
            $discountAmount = $promotion['discount_value'];
            // Don't let discount exceed order amount
            if ($discountAmount > $orderAmount) {
                $discountAmount = $orderAmount;
            }
            break;

        case 'free_shipping':
            $discountAmount = 0.00; // Shipping amount should be handled separately in checkout
            $freeShipping = true;
            break;
    }

    return [
        'amount' => $discountAmount,
        'free_shipping' => $freeShipping
    ];
}

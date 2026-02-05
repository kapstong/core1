<?php
/**
 * Customer Wishlist API Endpoint
 * POST /backend/api/shop/wishlist.php - Add/remove from wishlist
 * GET /backend/api/shop/wishlist.php - Get customer's wishlist
 *
 * Actions:
 * - add: Add product to wishlist
 * - remove: Remove product from wishlist
 * - list: Get customer's wishlist
 * - check: Check if product is in wishlist
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
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
        case 'POST':
            handlePostRequest($action);
            break;

        case 'GET':
            handleGetRequest($action);
            break;

        case 'DELETE':
            handleDeleteRequest($action);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Wishlist error: ' . $e->getMessage());
}

function handlePostRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    switch ($action) {
        case 'add':
            addToWishlist($input);
            break;

        case 'remove':
            removeFromWishlist($input);
            break;

        default:
            Response::error('Invalid action. Supported actions: add, remove', 400);
    }
}

function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            getWishlist();
            break;

        case 'check':
            checkWishlistStatus();
            break;

        default:
            Response::error('Invalid action. Supported actions: list, check', 400);
    }
}

function handleDeleteRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    switch ($action) {
        case 'remove':
            removeFromWishlist($input);
            break;

        default:
            Response::error('Invalid action. Supported actions: remove', 400);
    }
}

function addToWishlist($data) {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];

    // Validate required fields
    if (empty($data['product_id'])) {
        Response::error('product_id is required', 400);
    }

    $productId = intval($data['product_id']);

    // Check if product exists
    $productModel = new Product();
    $product = $productModel->findById($productId);
    if (!$product) {
        Response::error('Product not found', 404);
    }

    $db = Database::getInstance()->getConnection();

    // Check if already in wishlist
    $stmt = $db->prepare("SELECT id FROM customer_wishlists WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$customerId, $productId]);
    if ($stmt->fetch()) {
        Response::error('Product is already in your wishlist', 409);
    }

    // Add to wishlist
    $stmt = $db->prepare("INSERT INTO customer_wishlists (customer_id, product_id, added_at) VALUES (?, ?, NOW())");
    if (!$stmt->execute([$customerId, $productId])) {
        Response::error('Failed to add product to wishlist', 500);
    }

    Response::success([
        'message' => 'Product added to wishlist',
        'product_id' => $productId
    ]);
}

function removeFromWishlist($data) {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];

    // Validate required fields
    if (empty($data['product_id'])) {
        Response::error('product_id is required', 400);
    }

    $productId = intval($data['product_id']);

    $db = Database::getInstance()->getConnection();

    // Remove from wishlist
    $stmt = $db->prepare("DELETE FROM customer_wishlists WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$customerId, $productId]);

    if ($stmt->rowCount() === 0) {
        Response::error('Product was not in your wishlist', 404);
    }

    Response::success([
        'message' => 'Product removed from wishlist',
        'product_id' => $productId
    ]);
}

function getWishlist() {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $db = Database::getInstance()->getConnection();

    // Get wishlist items with product details
    $stmt = $db->prepare("
        SELECT
            cw.id as wishlist_id,
            cw.added_at,
            p.id,
            p.sku,
            p.name,
            p.selling_price,
            p.image_url,
            p.brand,
            c.name as category_name,
            i.quantity_available
        FROM customer_wishlists cw
        JOIN products p ON cw.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE cw.customer_id = ? AND p.deleted_at IS NULL
        ORDER BY cw.added_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$customerId, $limit, $offset]);
    $wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM customer_wishlists WHERE customer_id = ?");
    $countStmt->execute([$customerId]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format items
    foreach ($wishlistItems as &$item) {
        $item['price_formatted'] = '₱' . number_format($item['selling_price'], 2);
        $item['added_at_formatted'] = date('M j, Y', strtotime($item['added_at']));
        $item['in_stock'] = ($item['quantity_available'] ?? 0) > 0;
    }

    Response::success([
        'wishlist' => $wishlistItems,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function checkWishlistStatus() {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::success(['in_wishlist' => false]);
        return;
    }

    $customerId = $_SESSION['customer_id'];

    $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    if (!$productId) {
        Response::error('product_id parameter is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Check if product is in wishlist
    $stmt = $db->prepare("SELECT id FROM customer_wishlists WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$customerId, $productId]);
    $exists = $stmt->fetch();

    Response::success([
        'in_wishlist' => $exists ? true : false,
        'product_id' => $productId
    ]);
}


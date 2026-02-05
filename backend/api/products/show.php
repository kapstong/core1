<?php
/**
 * Product Detail API Endpoint
 * GET /backend/api/products/show.php?id=1
 */

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    if (!isset($_GET['id'])) {
        Response::error('Product ID is required');
    }

    $productId = intval($_GET['id']);

    $productModel = new Product();
    $product = $productModel->findById($productId);

    if (!$product) {
        Response::notFound('Product not found');
    }

    // Parse JSON specifications
    if ($product['specifications']) {
        $product['specifications'] = json_decode($product['specifications'], true);
    }

    // Add stock information for display
    $product['stock_quantity'] = (int)($product['quantity_on_hand'] ?? 0);
    $product['quantity_on_hand'] = (int)($product['quantity_on_hand'] ?? 0);

    // Use product-specific reorder level as low stock threshold for display
    // This ensures the badge color logic and display values make sense
    $product['low_stock_threshold'] = (int)($product['reorder_level'] ?? 0);

    // Product's reorder point (same as threshold in this implementation)
    $product['reorder_point'] = (int)($product['reorder_level'] ?? 0);

    // Determine if low stock alert should be triggered
    $currentStock = (int)($product['quantity_on_hand'] ?? 0);
    $reorderLevel = (int)$product['reorder_level'];
    $product['low_stock_alert'] = $currentStock <= $reorderLevel ? 'Yes' : 'No';

    Response::success($product, 'Product retrieved successfully');

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching product');
}


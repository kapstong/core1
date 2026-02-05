<?php
/**
 * Update Product API Endpoint
 * PUT /backend/api/products/update.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/Category.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer']);

    // Handle both JSON and FormData (for file uploads)
    if (!empty($_POST)) {
        // FormData submission (with file upload)
        $input = $_POST;
    } else {
        // JSON submission
        $input = json_decode(file_get_contents('php://input'), true);
    }

    if ($input === null || !isset($input['id'])) {
        Response::error('Invalid input or missing product ID');
    }

    $productId = intval($input['id']);
    $productModel = new Product();

    // Check if product exists
    $existing = $productModel->findById($productId);
    if (!$existing) {
        Response::notFound('Product not found');
    }

    // If category is being updated, validate it exists
    if (isset($input['category_id'])) {
        $categoryModel = new Category();
        $category = $categoryModel->findById($input['category_id']);
        if (!$category) {
            Response::error('Selected category does not exist', 400);
        }
    }

    // If SKU is being updated, check for duplicates
    if (isset($input['sku']) && $input['sku'] !== $existing['sku']) {
        if ($productModel->skuExists($input['sku'], $productId)) {
            Response::error('SKU already exists', 409);
        }
    }

    // If name is being updated, check for duplicates in same category
    if (isset($input['name']) && $input['name'] !== $existing['name']) {
        $categoryId = $input['category_id'] ?? $existing['category_id'];
        $duplicate = $productModel->findByName($input['name'], $categoryId);
        if ($duplicate && $duplicate['id'] != $productId) {
            Response::error('A product with this name already exists in the selected category', 409);
        }
    }

    // Update product
    $updated = $productModel->update($productId, $input);

    if (!$updated) {
        Response::serverError('Failed to update product');
    }

    // Get updated product
    $product = $productModel->findById($productId);

    // Log product update to audit logs
    AuditLogger::logUpdate('product', $productId, "Product '{$product['sku']}' updated", $existing, $product);

    Response::success($product, 'Product updated successfully');

} catch (Exception $e) {
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('An error occurred while updating product');
}


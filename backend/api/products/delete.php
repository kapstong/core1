<?php
/**
 * Delete Product API Endpoint
 * DELETE /backend/api/products/delete.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    Auth::requireRole(['admin', 'inventory_manager']);

    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null || !isset($input['id'])) {
        Response::error('Invalid input or missing product ID');
    }

    $productId = intval($input['id']);
    $productModel = new Product();

    $existing = $productModel->findById($productId);
    if (!$existing) {
        Response::notFound('Product not found');
    }

    $deleted = $productModel->delete($productId);

    if (!$deleted) {
        Response::serverError('Failed to delete product');
    }

    // Log product deletion to audit logs
    AuditLogger::logDelete('product', $productId, "Product '{$existing['sku']}' deleted", [
        'sku' => $existing['sku'],
        'name' => $existing['name'],
        'category_id' => $existing['category_id'],
        'cost_price' => $existing['cost_price'],
        'selling_price' => $existing['selling_price'],
        'deleted_by' => Auth::user()['full_name'] ?? 'Unknown'
    ]);

    Response::success(null, 'Product deleted successfully');

} catch (Exception $e) {
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('An error occurred while deleting product');
}

<?php
/**
 * Create Product API Endpoint
 * POST /backend/api/products/create.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/Category.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Require authentication and role
    Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer']);

    // Handle both JSON and FormData (for file uploads)
    if (!empty($_POST)) {
        // FormData submission (with file upload)
        $input = $_POST;
    } else {
        // JSON submission
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            Response::error('Invalid input data');
        }
    }

    // Validate required fields
    $required = ['sku', 'name', 'category_id', 'cost_price', 'selling_price'];
    $errors = Validator::required($input, $required);

    if ($errors) {
        Response::validationError($errors);
    }

    // Additional validation
    $validationErrors = [];

    if (!Validator::positive($input['cost_price'])) {
        $validationErrors['cost_price'] = 'Cost price must be a positive number';
    }

    if (!Validator::positive($input['selling_price'])) {
        $validationErrors['selling_price'] = 'Selling price must be a positive number';
    }

    if (!empty($validationErrors)) {
        Response::validationError($validationErrors);
    }

    // Validate category exists
    $categoryModel = new Category();
    $category = $categoryModel->findById($input['category_id']);
    if (!$category) {
        Response::error('Selected category does not exist', 400);
    }

    $productModel = new Product();

    // Check if SKU already exists
    if ($productModel->skuExists($input['sku'])) {
        Response::error('SKU already exists', 409);
    }

    // Check if product name already exists in the same category
    $existingProduct = $productModel->findByName($input['name'], $input['category_id']);
    if ($existingProduct) {
        Response::error('A product with this name already exists in the selected category', 409);
    }

    // Create product
    $product = $productModel->create($input);

    if (!$product) {
        error_log('Product creation failed for SKU: ' . $input['sku'] . ' | Name: ' . $input['name']);
        Response::serverError('Failed to create product');
    }

    // Log product creation to audit logs
    AuditLogger::logCreate('product', $product['id'], "Product '{$product['name']}' created", [
        'sku' => $product['sku'],
        'name' => $product['name'],
        'category_id' => $product['category_id'],
        'cost_price' => $product['cost_price'],
        'selling_price' => $product['selling_price']
    ]);

    Response::success($product, 'Product created successfully', 201);

} catch (Exception $e) {
    error_log('Product creation exception: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('An error occurred while creating product');
}


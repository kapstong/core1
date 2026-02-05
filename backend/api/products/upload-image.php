<?php
/**
 * Product Image Upload API Endpoint
 * POST /backend/api/products/upload-image.php - Upload product image
 * DELETE /backend/api/products/upload-image.php?product_id={id} - Delete product image
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/ImageUpload.php';
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

// Check if user has permission to upload images (admin or inventory_manager)
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            uploadProductImage();
            break;

        case 'DELETE':
            deleteProductImage();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Image operation failed: ' . $e->getMessage());
}

function uploadProductImage() {
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $tempUpload = isset($_POST['temp_upload']) && $_POST['temp_upload'] === 'true';

    // If not a temp upload, product_id is required
    if (!$tempUpload && !$productId) {
        Response::error('Product ID is required', 400);
    }

    // If product_id provided, check if product exists
    if ($productId) {
        $db = Database::getInstance()->getConnection();
        $productQuery = "SELECT id, name FROM products WHERE id = :id AND is_active = 1";
        $stmt = $db->prepare($productQuery);
        $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            Response::error('Product not found or not active', 404);
        }
    }

    // Check if image file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        Response::error('No image file uploaded', 400);
    }

    $file = $_FILES['image'];

    // Initialize image upload utility
    $imageUpload = new ImageUpload();

    // Upload and process image
    $result = $imageUpload->uploadProductImage($file, $productId);

    if (!$result['success']) {
        Response::error($result['error'], 400);
    }

    // Update product with image URL only if not a temp upload
    if ($productId && !$tempUpload) {
        $updateQuery = "UPDATE products SET image_url = :image_url, updated_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':image_url', $result['url']);
        $updateStmt->bindParam(':id', $productId, PDO::PARAM_INT);
        $updateStmt->execute();
    }

    Response::success([
        'message' => $tempUpload ? 'Image uploaded temporarily' : 'Product image uploaded successfully',
        'product_id' => $productId,
        'temp_upload' => $tempUpload,
        'image' => [
            'filename' => $result['filename'],
            'url' => $result['url'],
            'thumbnail_url' => $result['thumbnail_url'],
            'width' => $result['width'],
            'height' => $result['height'],
            'size' => $result['size']
        ]
    ], 201);
}

function deleteProductImage() {
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

    if (!$productId) {
        Response::error('Product ID is required', 400);
    }

    // Get current image URL
    $db = Database::getInstance()->getConnection();
    $productQuery = "SELECT id, name, image_url FROM products WHERE id = :id AND is_active = 1";
    $stmt = $db->prepare($productQuery);
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        Response::error('Product not found or not active', 404);
    }

    if (empty($product['image_url'])) {
        Response::error('Product has no image to delete', 400);
    }

    // Extract filename from URL
    $filename = basename($product['image_url']);

    // Delete image files
    $imageUpload = new ImageUpload();
    $deleted = $imageUpload->deleteProductImage($filename);

    // Update product to remove image URL
    $updateQuery = "UPDATE products SET image_url = NULL, updated_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $updateStmt->execute();

    Response::success([
        'message' => 'Product image deleted successfully',
        'product_id' => $productId,
        'deleted_files' => $deleted
    ]);
}


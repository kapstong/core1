<?php
/**
 * Delete Category API Endpoint
 * DELETE /backend/api/categories/delete.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../models/Category.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

CORS::handle();
Auth::requireRole(['admin', 'inventory_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error('Method not allowed', 405);
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id'])) {
        Response::error('Invalid JSON data or missing category ID', 400);
    }

    $categoryId = (int)$data['id'];
    $categoryModel = new Category();

    // Check if category exists
    $existingCategory = $categoryModel->findById($categoryId);
    if (!$existingCategory) {
        Response::error('Category not found', 404);
    }

    // Check if category has associated products (soft delete consideration)
    // For now, we'll allow deletion but in a real system you might want to check for dependencies

    $result = $categoryModel->delete($categoryId);

    if ($result) {
        AuditLogger::logDelete('category', $categoryId, "Category '{$existingCategory['name']}' soft-deleted", [
            'name' => $existingCategory['name'],
            'slug' => $existingCategory['slug'],
            'deleted_by' => Auth::user()['full_name'] ?? 'Unknown'
        ]);
        Response::success(null, 'Category deleted successfully');
    } else {
        Response::error('Failed to delete category', 500);
    }

} catch (Exception $e) {
    Response::serverError('An error occurred while deleting the category');
}


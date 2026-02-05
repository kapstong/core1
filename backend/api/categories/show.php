<?php
/**
 * Show Category API Endpoint
 * GET /backend/api/categories/show.php?id={id}
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Category.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        Response::error('Category ID is required', 400);
    }

    $categoryId = (int)$_GET['id'];
    $categoryModel = new Category();

    $category = $categoryModel->findById($categoryId);

    if ($category) {
        Response::success($category, 'Category retrieved successfully');
    } else {
        Response::error('Category not found', 404);
    }

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching the category');
}


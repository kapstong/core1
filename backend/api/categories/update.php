<?php
/**
 * Update Category API Endpoint
 * PUT /backend/api/categories/update.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
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

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET request - fetch category for editing
if ($method === 'GET') {
    if (!isset($_GET['id'])) {
        Response::error('Category ID is required', 400);
    }

    $categoryId = (int)$_GET['id'];
    $categoryModel = new Category();
    $category = $categoryModel->findById($categoryId);

    if (!$category) {
        Response::error('Category not found', 404);
    }

    Response::success($category, 'Category retrieved successfully');
    exit; // Stop execution after sending response
}

// Handle PUT/POST request - update category
if ($method !== 'PUT' && $method !== 'POST') {
    Response::error('Method not allowed. Use GET to retrieve or PUT/POST to update', 405);
}

try {
    // Accept data from either JSON body or POST parameters
    $data = json_decode(file_get_contents('php://input'), true);

    // If JSON decode failed or empty, try $_POST
    if (!$data) {
        $data = $_POST;
    }

    // Also check for ID in URL parameters if not in body
    if (!isset($data['id']) && isset($_GET['id'])) {
        $data['id'] = $_GET['id'];
    }

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

    // Validate slug if provided
    if (isset($data['slug'])) {
        $slug = trim($data['slug']);
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Response::error('Slug must contain only lowercase letters, numbers, and hyphens', 400);
        }

        // Check if slug is taken by another category
        $slugCheck = $categoryModel->findBySlug($slug);
        if ($slugCheck && $slugCheck['id'] != $categoryId) {
            Response::error('Category with this slug already exists', 400);
        }
    }

    $updateData = [];

    // Only include fields that are provided
    $allowedFields = ['name', 'slug', 'description', 'icon', 'is_active', 'sort_order'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'sort_order') {
                $updateData[$field] = (int)$data[$field];
            } elseif ($field === 'is_active') {
                $updateData[$field] = (int)$data[$field];
            } else {
                $updateData[$field] = trim($data[$field]);
            }
        }
    }

    if (empty($updateData)) {
        Response::error('No valid fields to update', 400);
    }

    $result = $categoryModel->update($categoryId, $updateData);

    if ($result) {
        // Return updated category
        $updatedCategory = $categoryModel->findById($categoryId);
        Response::success($updatedCategory, 'Category updated successfully');
    } else {
        Response::error('Failed to update category', 500);
    }

} catch (Exception $e) {
    Response::serverError('An error occurred while updating the category');
}

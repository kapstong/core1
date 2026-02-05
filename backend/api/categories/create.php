<?php
/**
 * Create Category API Endpoint
 * POST /backend/api/categories/create.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    if (!is_array($data) || empty($data)) {
        Response::error('Invalid JSON data', 400);
    }

    // Validate required fields
    $requiredFields = ['name', 'slug'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            Response::error("Field '$field' is required", 400);
        }
    }

    // Validate slug format (lowercase, no spaces, only alphanumeric and hyphens)
    $slug = trim($data['slug']);
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        Response::error('Slug must contain only lowercase letters, numbers, and hyphens', 400);
    }

    $categoryModel = new Category();

    // Check if slug already exists
    $existing = $categoryModel->findBySlug($slug);
    if ($existing) {
        Response::error('Category with this slug already exists', 400);
    }

    $categoryData = [
        'name' => trim($data['name']),
        'slug' => $slug,
        'description' => isset($data['description']) ? trim($data['description']) : '',
        'icon' => isset($data['icon']) ? trim($data['icon']) : '',
        'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
        'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
    ];

    $newCategory = $categoryModel->create($categoryData);

    if ($newCategory) {
        // Log category creation to audit logs
        AuditLogger::logCreate('category', $newCategory['id'], "Category '{$newCategory['name']}' created", [
            'name' => $newCategory['name'],
            'slug' => $newCategory['slug'],
            'description' => $newCategory['description'],
            'icon' => $newCategory['icon'],
            'sort_order' => $newCategory['sort_order']
        ]);

        Response::success($newCategory, 'Category created successfully', 201);
    } else {
        Response::error('Failed to create category', 500);
    }

} catch (Exception $e) {
    Response::serverError('An error occurred while creating the category');
}

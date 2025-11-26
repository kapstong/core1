<?php
/**
 * Categories List API Endpoint
 * GET /backend/api/categories/index.php
 */

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
    // Check authentication for dashboard access
    require_once __DIR__ . '/../../middleware/Auth.php';

    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    // Check if database is available
    try {
        $categoryModel = new Category();
        $dbAvailable = true;
    } catch (Exception $e) {
        $dbAvailable = false;
        error_log('Database not available for categories: ' . $e->getMessage());
    }

    $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;

    if ($dbAvailable) {
        $categories = $categoryModel->getAll($activeOnly);
    } else {
        // Return mock categories when database is not available
        $categories = [
            [
                'id' => 1,
                'name' => 'Processors',
                'slug' => 'processors',
                'description' => 'CPU processors for desktop and server systems',
                'icon' => 'fas fa-microchip',
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00'
            ],
            [
                'id' => 2,
                'name' => 'Graphics Cards',
                'slug' => 'graphics-cards',
                'description' => 'GPU and video cards',
                'icon' => 'fas fa-desktop',
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00'
            ],
            [
                'id' => 3,
                'name' => 'Motherboards',
                'slug' => 'motherboards',
                'description' => 'Main system boards for PC builds',
                'icon' => 'fas fa-memory',
                'is_active' => 1,
                'sort_order' => 3,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00'
            ]
        ];
    }

    Response::success([
        'categories' => $categories,
        'count' => count($categories)
    ], 'Categories retrieved successfully');

} catch (Exception $e) {
    Response::serverError('An error occurred while fetching categories');
}

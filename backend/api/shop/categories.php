<?php
/**
 * Shop Categories API Endpoint
 * GET /backend/api/shop/categories.php - Get categories available for browsing
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Allow public access (no authentication required for shop)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get all active categories with product counts
    $query = "
        SELECT
            c.id,
            c.name,
            c.slug,
            c.description,
            c.icon,
            COUNT(p.id) as product_count,
            COUNT(CASE WHEN i.quantity_available > 0 THEN p.id END) as available_product_count,
            MIN(p.selling_price) as min_price,
            MAX(p.selling_price) as max_price,
            AVG(p.selling_price) as avg_price
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 AND p.deleted_at IS NULL
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE c.is_active = 1 AND c.deleted_at IS NULL
        GROUP BY c.id, c.name, c.slug, c.description, c.icon
        ORDER BY c.sort_order ASC, c.name ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format categories
    $formattedCategories = array_map(function($category) {
        return [
            'id' => $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'],
            'icon' => $category['icon'],
            'stats' => [
                'total_products' => (int)$category['product_count'],
                'available_products' => (int)$category['available_product_count'],
                'price_range' => [
                    'min' => $category['min_price'] ? (float)$category['min_price'] : null,
                    'max' => $category['max_price'] ? (float)$category['max_price'] : null,
                    'avg' => $category['avg_price'] ? (float)$category['avg_price'] : null
                ]
            ]
        ];
    }, $categories);

    Response::success([
        'categories' => $formattedCategories,
        'total_categories' => count($formattedCategories)
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch categories: ' . $e->getMessage());
}


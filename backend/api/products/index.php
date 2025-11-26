<?php
/**
 * Products List API Endpoint
 * GET /backend/api/products/index.php
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Check if database is available
    try {
        $productModel = new Product();
        $dbAvailable = true;
    } catch (Exception $e) {
        $dbAvailable = false;
        error_log('Database not available for products: ' . $e->getMessage());
    }

    // Authentication not required for public shop
    // But we'll allow both authenticated and public access

    // Build filters from query parameters
    $filters = [];

    if (isset($_GET['category_id'])) {
        $filters['category_id'] = intval($_GET['category_id']);
    }

    if (isset($_GET['is_active'])) {
        $filters['is_active'] = intval($_GET['is_active']);
    }

    // Handle status parameter for backward compatibility
    if (isset($_GET['status'])) {
        if ($_GET['status'] == 'all') {
            // Show all products regardless of active status
            // Don't set any is_active filter
        } elseif ($_GET['status'] == 'active') {
            $filters['is_active'] = 1;
        } elseif ($_GET['status'] == 'inactive') {
            $filters['is_active'] = 0;
        }
    } elseif (!isset($_GET['is_active'])) {
        // Default to active only if no status filter specified
        $filters['is_active'] = 1;
    }

    if (isset($_GET['brand'])) {
        $filters['brand'] = $_GET['brand'];
    }

    if (isset($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }

    if (isset($_GET['low_stock']) && $_GET['low_stock'] == '1') {
        $filters['low_stock'] = true;
    }

    if (isset($_GET['limit'])) {
        $filters['limit'] = intval($_GET['limit']);
    }

    if (isset($_GET['offset'])) {
        $filters['offset'] = intval($_GET['offset']);
    }

    if ($dbAvailable) {
        $products = $productModel->getAll($filters);
    } else {
        // Return mock products when database is not available
        $products = [
            [
                'id' => 1,
                'sku' => 'AMD-RYZEN9-7950X',
                'name' => 'AMD Ryzen 9 7950X 16-Core Processor',
                'category_id' => 1,
                'category' => ['name' => 'Processors'],
                'description' => '16-core, 32-thread unlocked desktop processor with 4.5 GHz max boost',
                'brand' => 'AMD',
                'cost_price' => 450.00,
                'selling_price' => 599.99,
                'reorder_level' => 5,
                'quantity_on_hand' => 10,
                'quantity_available' => 10,
                'is_active' => 1,
                'image_url' => 'assets/img/AMD Ryzen 9 7950X 16-Core Processor.png',
                'warranty_months' => 36,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00'
            ],
            [
                'id' => 2,
                'sku' => 'INTEL-I9-13900K',
                'name' => 'Intel Core i9-13900K 24-Core Processor',
                'category_id' => 1,
                'category' => ['name' => 'Processors'],
                'description' => '24-core (8P+16E), 32-thread unlocked desktop processor with 5.8 GHz max turbo',
                'brand' => 'Intel',
                'cost_price' => 520.00,
                'selling_price' => 699.99,
                'reorder_level' => 5,
                'quantity_on_hand' => 8,
                'quantity_available' => 8,
                'is_active' => 1,
                'image_url' => 'assets/img/Intel Core i9-13900K 24-Core Processor.png',
                'warranty_months' => 36,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00'
            ],
            [
                'id' => 3,
                'sku' => 'NVIDIA-RTX4090',
                'name' => 'NVIDIA GeForce RTX 4090 24GB',
                'category_id' => 2,
                'category' => ['name' => 'Graphics Cards'],
                'description' => 'Ada Lovelace architecture with 24GB GDDR6X, 450W power requirement',
                'brand' => 'NVIDIA',
                'cost_price' => 1400.00,
                'selling_price' => 1899.99,
                'reorder_level' => 2,
                'quantity_on_hand' => 3,
                'quantity_available' => 3,
                'is_active' => 1,
                'image_url' => 'assets/img/NVIDIA GeForce RTX 4090 24GB.png',
                'warranty_months' => 36,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00'
            ]
        ];
    }

    Response::success([
        'products' => $products,
        'count' => count($products)
    ], 'Products retrieved successfully');

} catch (Exception $e) {
    error_log('Products API Error: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}

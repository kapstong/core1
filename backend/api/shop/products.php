<?php
/**
 * Shop Products API Endpoint
 * GET /backend/api/shop/products.php - Get products available for purchase
 *
 * Query Parameters:
 * - category_id: Filter by category
 * - search: Search in product name and description
 * - min_price: Minimum price filter
 * - max_price: Maximum price filter
 * - brand: Filter by brand
 * - in_stock: true/false - Only show products with available stock
 * - limit: Number of products to return (default: 20)
 * - offset: Pagination offset (default: 0)
 * - sort_by: 'name', 'price', 'newest', 'popular' (default: 'name')
 * - sort_order: 'asc', 'desc' (default: 'asc')
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
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

    // Get parameters
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
    $inStock = isset($_GET['in_stock']) ? filter_var($_GET['in_stock'], FILTER_VALIDATE_BOOLEAN) : false;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtolower($_GET['sort_order'] ?? 'asc');

    // Validate sort parameters
    $validSortBy = ['name', 'price', 'newest', 'popular'];
    $validSortOrder = ['asc', 'desc'];

    if (!in_array($sortBy, $validSortBy)) {
        $sortBy = 'name';
    }
    if (!in_array($sortOrder, $validSortOrder)) {
        $sortOrder = 'asc';
    }

    // Build query
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.description,
            p.brand,
            p.selling_price,
            p.cost_price,
            p.image_url,
            p.warranty_months,
            c.id as category_id,
            c.name as category_name,
            c.slug as category_slug,
            i.quantity_available,
            i.quantity_on_hand,
            CASE
                WHEN i.quantity_available > 0 THEN true
                ELSE false
            END as in_stock,
            CASE
                WHEN p.reorder_level > 0 AND i.quantity_available <= p.reorder_level THEN true
                ELSE false
            END as low_stock,
            p.created_at,
            p.updated_at
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1 AND p.deleted_at IS NULL AND c.is_active = 1
    ";

    $params = [];
    $conditions = [];

    // Add filters
    if ($categoryId) {
        $conditions[] = "p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    if (!empty($search)) {
        $conditions[] = "(p.name LIKE :search OR p.description LIKE :search OR p.brand LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($minPrice !== null) {
        $conditions[] = "p.selling_price >= :min_price";
        $params[':min_price'] = $minPrice;
    }

    if ($maxPrice !== null) {
        $conditions[] = "p.selling_price <= :max_price";
        $params[':max_price'] = $maxPrice;
    }

    if (!empty($brand)) {
        $conditions[] = "p.brand = :brand";
        $params[':brand'] = $brand;
    }

    if ($inStock) {
        $conditions[] = "i.quantity_available > 0";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    // Add sorting
    $orderClause = match($sortBy) {
        'price' => "p.selling_price $sortOrder",
        'newest' => "p.created_at DESC",
        'popular' => "i.quantity_on_hand DESC", // Simple popularity based on stock levels
        default => "p.name $sortOrder"
    };

    $query .= " ORDER BY $orderClause LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1 AND p.deleted_at IS NULL AND c.is_active = 1
    ";

    if (!empty($conditions)) {
        $countQuery .= " AND " . implode(" AND ", $conditions);
    }

    $countStmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get available brands for filtering
    $brandQuery = "
        SELECT DISTINCT p.brand
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1 AND p.deleted_at IS NULL AND c.is_active = 1 AND p.brand IS NOT NULL AND p.brand != ''
    ";

    if (!empty($conditions)) {
        $brandQuery .= " AND " . implode(" AND ", $conditions);
    }

    $brandQuery .= " ORDER BY p.brand";

    $brandStmt = $conn->prepare($brandQuery);
    foreach ($params as $key => $value) {
        if ($key !== ':search') { // Remove search from brand query to get all brands
            $brandStmt->bindValue($key, $value);
        }
    }
    $brandStmt->execute();
    $brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);

    // Format products
    $formattedProducts = array_map(function($product) {
        return [
            'id' => $product['id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'description' => $product['description'],
            'brand' => $product['brand'],
            'pricing' => [
                'selling_price' => (float)$product['selling_price'],
                'cost_price' => (float)$product['cost_price']
            ],
            'category' => [
                'id' => $product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug']
            ],
            'inventory' => [
                'quantity_available' => (int)$product['quantity_available'],
                'quantity_on_hand' => (int)$product['quantity_on_hand'],
                'in_stock' => $product['in_stock'],
                'low_stock' => $product['low_stock']
            ],
            'image_url' => $product['image_url'],
            'warranty_months' => (int)$product['warranty_months'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }, $products);

    Response::success([
        'products' => $formattedProducts,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'filters' => [
            'available_brands' => $brands,
            'applied_filters' => [
                'category_id' => $categoryId,
                'search' => $search,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'brand' => $brand,
                'in_stock' => $inStock
            ]
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch products: ' . $e->getMessage());
}

<?php
/**
 * Promotions and Discounts API Endpoint
 * GET /backend/api/promotions/index.php - List promotions
 * POST /backend/api/promotions/index.php - Create promotion
 * PUT /backend/api/promotions/index.php?id={id} - Update promotion
 * DELETE /backend/api/promotions/index.php?id={id} - Delete promotion
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
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

// Check if user has permission to manage promotions
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            isset($_GET['id']) ? getPromotion($_GET['id']) : listPromotions();
            break;

        case 'POST':
            createPromotion();
            break;

        case 'PUT':
            updatePromotion();
            break;

        case 'DELETE':
            deletePromotion();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Promotion operation failed: ' . $e->getMessage());
}

function listPromotions() {
    $db = Database::getInstance()->getConnection();

    // Get query parameters
    $status = isset($_GET['status']) ? $_GET['status'] : null; // active, inactive, expired
    $type = isset($_GET['type']) ? $_GET['type'] : null; // percentage, fixed, free_shipping
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Build query
    $query = "
        SELECT
            p.*,
            CASE
                WHEN p.is_active = 0 THEN 'inactive'
                WHEN p.start_date > NOW() THEN 'scheduled'
                WHEN p.end_date < NOW() THEN 'expired'
                ELSE 'active'
            END as status,
            CASE
                WHEN p.usage_limit > 0 THEN p.usage_count / p.usage_limit * 100
                ELSE 0
            END as usage_percentage
        FROM promotions p
        WHERE 1=1
    ";

    $params = [];

    // Add filters
    if ($status) {
        switch ($status) {
            case 'active':
                $query .= " AND p.is_active = 1 AND p.start_date <= NOW() AND p.end_date >= NOW()";
                break;
            case 'inactive':
                $query .= " AND p.is_active = 0";
                break;
            case 'expired':
                $query .= " AND p.end_date < NOW()";
                break;
            case 'scheduled':
                $query .= " AND p.start_date > NOW() AND p.is_active = 1";
                break;
        }
    }

    if ($type) {
        $query .= " AND p.discount_type = :type";
        $params[':type'] = $type;
    }

    $query .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM promotions p WHERE 1=1";
    if (!empty($params)) {
        $countQuery .= str_replace('ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset', '', $query);
        $countQuery = str_replace('SELECT p.*, CASE WHEN p.is_active = 0 THEN \'inactive\' WHEN p.start_date > NOW() THEN \'scheduled\' WHEN p.end_date < NOW() THEN \'expired\' ELSE \'active\' END as status, CASE WHEN p.usage_limit > 0 THEN p.usage_count / p.usage_limit * 100 ELSE 0 END as usage_percentage FROM promotions p WHERE 1=1', 'SELECT COUNT(*) as total FROM promotions p WHERE 1=1', $countQuery);
    }

    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format promotions
    $formattedPromotions = array_map(function($promotion) {
        return [
            'id' => $promotion['id'],
            'code' => $promotion['code'],
            'name' => $promotion['name'],
            'description' => $promotion['description'],
            'discount_type' => $promotion['discount_type'],
            'discount_value' => (float)$promotion['discount_value'],
            'minimum_order_amount' => $promotion['minimum_order_amount'] ? (float)$promotion['minimum_order_amount'] : null,
            'applicable_products' => $promotion['applicable_products'] ? json_decode($promotion['applicable_products'], true) : null,
            'applicable_categories' => $promotion['applicable_categories'] ? json_decode($promotion['applicable_categories'], true) : null,
            'usage_limit' => (int)$promotion['usage_limit'],
            'usage_count' => (int)$promotion['usage_count'],
            'usage_percentage' => round((float)$promotion['usage_percentage'], 2),
            'start_date' => $promotion['start_date'],
            'end_date' => $promotion['end_date'],
            'is_active' => (bool)$promotion['is_active'],
            'status' => $promotion['status'],
            'created_at' => $promotion['created_at'],
            'updated_at' => $promotion['updated_at']
        ];
    }, $promotions);

    Response::success([
        'promotions' => $formattedPromotions,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'filters' => [
            'status' => $status,
            'type' => $type
        ]
    ]);
}

function getPromotion($id) {
    $db = Database::getInstance()->getConnection();

    $query = "
        SELECT
            p.*,
            CASE
                WHEN p.is_active = 0 THEN 'inactive'
                WHEN p.start_date > NOW() THEN 'scheduled'
                WHEN p.end_date < NOW() THEN 'expired'
                ELSE 'active'
            END as status,
            CASE
                WHEN p.usage_limit > 0 THEN p.usage_count / p.usage_limit * 100
                ELSE 0
            END as usage_percentage
        FROM promotions p
        WHERE p.id = :id
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        Response::error('Promotion not found', 404);
    }

    $formattedPromotion = [
        'id' => $promotion['id'],
        'code' => $promotion['code'],
        'name' => $promotion['name'],
        'description' => $promotion['description'],
        'discount_type' => $promotion['discount_type'],
        'discount_value' => (float)$promotion['discount_value'],
        'minimum_order_amount' => $promotion['minimum_order_amount'] ? (float)$promotion['minimum_order_amount'] : null,
        'applicable_products' => $promotion['applicable_products'] ? json_decode($promotion['applicable_products'], true) : null,
        'applicable_categories' => $promotion['applicable_categories'] ? json_decode($promotion['applicable_categories'], true) : null,
        'usage_limit' => (int)$promotion['usage_limit'],
        'usage_count' => (int)$promotion['usage_count'],
        'usage_percentage' => round((float)$promotion['usage_percentage'], 2),
        'start_date' => $promotion['start_date'],
        'end_date' => $promotion['end_date'],
        'is_active' => (bool)$promotion['is_active'],
        'status' => $promotion['status'],
        'created_at' => $promotion['created_at'],
        'updated_at' => $promotion['updated_at']
    ];

    Response::success(['promotion' => $formattedPromotion]);
}

function createPromotion() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['code', 'name', 'discount_type', 'discount_value', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            Response::error("{$field} is required", 400);
        }
    }

    // Validate discount type
    $validTypes = ['percentage', 'fixed', 'free_shipping'];
    if (!in_array($input['discount_type'], $validTypes)) {
        Response::error('Invalid discount type. Must be: percentage, fixed, or free_shipping', 400);
    }

    // Validate discount value
    $discountValue = (float)$input['discount_value'];
    if ($discountValue <= 0) {
        Response::error('Discount value must be greater than 0', 400);
    }

    if ($input['discount_type'] === 'percentage' && $discountValue > 100) {
        Response::error('Percentage discount cannot exceed 100%', 400);
    }

    // Validate dates
    $startDate = $input['start_date'];
    $endDate = $input['end_date'];

    if (strtotime($startDate) >= strtotime($endDate)) {
        Response::error('End date must be after start date', 400);
    }

    // Check if code already exists
    $db = Database::getInstance()->getConnection();
    $existingQuery = "SELECT id FROM promotions WHERE code = :code";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':code', strtoupper(trim($input['code'])));
    $existingStmt->execute();

    if ($existingStmt->fetch()) {
        Response::error('Promotion code already exists', 409);
    }

    // Prepare promotion data
    $promotionData = [
        'code' => strtoupper(trim($input['code'])),
        'name' => trim($input['name']),
        'description' => isset($input['description']) ? trim($input['description']) : null,
        'discount_type' => $input['discount_type'],
        'discount_value' => $discountValue,
        'minimum_order_amount' => isset($input['minimum_order_amount']) ? (float)$input['minimum_order_amount'] : null,
        'applicable_products' => isset($input['applicable_products']) ? json_encode($input['applicable_products']) : null,
        'applicable_categories' => isset($input['applicable_categories']) ? json_encode($input['applicable_categories']) : null,
        'usage_limit' => isset($input['usage_limit']) ? (int)$input['usage_limit'] : 0,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : true
    ];

    // Insert promotion
    $insertQuery = "INSERT INTO promotions
                   (code, name, description, discount_type, discount_value, minimum_order_amount,
                    applicable_products, applicable_categories, usage_limit, start_date, end_date, is_active)
                   VALUES
                   (:code, :name, :description, :discount_type, :discount_value, :minimum_order_amount,
                    :applicable_products, :applicable_categories, :usage_limit, :start_date, :end_date, :is_active)";

    $stmt = $db->prepare($insertQuery);

    foreach ($promotionData as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }

    if (!$stmt->execute()) {
        Response::error('Failed to create promotion', 500);
    }

    $promotionId = $db->lastInsertId();

    Response::success([
        'message' => 'Promotion created successfully',
        'promotion_id' => $promotionId,
        'promotion' => array_merge(['id' => $promotionId], $promotionData)
    ], 201);
}

function updatePromotion() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$id) {
        Response::error('Promotion ID is required', 400);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Check if promotion exists
    $db = Database::getInstance()->getConnection();
    $existingQuery = "SELECT id FROM promotions WHERE id = :id";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $existingStmt->execute();

    if (!$existingStmt->fetch()) {
        Response::error('Promotion not found', 404);
    }

    // Validate discount type if provided
    if (isset($input['discount_type'])) {
        $validTypes = ['percentage', 'fixed', 'free_shipping'];
        if (!in_array($input['discount_type'], $validTypes)) {
            Response::error('Invalid discount type. Must be: percentage, fixed, or free_shipping', 400);
        }
    }

    // Validate discount value if provided
    if (isset($input['discount_value'])) {
        $discountValue = (float)$input['discount_value'];
        if ($discountValue <= 0) {
            Response::error('Discount value must be greater than 0', 400);
        }

        $discountType = $input['discount_type'] ?? null;
        if ($discountType === 'percentage' && $discountValue > 100) {
            Response::error('Percentage discount cannot exceed 100%', 400);
        }
    }

    // Validate dates if provided
    if (isset($input['start_date']) && isset($input['end_date'])) {
        if (strtotime($input['start_date']) >= strtotime($input['end_date'])) {
            Response::error('End date must be after start date', 400);
        }
    }

    // Check code uniqueness if changing code
    if (isset($input['code'])) {
        $codeCheckQuery = "SELECT id FROM promotions WHERE code = :code AND id != :id";
        $codeCheckStmt = $db->prepare($codeCheckQuery);
        $codeCheckStmt->bindParam(':code', strtoupper(trim($input['code'])));
        $codeCheckStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $codeCheckStmt->execute();

        if ($codeCheckStmt->fetch()) {
            Response::error('Promotion code already exists', 409);
        }
    }

    // Build update query
    $updateFields = [];
    $params = [':id' => $id];

    $allowedFields = [
        'code', 'name', 'description', 'discount_type', 'discount_value',
        'minimum_order_amount', 'applicable_products', 'applicable_categories',
        'usage_limit', 'start_date', 'end_date', 'is_active'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "{$field} = :{$field}";
            $params[":{$field}"] = $field === 'code' ? strtoupper(trim($input[$field])) : $input[$field];

            // JSON encode arrays
            if (in_array($field, ['applicable_products', 'applicable_categories']) && is_array($input[$field])) {
                $params[":{$field}"] = json_encode($input[$field]);
            }
        }
    }

    if (empty($updateFields)) {
        Response::error('No valid fields to update', 400);
    }

    $updateQuery = "UPDATE promotions SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);

    if (!$updateStmt->execute($params)) {
        Response::error('Failed to update promotion', 500);
    }

    Response::success([
        'message' => 'Promotion updated successfully',
        'promotion_id' => $id
    ]);
}

function deletePromotion() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$id) {
        Response::error('Promotion ID is required', 400);
    }

    // Check if promotion exists and can be deleted
    $db = Database::getInstance()->getConnection();
    $existingQuery = "SELECT id, usage_count FROM promotions WHERE id = :id";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $existingStmt->execute();

    $promotion = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        Response::error('Promotion not found', 404);
    }

    // Prevent deletion if promotion has been used
    if ($promotion['usage_count'] > 0) {
        Response::error('Cannot delete promotion that has been used', 400);
    }

    // Delete promotion
    $deleteQuery = "DELETE FROM promotions WHERE id = :id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);

    if (!$deleteStmt->execute()) {
        Response::error('Failed to delete promotion', 500);
    }

    Response::success([
        'message' => 'Promotion deleted successfully',
        'promotion_id' => $id
    ]);
}

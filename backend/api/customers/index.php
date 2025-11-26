<?php
/**
 * Customer Management API Endpoint
 * GET /backend/api/customers/index.php - List customers
 * POST /backend/api/customers/index.php - Create customer (admin only)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../models/Customer.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication and role
Auth::requireRole(['admin', 'inventory_manager']);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            listCustomers();
            break;

        case 'POST':
            createCustomer();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Customer management failed: ' . $e->getMessage());
}

function listCustomers() {
    $db = Database::getInstance()->getConnection();

    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? $_GET['status'] : null; // active, inactive
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
    $sortOrder = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Validate sort parameters
    $validSortBy = ['id', 'first_name', 'last_name', 'email', 'created_at', 'last_login'];
    $validSortOrder = ['asc', 'desc'];

    if (!in_array($sortBy, $validSortBy)) {
        $sortBy = 'created_at';
    }
    if (!in_array($sortOrder, $validSortOrder)) {
        $sortOrder = 'desc';
    }

    // Build query
    $query = "SELECT
                c.id,
                c.email,
                c.first_name,
                c.last_name,
                c.phone,
                c.is_active,
                c.email_verified,
                c.last_login,
                c.created_at,
                COUNT(DISTINCT co.id) as total_orders,
                COALESCE(SUM(co.total_amount), 0) as total_spent,
                MAX(co.order_date) as last_order_date
              FROM customers c
              LEFT JOIN customer_orders co ON c.id = co.customer_id
              WHERE 1=1";

    $params = [];

    // Add search filter
    if (!empty($search)) {
        $query .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    // Add status filter
    if ($status === 'active') {
        $query .= " AND c.is_active = 1";
    } elseif ($status === 'inactive') {
        $query .= " AND c.is_active = 0";
    }

    $query .= " GROUP BY c.id, c.email, c.first_name, c.last_name, c.phone, c.is_active, c.email_verified, c.last_login, c.created_at
               ORDER BY {$sortBy} {$sortOrder}
               LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM customers c WHERE 1=1";
    if (!empty($params)) {
        $countQuery .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search)";
    }
    if ($status === 'active') {
        $countQuery .= " AND c.is_active = 1";
    } elseif ($status === 'inactive') {
        $countQuery .= " AND c.is_active = 0";
    }

    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format customers
    $formattedCustomers = array_map(function($customer) {
        return [
            'id' => $customer['id'],
            'email' => $customer['email'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'full_name' => $customer['first_name'] . ' ' . $customer['last_name'],
            'phone' => $customer['phone'],
            'is_active' => (bool)$customer['is_active'],
            'email_verified' => (bool)$customer['email_verified'],
            'last_login' => $customer['last_login'],
            'created_at' => $customer['created_at'],
            'stats' => [
                'total_orders' => (int)$customer['total_orders'],
                'total_spent' => (float)$customer['total_spent'],
                'last_order_date' => $customer['last_order_date'],
                'avg_order_value' => $customer['total_orders'] > 0 ? round($customer['total_spent'] / $customer['total_orders'], 2) : 0
            ]
        ];
    }, $customers);

    Response::success([
        'customers' => $formattedCustomers,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'filters' => [
            'search' => $search,
            'status' => $status,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);
}

function createCustomer() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['email', 'first_name', 'last_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Invalid email format', 400);
    }

    // Check if email already exists
    $customerModel = new Customer();
    $existing = $customerModel->findByEmail(strtolower(trim($input['email'])));
    if ($existing) {
        Response::error('Email already registered', 409);
    }

    // Prepare customer data
    $customerData = [
        'email' => strtolower(trim($input['email'])),
        'password_hash' => password_hash($input['password'] ?? 'defaultpassword', PASSWORD_DEFAULT),
        'first_name' => trim($input['first_name']),
        'last_name' => trim($input['last_name']),
        'phone' => isset($input['phone']) ? trim($input['phone']) : null,
        'date_of_birth' => isset($input['date_of_birth']) ? $input['date_of_birth'] : null,
        'gender' => isset($input['gender']) ? $input['gender'] : null
    ];

    // Create customer
    $customer = $customerModel->create($customerData);

    if (!$customer) {
        Response::error('Failed to create customer account', 500);
    }

    // Return customer data (exclude password hash)
    unset($customer['password_hash']);
    unset($customer['email_verification_token']);
    unset($customer['password_reset_token']);

    Response::success([
        'customer' => $customer,
        'message' => 'Customer account created successfully'
    ], 201);
}

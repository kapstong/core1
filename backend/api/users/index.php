<?php
/**
 * Users API - List all users
 * GET /backend/api/users/index.php
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Enable error reporting for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Check authentication first
    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    // Get user data
    $user = Auth::user();

    // Only admins, inventory managers, and purchasing officers can list users
    if (!$user || !in_array($user['role'], ['admin', 'inventory_manager', 'purchasing_officer'])) {
        Response::error('Access denied. Admin, inventory manager, or purchasing officer privileges required.', 403);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if role filter is requested
    $roleFilter = isset($_GET['role']) ? $_GET['role'] : null;

    // First, get total count of users
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL";
    $countStmt = $conn->query($countQuery);
    $totalUsers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get all users without any filter first
    $allQuery = "SELECT role, COUNT(*) as count FROM users WHERE deleted_at IS NULL GROUP BY role";
    $roleStmt = $conn->query($allQuery);
    $roleCounts = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build query based on role filter
    if ($roleFilter === 'supplier') {
        // If specifically requesting suppliers, return only suppliers
        $query = "SELECT
                    u.id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role,
                    u.is_active,
                    u.last_login,
                    u.created_at
                  FROM users u
                  WHERE u.role = 'supplier' AND u.deleted_at IS NULL
                  ORDER BY u.full_name ASC";
    } else {
        // Get all users EXCLUDING ALL SUPPLIERS (pending and approved)
        // Suppliers are managed separately:
        // - Pending suppliers: shown in User Management > Pending Section (separate endpoint)
        // - Approved suppliers: shown ONLY in Purchasing > Suppliers
        $query = "SELECT
                    u.id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role,
                    u.is_active,
                    u.last_login,
                    u.created_at
                  FROM users u
                  WHERE
                    u.deleted_at IS NULL
                    AND u.role != 'supplier'
                  ORDER BY
                    u.created_at DESC";
    }

    require_once __DIR__ . '/../../utils/Logger.php';
    $logger = new Logger($user['id']);
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Get results
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log detailed debug info
    $logger->log('users_query_debug', 'debug', null, [
        'total_users' => $totalUsers,
        'role_counts' => $roleCounts,
        'query' => $query,
        'results_count' => count($users),
        'user_role' => $user['role']
    ]);

    // Send the direct response - frontend expects users at top level
    echo json_encode([
        'success' => true,
        'message' => 'Success',
        'users' => $users,
        'total' => count($users)
    ]);
    exit;

    // Log the query results
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $logger->log('users_list', 'users', null, [
        'count' => count($users),
        'roles' => array_unique(array_column($users, 'role'))
    ]);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success([
        'users' => $users,
        'total' => count($users)
    ]);
} catch (Exception $e) {
    Response::serverError('An error occurred while fetching users');
}

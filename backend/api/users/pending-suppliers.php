<?php
/**
 * Pending Suppliers API - List pending suppliers awaiting approval
 * GET /backend/api/users/pending-suppliers.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
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
    // Check authentication first
    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    // Get user data
    $user = Auth::user();

    // Only admins and inventory managers can view pending suppliers
    if (!$user || !in_array($user['role'], ['admin', 'inventory_manager'])) {
        Response::error('Access denied. Admin or inventory manager privileges required.', 403);
    }

    // Try to get database connection
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
    } catch (Exception $e) {
        // If database connection fails, return empty result instead of error
        Response::success([
            'suppliers' => [],
            'total' => 0
        ]);
        return;
    }

    // Get pending suppliers (supplier status determined by is_active column)
    // is_active = 0 means pending approval
    $query = "SELECT
                id,
                username,
                email,
                phone,
                full_name,
                role,
                is_active,
                last_login,
                created_at,
                updated_at
              FROM users
              WHERE
                role = 'supplier'
                AND is_active = 0
                AND deleted_at IS NULL
              ORDER BY
                created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    // Get results
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format suppliers for frontend consistency
    $formattedSuppliers = array_map(function($supplier) {
        $isActive = (bool)$supplier['is_active'];
        $supplierStatus = 'pending_approval'; // All suppliers here are pending (is_active = 0)

        return [
            'id' => (int)$supplier['id'],
            'user_id' => (int)$supplier['id'],
            'code' => 'SUP-' . str_pad($supplier['id'], 5, '0', STR_PAD_LEFT),
            'name' => $supplier['full_name'] ?? $supplier['username'] ?? 'Unknown',
            'supplier_name' => $supplier['full_name'] ?? $supplier['username'] ?? 'Unknown',
            'full_name' => $supplier['full_name'] ?? $supplier['username'] ?? 'Unknown',
            'username' => $supplier['username'] ?? 'unknown',
            'email' => $supplier['email'] ?? 'N/A',
            'phone' => $supplier['phone'] ?? 'N/A',
            'contact_person' => $supplier['full_name'] ?? 'N/A',
            'is_active' => $isActive,
            'status' => $isActive ? 'active' : 'inactive',
            'supplier_status' => $supplierStatus,
            'role' => $supplier['role'] ?? 'supplier',
            'created_at' => $supplier['created_at'],
            'updated_at' => $supplier['updated_at'] ?? $supplier['created_at'],
            'last_login' => $supplier['last_login'] ?? null
        ];
    }, $suppliers);

    Response::success([
        'suppliers' => $formattedSuppliers,
        'total' => count($formattedSuppliers)
    ]);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Pending suppliers API error: " . $e->getMessage());

    // Return a more user-friendly error response
    Response::error('Unable to load pending suppliers. Please try again later.', 500);
}
?>

<?php
/**
 * RBAC Permissions Management API
 * GET - List all permissions grouped by category
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Permissions.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Check if user has permission to view permissions
$currentUser = Auth::user();
if (!Permissions::userHas($currentUser['id'], 'view_settings')) {
    Response::error('Access denied', 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all permissions
        $query = "SELECT id, name, description, category FROM permissions ORDER BY category, name";
        $stmt = $conn->query($query);
        $allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group permissions by category
        $permissionsByCategory = [];
        foreach ($allPermissions as $permission) {
            $category = $permission['category'] ?? 'Other';
            if (!isset($permissionsByCategory[$category])) {
                $permissionsByCategory[$category] = [];
            }
            $permissionsByCategory[$category][] = $permission;
        }

        Response::success([
            'permissions' => $allPermissions,
            'permissions_by_category' => $permissionsByCategory
        ]);

    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to fetch permissions: ' . $e->getMessage());
}

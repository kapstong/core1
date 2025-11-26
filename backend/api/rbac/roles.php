<?php
/**
 * RBAC Roles Management API
 * GET - List all roles with their permissions
 * POST - Update role permissions
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

// Check if user has permission to manage roles
$currentUser = Auth::user();
if (!Permissions::userHas($currentUser['id'], 'manage_permissions')) {
    Response::error('Access denied. You need manage_permissions permission.', 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all available roles
        $roles = [
            [
                'name' => 'admin',
                'label' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'is_system' => true,
                'user_count' => 0
            ],
            [
                'name' => 'inventory_manager',
                'label' => 'Inventory Manager',
                'description' => 'Manages products, inventory, and orders',
                'is_system' => true,
                'user_count' => 0
            ],
            [
                'name' => 'purchasing_officer',
                'label' => 'Purchasing Officer',
                'description' => 'Handles purchase orders, GRN, and suppliers',
                'is_system' => true,
                'user_count' => 0
            ],
            [
                'name' => 'staff',
                'label' => 'Staff',
                'description' => 'Basic sales and customer operations',
                'is_system' => true,
                'user_count' => 0
            ],
            [
                'name' => 'supplier',
                'label' => 'Supplier',
                'description' => 'Supplier portal access',
                'is_system' => true,
                'user_count' => 0
            ]
        ];

        // Get user counts for each role
        foreach ($roles as &$role) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
            $stmt->execute([$role['name']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $role['user_count'] = (int)$result['count'];

            // Get permissions for this role
            $permStmt = $conn->prepare("
                SELECT p.id, p.name, p.description, p.category
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = ?
                ORDER BY p.category, p.name
            ");
            $permStmt->execute([$role['name']]);
            $role['permissions'] = $permStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        Response::success(['roles' => $roles]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update role permissions
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['role']) || !isset($data['permissions'])) {
            Response::error('Role and permissions are required', 400);
        }

        $role = $data['role'];
        $permissions = $data['permissions']; // Array of permission IDs

        // Validate role
        $validRoles = ['admin', 'inventory_manager', 'purchasing_officer', 'staff', 'supplier'];
        if (!in_array($role, $validRoles)) {
            Response::error('Invalid role', 400);
        }

        // Don't allow modifying admin role
        if ($role === 'admin') {
            Response::error('Cannot modify admin role permissions', 403);
        }

        $conn->beginTransaction();

        try {
            // Delete existing role permissions
            $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role = ?");
            $stmt->execute([$role]);

            // Insert new permissions
            if (!empty($permissions)) {
                $insertStmt = $conn->prepare("
                    INSERT INTO role_permissions (role, permission_id)
                    VALUES (?, ?)
                ");

                foreach ($permissions as $permissionId) {
                    $insertStmt->execute([$role, $permissionId]);
                }
            }

            $conn->commit();
            Response::success(['message' => 'Role permissions updated successfully']);

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }

    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to manage roles: ' . $e->getMessage());
}

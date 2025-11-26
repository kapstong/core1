<?php
/**
 * Permissions Utility Class
 * Handles role-based access control (RBAC)
 */

class Permissions {

    private static $db = null;
    private static $cache = [];

    /**
     * Get database connection
     */
    private static function getDB() {
        if (self::$db === null) {
            require_once __DIR__ . '/../config/database.php';
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * Check if a user has a specific permission
     *
     * @param int $userId User ID
     * @param string $permission Permission name
     * @return bool
     */
    public static function userHas($userId, $permission) {
        try {
            $db = self::getDB();

            // Get user role
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            $role = $user['role'];

            // Admin has all permissions
            if ($role === 'admin') {
                return true;
            }

            return self::roleHas($role, $permission, $userId);

        } catch (Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a role has a specific permission
     *
     * @param string $role User role
     * @param string $permission Permission name
     * @param int|null $userId User ID for user-specific overrides
     * @return bool
     */
    public static function roleHas($role, $permission, $userId = null) {
        try {
            $db = self::getDB();

            // Check cache
            $cacheKey = "{$role}:{$permission}";
            if (isset(self::$cache[$cacheKey])) {
                return self::$cache[$cacheKey];
            }

            // Check if user has specific permission override
            if ($userId) {
                $stmt = $db->prepare("
                    SELECT up.granted
                    FROM user_permissions up
                    JOIN permissions p ON up.permission_id = p.id
                    WHERE up.user_id = ? AND p.name = ?
                    LIMIT 1
                ");
                $stmt->execute([$userId, $permission]);
                $override = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($override !== false) {
                    return (bool)$override['granted'];
                }
            }

            // Check role permission
            $stmt = $db->prepare("
                SELECT COUNT(*) as has_permission
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = ? AND p.name = ?
            ");
            $stmt->execute([$role, $permission]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $hasPermission = $result['has_permission'] > 0;

            // Cache result
            self::$cache[$cacheKey] = $hasPermission;

            return $hasPermission;

        } catch (Exception $e) {
            error_log("Role permission check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all permissions for a role
     *
     * @param string $role User role
     * @return array
     */
    public static function getRolePermissions($role) {
        try {
            $db = self::getDB();

            $stmt = $db->prepare("
                SELECT p.*
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role = ?
                ORDER BY p.category, p.display_name
            ");
            $stmt->execute([$role]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Get role permissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all permissions for a user (including user-specific overrides)
     *
     * @param int $userId User ID
     * @return array
     */
    public static function getUserPermissions($userId) {
        try {
            $db = self::getDB();

            // Get user role
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [];
            }

            // Get role permissions
            $permissions = self::getRolePermissions($user['role']);

            // Apply user-specific overrides
            $stmt = $db->prepare("
                SELECT p.*, up.granted
                FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.id
                WHERE up.user_id = ?
            ");
            $stmt->execute([$userId]);
            $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Apply overrides
            $permissionMap = [];
            foreach ($permissions as $perm) {
                $permissionMap[$perm['name']] = $perm;
            }

            foreach ($overrides as $override) {
                if ($override['granted']) {
                    $permissionMap[$override['name']] = $override;
                } else {
                    unset($permissionMap[$override['name']]);
                }
            }

            return array_values($permissionMap);

        } catch (Exception $e) {
            error_log("Get user permissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all available permissions grouped by category
     *
     * @return array
     */
    public static function getAllPermissions() {
        try {
            $db = self::getDB();

            $stmt = $db->query("
                SELECT * FROM permissions
                ORDER BY category, display_name
            ");

            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by category
            $grouped = [];
            foreach ($permissions as $perm) {
                $category = $perm['category'];
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                $grouped[$category][] = $perm;
            }

            return $grouped;

        } catch (Exception $e) {
            error_log("Get all permissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Grant a permission to a role
     *
     * @param string $role User role
     * @param string $permission Permission name
     * @return bool
     */
    public static function grantToRole($role, $permission) {
        try {
            $db = self::getDB();

            $stmt = $db->prepare("
                INSERT INTO role_permissions (role, permission_id)
                SELECT ?, id FROM permissions WHERE name = ?
                ON DUPLICATE KEY UPDATE role = role
            ");
            $stmt->execute([$role, $permission]);

            // Clear cache
            self::clearCache();

            return true;

        } catch (Exception $e) {
            error_log("Grant permission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke a permission from a role
     *
     * @param string $role User role
     * @param string $permission Permission name
     * @return bool
     */
    public static function revokeFromRole($role, $permission) {
        try {
            $db = self::getDB();

            $stmt = $db->prepare("
                DELETE rp FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = ? AND p.name = ?
            ");
            $stmt->execute([$role, $permission]);

            // Clear cache
            self::clearCache();

            return true;

        } catch (Exception $e) {
            error_log("Revoke permission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set all permissions for a role (replaces existing)
     *
     * @param string $role User role
     * @param array $permissions Array of permission names
     * @return bool
     */
    public static function setRolePermissions($role, $permissions) {
        try {
            $db = self::getDB();
            $db->beginTransaction();

            // Delete existing permissions
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role = ?");
            $stmt->execute([$role]);

            // Insert new permissions
            if (!empty($permissions)) {
                $placeholders = implode(',', array_fill(0, count($permissions), '?'));
                $stmt = $db->prepare("
                    INSERT INTO role_permissions (role, permission_id)
                    SELECT ?, id FROM permissions WHERE name IN ($placeholders)
                ");
                $stmt->execute(array_merge([$role], $permissions));
            }

            $db->commit();

            // Clear cache
            self::clearCache();

            return true;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Set role permissions error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check multiple permissions (requires ALL)
     *
     * @param int $userId User ID
     * @param array $permissions Array of permission names
     * @return bool
     */
    public static function userHasAll($userId, $permissions) {
        foreach ($permissions as $permission) {
            if (!self::userHas($userId, $permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check multiple permissions (requires ANY)
     *
     * @param int $userId User ID
     * @param array $permissions Array of permission names
     * @return bool
     */
    public static function userHasAny($userId, $permissions) {
        foreach ($permissions as $permission) {
            if (self::userHas($userId, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Clear permission cache
     */
    public static function clearCache() {
        self::$cache = [];
    }

    /**
     * Get permission name from page/feature
     * Maps page names to required permissions
     *
     * @param string $page Page identifier
     * @return string|null Permission name
     */
    public static function getPermissionForPage($page) {
        $pagePermissions = [
            'dashboard' => 'view_dashboard',
            'products' => 'view_products',
            'categories' => 'view_categories',
            'orders' => 'view_orders',
            'purchase-orders' => 'view_purchase_orders',
            'grn' => 'view_grn',
            'suppliers' => 'view_suppliers',
            'customers' => 'view_customers',
            'users' => 'view_users',
            'reports' => 'view_reports',
            'logs' => 'view_audit_logs',
            'settings' => 'view_settings'
        ];

        return $pagePermissions[$page] ?? null;
    }
}

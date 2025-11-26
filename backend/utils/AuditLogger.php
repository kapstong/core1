<?php
/**
 * Audit Logger Utility
 * Tracks all user actions and system changes for security and compliance
 */

class AuditLogger {

    private static $db = null;

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
     * Log an action to the audit log
     *
     * @param string $action Action performed (e.g., 'login', 'create', 'update', 'delete')
     * @param string $entityType Type of entity affected (e.g., 'user', 'product', 'order')
     * @param int|null $entityId ID of the entity
     * @param string $description Human-readable description
     * @param array|null $oldValues Previous values (for updates)
     * @param array|null $newValues New values (for creates/updates)
     * @param int|null $userId User who performed the action (null for system actions)
     */
    public static function log(
        $action,
        $entityType,
        $entityId = null,
        $description = '',
        $oldValues = null,
        $newValues = null,
        $userId = null
    ) {
        try {
            // Start session if not already started (to get user info)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Get user ID from session if not provided
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // Get username from session
            $username = $_SESSION['username'] ?? null;

            // Get IP address
            $ipAddress = self::getClientIP();

            // Get user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Convert arrays to JSON
            $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
            $newValuesJson = $newValues ? json_encode($newValues) : null;

            // Insert into database
            $db = self::getDB();
            $stmt = $db->prepare("
                INSERT INTO audit_logs
                (user_id, username, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $username,
                $action,
                $entityType,
                $entityId,
                $description,
                $oldValuesJson,
                $newValuesJson,
                $ipAddress,
                $userAgent
            ]);

            return true;
        } catch (Exception $e) {
            // Log error but don't throw to prevent breaking the main operation
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convenience method for logging user login
     */
    public static function logLogin($userId, $username, $success = true) {
        return self::log(
            $success ? 'login' : 'login_failed',
            'user',
            $userId,
            $success ? "User logged in successfully" : "Failed login attempt for user: {$username}"
        );
    }

    /**
     * Convenience method for logging user logout
     */
    public static function logLogout($userId, $username) {
        return self::log(
            'logout',
            'user',
            $userId,
            "User logged out"
        );
    }

    /**
     * Convenience method for logging create operations
     */
    public static function logCreate($entityType, $entityId, $description, $values = null) {
        return self::log(
            'create',
            $entityType,
            $entityId,
            $description,
            null,
            $values
        );
    }

    /**
     * Convenience method for logging update operations
     */
    public static function logUpdate($entityType, $entityId, $description, $oldValues = null, $newValues = null) {
        return self::log(
            'update',
            $entityType,
            $entityId,
            $description,
            $oldValues,
            $newValues
        );
    }

    /**
     * Convenience method for logging delete operations
     */
    public static function logDelete($entityType, $entityId, $description, $oldValues = null) {
        return self::log(
            'delete',
            $entityType,
            $entityId,
            $description,
            $oldValues,
            null
        );
    }

    /**
     * Get client IP address (handles proxies)
     */
    private static function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get recent audit logs
     *
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset for pagination
     * @param array $filters Filters to apply (action, entity_type, user_id, etc.)
     */
    public static function getLogs($limit = 50, $offset = 0, $filters = []) {
        try {
            $db = self::getDB();

            $where = [];
            $params = [];

            // Apply filters
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['entity_type'])) {
                $where[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }

            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['start_date'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['start_date'];
            }

            if (!empty($filters['end_date'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['end_date'];
            }

            // Enhanced inventory system filtering
            if (!empty($filters['inventory_actions'])) {
                $actionPlaceholders = str_repeat('?,', count($filters['inventory_actions']) - 1) . '?';
                $where[] = "action IN ($actionPlaceholders)";
                $params = array_merge($params, $filters['inventory_actions']);
            }

            if (!empty($filters['inventory_entities'])) {
                $inventoryEntityTypes = ['product', 'inventory', 'stock_adjustment', 'purchase_order', 'supplier', 'grn'];
                $entityPlaceholders = str_repeat('?,', count($inventoryEntityTypes) - 1) . '?';
                $where[] = "entity_type IN ($entityPlaceholders)";
                $params = array_merge($params, $inventoryEntityTypes);
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT
                    al.*,
                    COALESCE(u.full_name, al.username, 'System') as username
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching audit logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of audit logs (for pagination)
     */
    public static function getLogsCount($filters = []) {
        try {
            $db = self::getDB();

            $where = [];
            $params = [];

            // Apply same filters as getLogs
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['entity_type'])) {
                $where[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }

            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['start_date'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['start_date'];
            }

            if (!empty($filters['end_date'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['end_date'];
            }

            // Enhanced inventory system filtering (same as getLogs)
            if (!empty($filters['inventory_actions'])) {
                $actionPlaceholders = str_repeat('?,', count($filters['inventory_actions']) - 1) . '?';
                $where[] = "action IN ($actionPlaceholders)";
                $params = array_merge($params, $filters['inventory_actions']);
            }

            if (!empty($filters['inventory_entities'])) {
                $inventoryEntityTypes = ['product', 'inventory', 'stock_adjustment', 'purchase_order', 'supplier', 'grn'];
                $entityPlaceholders = str_repeat('?,', count($inventoryEntityTypes) - 1) . '?';
                $where[] = "entity_type IN ($entityPlaceholders)";
                $params = array_merge($params, $inventoryEntityTypes);
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT COUNT(*) as total FROM audit_logs {$whereClause}";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error counting audit logs: " . $e->getMessage());
            return 0;
        }
    }
}

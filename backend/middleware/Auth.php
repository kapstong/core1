<?php
/**
 * Authentication Middleware
 * Session-based authentication and role-based access control
 */

class Auth {

    /**
     * Session timeout in seconds (1 minute)
     */
    const SESSION_TIMEOUT = 60;

    /**
     * Persistent login token expiry in seconds (30 days)
     */
    const PERSISTENT_TOKEN_EXPIRY = 2592000;

    /**
     * Check if user is authenticated and session is valid
     */
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            self::startSecureSession();
        }

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return false;
        }

        // For supplier role, ensure supplier_id is set (use user_id as supplier_id for suppliers in users table)
        if ($_SESSION['user_role'] === 'supplier') {
            if (!isset($_SESSION['supplier_id'])) {
                // If supplier_id is not set, set it to user_id for suppliers stored in users table
                $_SESSION['supplier_id'] = $_SESSION['user_id'];
            }
        }

        // Check session timeout
        if (!self::validateSessionTimeout()) {
            self::logout();
            return false;
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['session_created'])) {
            $_SESSION['session_created'] = time();
        } elseif (time() - $_SESSION['session_created'] > 3600) {
            // Regenerate session ID every hour
            session_regenerate_id(true);
            $_SESSION['session_created'] = time();
        }

        return true;
    }

    /**
     * Start secure session with security settings
     */
    private static function startSecureSession() {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict for better API compatibility
        ini_set('session.cookie_path', '/');

        // Use secure cookies in production (HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        session_start();
    }

    /**
     * Validate session timeout
     */
    private static function validateSessionTimeout() {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return true;
        }

        $elapsed = time() - $_SESSION['last_activity'];

        // Check if session has timed out
        if ($elapsed > self::SESSION_TIMEOUT) {
            return false;
        }

        return true;
    }

    /**
     * Get current user ID
     */
    public static function userId() {
        if (!self::check()) {
            return null;
        }
        return $_SESSION['user_id'];
    }

    /**
     * Get current user role
     */
    public static function userRole() {
        if (!self::check()) {
            return null;
        }
        return $_SESSION['user_role'];
    }

    /**
     * Get current user data
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'is_active' => $_SESSION['is_active'] ?? 1,
            'created_at' => $_SESSION['created_at'] ?? null
        ];
    }

    /**
     * Require authentication
     */
    public static function requireAuth() {
        if (!self::check()) {
            require_once __DIR__ . '/../utils/Response.php';
            Response::unauthorized('Please login to continue');
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($allowedRoles) {
        self::requireAuth();

        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        $userRole = self::userRole();

        if (!in_array($userRole, $allowedRoles)) {
            require_once __DIR__ . '/../utils/Response.php';
            Response::forbidden('You do not have permission to access this resource');
        }
    }

    /**
     * Check if user has role
     */
    public static function hasRole($role) {
        if (!self::check()) {
            return false;
        }

        if (is_array($role)) {
            return in_array(self::userRole(), $role);
        }

        return self::userRole() === $role;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }

    /**
     * Check if user is inventory manager
     */
    public static function isInventoryManager() {
        return self::hasRole(['admin', 'inventory_manager']);
    }

    /**
     * Check if user is purchasing officer
     */
    public static function isPurchasingOfficer() {
        return self::hasRole(['admin', 'purchasing_officer']);
    }

    /**
     * Login user
     */
    public static function login($user) {
        if (session_status() === PHP_SESSION_NONE) {
            self::startSecureSession();
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        // For suppliers, use user id as supplier_id since suppliers are stored in users table
        $_SESSION['supplier_id'] = $user['supplier_id'] ?? ($user['role'] === 'supplier' ? $user['id'] : null);
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['is_active'] = $user['is_active'] ?? 1;
        $_SESSION['created_at'] = $user['created_at'] ?? null;
        $_SESSION['last_activity'] = time();
        $_SESSION['session_created'] = time();
        $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['login_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Update current user session data
     */
    public static function updateUserSession($userData) {
        if (!self::check()) {
            return false;
        }

        // Update session data with new user information
        if (isset($userData['username'])) {
            $_SESSION['username'] = $userData['username'];
        }
        if (isset($userData['email'])) {
            $_SESSION['email'] = $userData['email'];
        }
        if (isset($userData['full_name'])) {
            $_SESSION['full_name'] = $userData['full_name'];
        }
        if (isset($userData['role'])) {
            $_SESSION['user_role'] = $userData['role'];
        }
        if (isset($userData['is_active'])) {
            $_SESSION['is_active'] = $userData['is_active'];
        }

        return true;
    }

    /**
     * Logout user
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Login user with remember me functionality
     */
    public static function loginWithRemember($user, $remember = false) {
        // Perform regular login
        self::login($user);

        // If remember me is requested, create persistent token
        if ($remember) {
            try {
                self::createPersistentToken($user['id']);
            } catch (Exception $e) {
                // If persistent token creation fails (e.g., table doesn't exist),
                // just continue with regular session login
                error_log('Failed to create persistent token: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create persistent login token for user
     */
    public static function createPersistentToken($userId) {
        require_once __DIR__ . '/../config/database.php';

        $db = Database::getInstance()->getConnection();
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::PERSISTENT_TOKEN_EXPIRY);

        // Delete any existing tokens for this user
        $stmt = $db->prepare("DELETE FROM persistent_login_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Create new token
        $stmt = $db->prepare("
            INSERT INTO persistent_login_tokens
            (user_id, token, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $token,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // Set cookie (30 days)
        setcookie('remember_token', $token, [
            'expires' => time() + self::PERSISTENT_TOKEN_EXPIRY,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        return $token;
    }

    /**
     * Validate persistent login token
     */
    public static function validatePersistentToken($token) {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../models/User.php';

        if (empty($token)) {
            return false;
        }

        $db = Database::getInstance()->getConnection();

        // Get token details
        $stmt = $db->prepare("
            SELECT plt.*, u.username, u.email, u.role, u.full_name, u.is_active
            FROM persistent_login_tokens plt
            JOIN users u ON plt.user_id = u.id
            WHERE plt.token = ? AND plt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        // Check if user is still active
        if (!$result['is_active']) {
            // Clean up expired/invalid token
            $stmt = $db->prepare("DELETE FROM persistent_login_tokens WHERE token = ?");
            $stmt->execute([$token]);
            return false;
        }

        // Update last used timestamp
        $stmt = $db->prepare("
            UPDATE persistent_login_tokens
            SET last_used_at = NOW(), ip_address = ?, user_agent = ?
            WHERE token = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $token
        ]);

        // Create user array for login
        $user = [
            'id' => $result['user_id'],
            'username' => $result['username'],
            'email' => $result['email'],
            'role' => $result['role'],
            'full_name' => $result['full_name'],
            'is_active' => $result['is_active']
        ];

        // Perform login
        self::login($user);

        return true;
    }

    /**
     * Check for persistent login on page load
     */
    public static function checkPersistentLogin() {
        // Only check if not already authenticated and remember token exists
        if (self::check() || !isset($_COOKIE['remember_token'])) {
            return false;
        }

        return self::validatePersistentToken($_COOKIE['remember_token']);
    }

    /**
     * Clear persistent login token
     */
    public static function clearPersistentToken() {
        if (isset($_COOKIE['remember_token'])) {
            require_once __DIR__ . '/../config/database.php';

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM persistent_login_tokens WHERE token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);

            // Clear cookie
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }

    /**
     * Logout with persistent token cleanup
     */
    public static function logoutWithRememberCleanup() {
        self::clearPersistentToken();
        self::logout();
    }
}

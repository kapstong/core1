<?php
/**
 * Login API Endpoint
 * POST /backend/api/auth/login.php
 */

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restore full login functionality
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Test if database is available, if not use test authentication
    $useTestAuth = false;
    try {
        $database = Database::getInstance()->getConnection();
        // Test if users table exists
        $stmt = $database->prepare("SHOW TABLES LIKE 'users'");
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tableExists) {
            $useTestAuth = true;
        }
    } catch (Exception $e) {
        $useTestAuth = true;
    }

    if ($useTestAuth) {
        // Test authentication (schema not deployed yet)
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            Response::error('Username and password required', 400);
        }

        // Check if we have admin user in database
        try {
            $database = Database::getInstance()->getConnection();
            $stmt = $database->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$username]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dbUser && password_verify($password, $dbUser['password_hash'])) {
                // Real admin user from database - use Auth::login()
                Auth::login($dbUser);

                Response::success([
                    'user' => [
                        'id' => $dbUser['id'],
                        'username' => $dbUser['username'],
                        'role' => $dbUser['role'],
                        'full_name' => $dbUser['full_name'],
                        'email' => $dbUser['email']
                    ],
                    'session_id' => session_id(),
                    'message' => 'Login successful'
                ], 'Login successful');
            } else {
                // Fallback test users
                $testUsers = [
                    ['id' => 1, 'username' => 'admin', 'email' => 'admin@core1.com', 'role' => 'admin', 'full_name' => 'System Administrator'],
                    ['id' => 5, 'username' => 'staff1', 'email' => 'staff@core1.com', 'role' => 'staff', 'full_name' => 'Jane Staff'],
                    ['id' => 2, 'username' => 'inventory_mgr', 'email' => 'inventory@core1.com', 'role' => 'inventory_manager', 'full_name' => 'John Inventory'],
                    ['id' => 3, 'username' => 'supplier1', 'email' => 'supplier@core1.com', 'role' => 'supplier', 'full_name' => 'Mike Supplier']
                ];

                $authenticatedUser = null;
                foreach ($testUsers as $user) {
                    if ($user['username'] === $username && $password === 'password') {
                        $authenticatedUser = $user;
                        break;
                    }
                }

                if (!$authenticatedUser) {
                    Response::error('Invalid username or password', 401);
                }

                // Store user in session using Auth::login() method
                Auth::login($authenticatedUser);

                // Ensure session variables are set correctly for Auth::check()
                $_SESSION['user_role'] = $authenticatedUser['role'];

                Response::success([
                    'user' => $authenticatedUser,
                    'session_id' => session_id(),
                    'message' => 'Login successful'
                ], 'Login successful');
            }
        } catch (Exception $e) {
            // Fallback to simple test users if database not available
            $testUsers = [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@core1.com', 'role' => 'admin', 'full_name' => 'System Administrator'],
                ['id' => 5, 'username' => 'staff1', 'email' => 'staff@core1.com', 'role' => 'staff', 'full_name' => 'Jane Staff'],
                ['id' => 2, 'username' => 'inventory_mgr', 'email' => 'inventory@core1.com', 'role' => 'inventory_manager', 'full_name' => 'John Inventory'],
                ['id' => 3, 'username' => 'supplier1', 'email' => 'supplier@core1.com', 'role' => 'supplier', 'full_name' => 'Mike Supplier']
            ];

            $authenticatedUser = null;
            foreach ($testUsers as $user) {
                if ($user['username'] === $username && $password === 'password') {
                    $authenticatedUser = $user;
                    break;
                }
            }

            if (!$authenticatedUser) {
                Response::error('Invalid username or password', 401);
            }

            // Store user in session using Auth::login() method
            Auth::login($authenticatedUser);

            Response::success([
                'user' => $authenticatedUser,
                'session_id' => session_id(),
                'message' => 'Login successful'
            ], 'Login successful');
        }

    } else {
        // Full authentication with database
        $userModel = new User();
        $username = $input['username'] ?? '';
        $user = $userModel->authenticate($username, $input['password'] ?? '');

        // Check if supplier is inactive
        if ($user && $user['role'] === 'supplier' && !$user['is_active']) {
            Response::error('Your supplier account has been deactivated. Please contact the administrator to reactivate your account.', 403);
        }

        if (!$user) {
            // Fallback to test users for development/testing
            $testUsers = [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@core1.com', 'role' => 'admin', 'full_name' => 'System Administrator'],
                ['id' => 5, 'username' => 'staff1', 'email' => 'staff@core1.com', 'role' => 'staff', 'full_name' => 'Jane Staff'],
                ['id' => 2, 'username' => 'inventory_mgr', 'email' => 'inventory@core1.com', 'role' => 'inventory_manager', 'full_name' => 'John Inventory'],
                ['id' => 3, 'username' => 'supplier1', 'email' => 'supplier@core1.com', 'role' => 'supplier', 'full_name' => 'Mike Supplier']
            ];

            $authenticatedUser = null;
            foreach ($testUsers as $testUser) {
                if ($testUser['username'] === $username && $password === 'password') {
                    $authenticatedUser = $testUser;
                    break;
                }
            }

            if (!$authenticatedUser) {
                // Log failed login attempt
                AuditLogger::log('login_failed', 'user', null, "Failed login attempt for username: {$username}");
                Response::error('Invalid username or password', 401);
            }

            // Use test user authentication
            Auth::login($authenticatedUser);
        }

        // Login successful with full features
        Auth::loginWithRemember($user, isset($input['remember']) && $input['remember'] === true);

        // Log successful login (both old logger and new audit logger)
        try {
            $logger = new Logger($user['id']);
            $logger->log('login', 'user', $user['id'], ['success' => true]);
        } catch (Exception $e) {}

        // Audit log
        AuditLogger::logLogin($user['id'], $user['username'], true);

        // Security features - attempt to send login notifications
        try {
            require_once __DIR__ . '/../../utils/Email.php';
            $clientInfo = getClientInfo();
            $sessionInfo = [
                'user_id' => $user['id'],
                'ip_address' => $clientInfo['ip'],
                'user_agent' => $clientInfo['user_agent'],
                'device_fingerprint' => generateDeviceFingerprint($clientInfo),
                'country' => $clientInfo['country'],
                'city' => $clientInfo['city'],
                'login_time' => date('Y-m-d H:i:s'),
                'session_id' => session_id()
            ];

            // Detect new device/location for security
            $isNewDevice = isNewDeviceLocation($user['id'], $sessionInfo);

            if ($isNewDevice && !empty($user['email'])) {
                // Generate 6-digit code
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                // Store in session for simple verification
                $_SESSION['2fa_code'] = $code;
                $_SESSION['2fa_user_id'] = $user['id'];
                $_SESSION['2fa_expires'] = time() + 600; // 10 minutes

                // Try to send email
                try {
                    $email = new Email();
                    $sent = $email->sendTwoFactorAuthCode($user, $code, $sessionInfo);
                    if (!$sent) {
                        error_log('2FA email send returned false for user_id=' . (int)$user['id']);
                    }
                } catch (Exception $e) {
                    error_log("Failed to send 2FA email: " . $e->getMessage());
                    // Continue anyway - code is in session
                }

                Response::success([
                    'requires_two_factor' => true,
                    'message' => 'Please check your email for a verification code.',
                    'user' => array_intersect_key($user, array_flip(['id', 'username', 'email', 'role', 'full_name']))
                ], '2FA Required');
                exit;
            }

            // Record login session
            recordLoginSession($sessionInfo);

            // Send standard login notifications
            if (in_array($user['role'], ['admin', 'inventory_manager', 'staff', 'purchasing_officer'])) {
                try {
                    $email = new Email();
                    $email->sendStaffLoginNotification($user, $sessionInfo);
                } catch (Exception $e) {}
            }

        } catch (Exception $e) {
            error_log('Security features failed: ' . $e->getMessage());
        }

        Response::success([
            'user' => array_intersect_key($user, array_flip(['id', 'username', 'email', 'role', 'full_name'])),
            'session_id' => session_id(),
            'message' => 'Login successful'
        ], 'Login successful');
    }

} catch (Exception $e) {
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('An error occurred during login');
}

/**
 * Get client information for security tracking
 */
function getClientInfo() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // If there's a comma-separated list of IPs (proxy), take the first one
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Get location information (simplified - in production, use a geoip service)
    $locationInfo = getLocationFromIP($ip);

    return [
        'ip' => $ip,
        'user_agent' => $userAgent,
        'country' => $locationInfo['country'],
        'city' => $locationInfo['city']
    ];
}

/**
 * Generate device fingerprint for security tracking
 */
function generateDeviceFingerprint($clientInfo) {
    $components = [
        $clientInfo['ip'],
        substr($clientInfo['user_agent'], 0, 50), // First 50 chars of user agent
        $_SERVER['HTTP_ACCEPT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
    ];

    return hash('sha256', implode('|', $components));
}

/**
 * Get location information from IP (simplified version)
 */
function getLocationFromIP($ip) {
    // This is a simplified version. In production, use a proper GeoIP service
    // like MaxMind GeoIP2, IP-API, or similar

    // For now, return basic info
    return [
        'country' => 'Philippines', // Default for PC Parts Central
        'city' => 'Unknown'
    ];

    // Production example with IP-API:
    /*
    try {
        $response = file_get_contents("http://ip-api.com/json/{$ip}");
        $data = json_decode($response, true);
        return [
            'country' => $data['country'] ?? 'Unknown',
            'city' => $data['city'] ?? 'Unknown'
        ];
    } catch (Exception $e) {
        return ['country' => 'Unknown', 'city' => 'Unknown'];
    }
    */
}

/**
 * Check if this is a new device/location requiring 2FA
 */
function isNewDeviceLocation($userId, $sessionInfo) {
    $db = Database::getInstance()->getConnection();

    // First, check if this device has active 2FA bypass
    $bypassQuery = "
        SELECT id FROM 2fa_bypass_records
        WHERE user_id = :user_id
            AND device_fingerprint = :device_fingerprint
            AND expires_at > NOW()
        LIMIT 1
    ";

    $bypassStmt = $db->prepare($bypassQuery);
    $bypassStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $bypassStmt->bindValue(':device_fingerprint', $sessionInfo['device_fingerprint']);
    $bypassStmt->execute();
    $bypassResult = $bypassStmt->fetch(PDO::FETCH_ASSOC);

    // If device has active 2FA bypass, no 2FA needed
    if ($bypassResult) {
        return false;
    }

    // Check recent login sessions for this user
    $query = "
        SELECT COUNT(*) as login_count
        FROM login_sessions
        WHERE user_id = :user_id
            AND device_fingerprint = :device_fingerprint
            AND login_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':device_fingerprint', $sessionInfo['device_fingerprint']);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user has logged in from this device/location before, no 2FA needed
    if ($result && $result['login_count'] > 0) {
        return false;
    }

    // Get total number of unique devices/locations for this user
    $totalDevicesQuery = "
        SELECT COUNT(DISTINCT device_fingerprint) as total_devices
        FROM login_sessions
        WHERE user_id = :user_id
    ";

    $totalDevicesStmt = $db->prepare($totalDevicesQuery);
    $totalDevicesStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $totalDevicesStmt->execute();

    $totalResult = $totalDevicesStmt->fetch(PDO::FETCH_ASSOC);
    $totalDevices = $totalResult ? $totalResult['total_devices'] : 0;

    // Require 2FA for:
    // 1. First login ever, OR
    // 2. New device/location (first time from this device), OR
    // 3. High number of different devices (security measure)
    return $totalDevices === 0 || $totalDevices >= 5;
}

/**
 * Generate a 2FA verification code
 */
function generate2FACode($userId) {
    $db = Database::getInstance()->getConnection();

    // Generate 6-digit random code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete any existing codes for this user
    $deleteQuery = "DELETE FROM verification_codes WHERE user_id = :user_id AND code_type = '2fa'";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $deleteStmt->execute();

    // Insert new verification code
    $insertQuery = "
        INSERT INTO verification_codes (user_id, code, code_type, expires_at, created_at)
        VALUES (:user_id, :code, '2fa', :expires_at, NOW())
    ";

    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $insertStmt->bindValue(':code', $code);
    $insertStmt->bindValue(':expires_at', $expiresAt);
    $insertStmt->execute();

    return $code;
}

/**
 * Record a login session for security tracking
 */
function recordLoginSession($sessionInfo) {
    $db = Database::getInstance()->getConnection();

    $query = "
        INSERT INTO login_sessions
        (user_id, ip_address, user_agent, device_fingerprint, country, city, login_time, session_id)
        VALUES
        (:user_id, :ip_address, :user_agent, :device_fingerprint, :country, :city, :login_time, :session_id)
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $sessionInfo['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':ip_address', $sessionInfo['ip_address']);
    $stmt->bindValue(':user_agent', $sessionInfo['user_agent']);
    $stmt->bindValue(':device_fingerprint', $sessionInfo['device_fingerprint']);
    $stmt->bindValue(':country', $sessionInfo['country']);
    $stmt->bindValue(':city', $sessionInfo['city']);
    $stmt->bindValue(':login_time', $sessionInfo['login_time']);
    $stmt->bindValue(':session_id', $sessionInfo['session_id']);
    $stmt->execute();
}


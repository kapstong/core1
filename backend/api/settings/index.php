<?php
/**
 * System Settings API Endpoint
 * GET /backend/api/settings/index.php - Get all settings or specific setting
 * POST /backend/api/settings/index.php - Update setting(s)
 * PUT /backend/api/settings/index.php - Update setting(s)
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

// Get HTTP method first
$method = $_SERVER['REQUEST_METHOD'];

// Parse input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Store input globally for use in functions
global $requestInput;
$requestInput = $input;

// Special case: Allow maintenance mode updates even when database is down
$isMaintenanceModeUpdate = isset($input['maintenance_mode']) && count($input) === 1;

if ($isMaintenanceModeUpdate) {
    // Update database FIRST (before file update which sends response and exits)
    try {
        $db = Database::getInstance()->getConnection();

        // Update maintenance_mode in database
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, category, description)
            VALUES ('maintenance_mode', :value, 'shop', 'Enable/disable maintenance mode')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([':value' => $input['maintenance_mode']]);

        // Also update maintenance_message if provided
        if (isset($input['maintenance_message'])) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, category, description)
                VALUES ('maintenance_message', :value, 'shop', 'Message displayed when maintenance mode is enabled')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([':value' => $input['maintenance_message']]);
        }
    } catch (Exception $e) {
        // Database not available, file update is sufficient
    }

    // Then update file (this will send response and exit)
    updateFileSettings();
}

if (!$isMaintenanceModeUpdate) {
    // Check authentication for non-maintenance mode updates
    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    // Get user data
    $user = Auth::user();

    // Check if user has admin role for settings management (except for GET requests)
    if ($method !== 'GET' && !in_array($user['role'], ['admin', 'inventory_manager'])) {
        Response::error('Access denied. Admin or inventory manager role required', 403);
    }
}

// Response tracking
$responseSent = false;

try {
    switch ($method) {
        case 'GET':
            getSettings();
            $responseSent = true;
            break;

        case 'POST':
        case 'PUT':
            updateSettings();
            $responseSent = true;
            break;

        default:
            Response::error('Method not allowed', 405);
            $responseSent = true;
    }

} catch (Exception $e) {
    // Fallback to file-based settings if database fails
    if (!$responseSent) {
        try {
            switch ($method) {
                case 'GET':
                    getFileSettings();
                    $responseSent = true;
                    break;
                case 'POST':
                case 'PUT':
                    updateFileSettings();
                    $responseSent = true;
                    break;
                default:
                    Response::error('Method not allowed', 405);
                    $responseSent = true;
            }
        } catch (Exception $fallbackError) {
            if (!$responseSent) {
                Response::serverError('Settings operation failed: ' . $e->getMessage());
                $responseSent = true;
            }
        }
    }
}

// Final safeguard - ensure we only send one response
if (!$responseSent && !headers_sent()) {
    Response::serverError('No response was sent');
}

function getSettings() {
    $db = Database::getInstance()->getConnection();

    $settingKey = isset($_GET['key']) ? trim($_GET['key']) : null;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;

    if ($settingKey) {
        // Get specific setting
        $query = "SELECT * FROM settings WHERE setting_key = :key";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':key', $settingKey);
        $stmt->execute();

        $setting = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setting) {
            Response::error('Setting not found', 404);
        }

        // Parse JSON values if needed
        $setting['parsed_value'] = parseSettingValue($setting['setting_value']);

        Response::success(['setting' => $setting]);
    } else {
        // Get all settings, optionally filtered by category
        $query = "SELECT * FROM settings WHERE 1=1";
        $params = [];

        if ($category) {
            $query .= " AND category = :category";
            $params[':category'] = $category;
        }

        $query .= " ORDER BY category ASC, setting_key ASC";

        $stmt = $db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by category
        $groupedSettings = [];
        foreach ($settings as $setting) {
            $setting['parsed_value'] = parseSettingValue($setting['setting_value']);
            $cat = $setting['category'] ?: 'general';
            if (!isset($groupedSettings[$cat])) {
                $groupedSettings[$cat] = [];
            }
            $groupedSettings[$cat][] = $setting;
        }

        Response::success([
            'settings' => $settings,
            'grouped_settings' => $groupedSettings,
            'categories' => array_keys($groupedSettings)
        ]);
    }
}

function updateSettings() {
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (empty($input)) {
        Response::error('No settings data provided', 400);
    }

    // Support both single setting update and bulk update
    $settingsToUpdate = [];

    if (isset($input['setting_key']) && isset($input['setting_value'])) {
        // Single setting update
        $settingsToUpdate[] = [
            'key' => $input['setting_key'],
            'value' => $input['setting_value'],
            'description' => $input['description'] ?? null,
            'category' => $input['category'] ?? null
        ];
    } elseif (isset($input['settings']) && is_array($input['settings'])) {
        // Bulk update
        $settingsToUpdate = $input['settings'];
    } else {
        // Assume direct key-value pairs
        foreach ($input as $key => $value) {
            if ($key !== 'settings') {
                $settingsToUpdate[] = [
                    'key' => $key,
                    'value' => $value
                ];
            }
        }
    }

    if (empty($settingsToUpdate)) {
        Response::error('No valid settings to update', 400);
    }

    $db->beginTransaction();

    try {
        $updatedSettings = [];

        foreach ($settingsToUpdate as $setting) {
            if (!isset($setting['key']) || !isset($setting['value'])) {
                continue;
            }

            $settingKey = trim($setting['key']);
            $settingValue = $setting['value']; // Keep as-is, will be stored as string
            $description = isset($setting['description']) ? trim($setting['description']) : null;
            $category = isset($setting['category']) ? trim($setting['category']) : null;

            // Check if setting exists
            $checkQuery = "SELECT id FROM settings WHERE setting_key = :key";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':key', $settingKey);
            $checkStmt->execute();

            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing setting
                $updateQuery = "UPDATE settings SET setting_value = :value";
                $params = [':value' => $settingValue, ':key' => $settingKey];

                if ($description !== null) {
                    $updateQuery .= ", description = :description";
                    $params[':description'] = $description;
                }

                if ($category !== null) {
                    $updateQuery .= ", category = :category";
                    $params[':category'] = $category;
                }

                $updateQuery .= " WHERE setting_key = :key";

                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute($params);
            } else {
                // Create new setting
                $insertQuery = "INSERT INTO settings (setting_key, setting_value, description, category)
                               VALUES (:key, :value, :description, :category)";

                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(':key', $settingKey);
                $insertStmt->bindParam(':value', $settingValue);
                $insertStmt->bindParam(':description', $description);
                $insertStmt->bindValue(':category', $category ?: 'general');
                $insertStmt->execute();
            }

            $updatedSettings[] = [
                'key' => $settingKey,
                'value' => $settingValue,
                'description' => $description,
                'category' => $category,
                'parsed_value' => parseSettingValue($settingValue)
            ];
        }

        $db->commit();

        Response::success([
            'message' => 'Settings updated successfully',
            'updated_settings' => $updatedSettings,
            'count' => count($updatedSettings)
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function parseSettingValue($value) {
    if ($value === null || $value === '') {
        return null;
    }

    // Try to parse as JSON first
    $jsonValue = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $jsonValue;
    }

    // Try to parse as boolean
    if (strtolower($value) === 'true') {
        return true;
    }
    if (strtolower($value) === 'false') {
        return false;
    }

    // Try to parse as number
    if (is_numeric($value)) {
        return strpos($value, '.') !== false ? (float)$value : (int)$value;
    }

    // Return as string
    return $value;
}

// Initialize default settings if they don't exist
function initializeDefaultSettings($db) {
    $defaultSettings = [
        // System settings
        ['key' => 'system_name', 'value' => 'PC Parts Merchandising System', 'description' => 'Name of the system', 'category' => 'system'],
        ['key' => 'system_version', 'value' => '1.0', 'description' => 'Current system version', 'category' => 'system'],
        ['key' => 'timezone', 'value' => 'UTC', 'description' => 'System timezone', 'category' => 'system'],

        // Tax settings
        ['key' => 'tax_rate', 'value' => '0.08', 'description' => 'Default tax rate (8%)', 'category' => 'tax'],
        ['key' => 'tax_inclusive', 'value' => 'false', 'description' => 'Whether tax is included in prices', 'category' => 'tax'],

        // Inventory settings
        ['key' => 'default_reorder_level', 'value' => '10', 'description' => 'Default reorder level for products', 'category' => 'inventory'],
        ['key' => 'low_stock_threshold', 'value' => '5', 'description' => 'Low stock alert threshold', 'category' => 'inventory'],
        ['key' => 'auto_update_inventory', 'value' => 'true', 'description' => 'Automatically update inventory on sales', 'category' => 'inventory'],

        // Shop settings
        ['key' => 'shop_enabled', 'value' => 'true', 'description' => 'Enable/disable online shop', 'category' => 'shop'],
        ['key' => 'guest_checkout', 'value' => 'true', 'description' => 'Allow guest checkout', 'category' => 'shop'],
        ['key' => 'free_shipping_threshold', 'value' => '100.00', 'description' => 'Free shipping threshold', 'category' => 'shop'],
        ['key' => 'default_shipping_cost', 'value' => '10.00', 'description' => 'Default shipping cost', 'category' => 'shop'],
        ['key' => 'maintenance_mode', 'value' => 'false', 'description' => 'Enable/disable maintenance mode', 'category' => 'shop'],
        ['key' => 'maintenance_message', 'value' => 'We are currently performing scheduled maintenance. Please check back later.', 'description' => 'Message displayed when maintenance mode is enabled', 'category' => 'shop'],

        // Email settings
        ['key' => 'smtp_host', 'value' => '', 'description' => 'SMTP server hostname', 'category' => 'email'],
        ['key' => 'smtp_port', 'value' => '587', 'description' => 'SMTP server port', 'category' => 'email'],
        ['key' => 'smtp_username', 'value' => '', 'description' => 'SMTP authentication username', 'category' => 'email'],
        ['key' => 'smtp_password', 'value' => '', 'description' => 'SMTP authentication password', 'category' => 'email'],
        ['key' => 'email_from', 'value' => 'noreply@pcparts.com', 'description' => 'Default from email address', 'category' => 'email'],

        // Payment settings
        ['key' => 'currency', 'value' => 'PHP', 'description' => 'System currency', 'category' => 'payment'],
        ['key' => 'currency_symbol', 'value' => '₱', 'description' => 'Currency symbol', 'category' => 'payment'],
        ['key' => 'paypal_enabled', 'value' => 'false', 'description' => 'Enable PayPal payments', 'category' => 'payment'],
        ['key' => 'stripe_enabled', 'value' => 'false', 'description' => 'Enable Stripe payments', 'category' => 'payment'],

        // Security settings
        ['key' => 'inactivity_timeout', 'value' => '30', 'description' => 'Auto-logout after inactivity (minutes, 0 = disabled)', 'category' => 'security'],
    ];

    foreach ($defaultSettings as $setting) {
        // Check if setting already exists
        $checkQuery = "SELECT id FROM settings WHERE setting_key = :key";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':key', $setting['key']);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            // Insert default setting
            $insertQuery = "INSERT INTO settings (setting_key, setting_value, description, category)
                           VALUES (:key, :value, :description, :category)";

            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bindValue(':key', $setting['key']);
            $insertStmt->bindValue(':value', $setting['value']);
            $insertStmt->bindValue(':description', $setting['description']);
            $insertStmt->bindValue(':category', $setting['category']);
            $insertStmt->execute();
        }
    }
}

// File-based settings functions for fallback
function getFileSettings() {
    $settingsFile = __DIR__ . '/../../data/settings.json';

    // Create default settings if file doesn't exist
    if (!file_exists($settingsFile)) {
        createDefaultSettingsFile($settingsFile);
    }

    $settings = json_decode(file_get_contents($settingsFile), true);

    if ($settings === null) {
        Response::error('Invalid settings file', 500);
    }

    $settingKey = isset($_GET['key']) ? trim($_GET['key']) : null;

    if ($settingKey) {
        // Get specific setting
        if (!isset($settings[$settingKey])) {
            Response::error('Setting not found', 404);
        }

        Response::success([
            'setting' => [
                'setting_key' => $settingKey,
                'setting_value' => $settings[$settingKey],
                'parsed_value' => parseSettingValue($settings[$settingKey])
            ]
        ]);
    } else {
        // Get all settings
        $settingsArray = [];
        foreach ($settings as $key => $value) {
            $settingsArray[] = [
                'setting_key' => $key,
                'setting_value' => $value,
                'parsed_value' => parseSettingValue($value),
                'category' => getSettingCategory($key)
            ];
        }

        Response::success([
            'settings' => $settingsArray,
            'grouped_settings' => [],
            'categories' => []
        ]);
    }
}

function updateFileSettings() {
    global $requestInput;

    $settingsFile = __DIR__ . '/../../data/settings.json';

    // Create default settings if file doesn't exist
    if (!file_exists($settingsFile)) {
        createDefaultSettingsFile($settingsFile);
    }

    $currentSettings = json_decode(file_get_contents($settingsFile), true);

    if ($currentSettings === null) {
        Response::error('Invalid settings file', 500);
    }

    $input = $requestInput;

    if (empty($input)) {
        Response::error('No settings data provided', 400);
    }

    // Update settings
    $updatedSettings = [];
    foreach ($input as $key => $value) {
        if ($key !== 'settings') {
            $currentSettings[$key] = $value;
            $updatedSettings[] = [
                'key' => $key,
                'value' => $value,
                'parsed_value' => parseSettingValue($value)
            ];
        }
    }

    // Debug: Force maintenance_mode update
    if (isset($input['maintenance_mode'])) {
        $currentSettings['maintenance_mode'] = $input['maintenance_mode'];
    }

    // Save to file
    if (file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT)) === false) {
        Response::error('Failed to save settings', 500);
    }

    Response::success([
        'message' => 'Settings updated successfully',
        'updated_settings' => $updatedSettings,
        'count' => count($updatedSettings)
    ]);
}

function createDefaultSettingsFile($filePath) {
    $defaultSettings = [
        // System settings
        'system_name' => 'PC Parts Central',
        'system_version' => '1.0',
        'timezone' => 'UTC',
        'currency' => 'PHP',
        'currency_symbol' => '₱',

        // Store information
        'store_name' => 'PC Parts Central',
        'store_email' => '',
        'store_phone' => '',
        'store_address' => '',

        // Tax settings
        'tax_rate' => '0',

        // Shop settings
        'shop_enabled' => 'true',
        'guest_checkout' => 'true',
        'free_shipping_threshold' => '100',
        'default_shipping_cost' => '10',
        'maintenance_mode' => 'false',
        'maintenance_message' => 'We are currently performing scheduled maintenance. Please check back later.',

        // Security settings
        'enable_2fa' => 'false',
        'session_timeout' => 'false',
        'password_policy' => 'basic',
        'inactivity_timeout' => '30', // Minutes before auto-logout due to inactivity

        // Email settings
        'email_from' => 'noreply@pcparts.com'
    ];

    // Ensure maintenance settings are always present
    if (!isset($defaultSettings['maintenance_mode'])) {
        $defaultSettings['maintenance_mode'] = 'false';
    }
    if (!isset($defaultSettings['maintenance_message'])) {
        $defaultSettings['maintenance_message'] = 'We are currently performing scheduled maintenance. Please check back later.';
    }

    // Create data directory if it doesn't exist
    $dataDir = dirname($filePath);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    file_put_contents($filePath, json_encode($defaultSettings, JSON_PRETTY_PRINT));
}

function getSettingCategory($key) {
    $categories = [
        'system' => ['system_name', 'system_version', 'timezone', 'currency', 'currency_symbol'],
        'store' => ['store_name', 'store_email', 'store_phone', 'store_address'],
        'tax' => ['tax_rate'],
        'shop' => ['shop_enabled', 'guest_checkout', 'free_shipping_threshold', 'default_shipping_cost'],
        'security' => ['enable_2fa', 'session_timeout', 'password_policy', 'inactivity_timeout'],
        'email' => ['email_from']
    ];

    foreach ($categories as $category => $keys) {
        if (in_array($key, $keys)) {
            return $category;
        }
    }

    return 'general';
}

// Initialize default settings on first access (only if database is available)
// Only run initialization on GET requests to avoid conflicts during updates
if ($method === 'GET') {
    try {
        $db = Database::getInstance()->getConnection();
        initializeDefaultSettings($db);
    } catch (Exception $e) {
        // Database not available, will use file-based settings
    }
}

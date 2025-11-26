<?php
/**
 * Reset Settings API Endpoint
 * POST /backend/api/settings/reset.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Check authentication and admin role
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();
if ($user['role'] !== 'admin') {
    Response::error('Forbidden - Admin access required', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $settingKey = isset($input['setting_key']) ? $input['setting_key'] : null;

    $db = Database::getInstance()->getConnection();

    // Default settings
    $defaultSettings = [
        'site_name' => 'PC Parts Central',
        'site_url' => 'http://localhost/core1',
        'currency' => 'USD',
        'tax_rate' => '0.08',
        'po_auto_number' => 'PO-',
        'grn_auto_number' => 'GRN-',
        'invoice_auto_number' => 'INV-',
        'order_auto_number' => 'ORD-',
        '2fa_required' => '0',
        'max_failed_logins' => '5',
        'lockout_duration' => '30'
    ];

    if ($settingKey && isset($defaultSettings[$settingKey])) {
        // Reset specific setting
        $stmt = $db->prepare("UPDATE settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key");
        $stmt->execute([
            'key' => $settingKey,
            'value' => $defaultSettings[$settingKey]
        ]);

        Response::success([
            'setting_key' => $settingKey,
            'setting_value' => $defaultSettings[$settingKey]
        ], 'Setting reset to default successfully');

    } else if ($settingKey === 'all') {
        // Reset all settings to defaults
        $resetCount = 0;
        foreach ($defaultSettings as $key => $value) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key");
            $stmt->execute([
                'key' => $key,
                'value' => $value
            ]);
            $resetCount++;
        }

        Response::success([
            'reset_count' => $resetCount
        ], 'All settings reset to defaults successfully');

    } else {
        Response::error('Invalid setting key specified');
    }

} catch (Exception $e) {
    error_log("Reset Settings Error: " . $e->getMessage());
    Response::error('An error occurred while resetting settings: ' . $e->getMessage(), 500);
}

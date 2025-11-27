<?php
// Debug version to identify the issue
header('Content-Type: application/json');

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$debug = [
    'step' => '',
    'error' => null,
    'session_status' => session_status(),
    'php_version' => PHP_VERSION,
    'session_data' => []
];

try {
    $debug['step'] = 'Starting';

    // Step 1: Check if files exist
    $debug['step'] = 'Checking files';
    $configPath = __DIR__ . '/../../config/database.php';
    $authPath = __DIR__ . '/../../middleware/Auth.php';
    $debug['config_exists'] = file_exists($configPath);
    $debug['auth_exists'] = file_exists($authPath);

    // Step 2: Include files
    $debug['step'] = 'Including database.php';
    require_once $configPath;

    $debug['step'] = 'Including auth.php';
    require_once $authPath;

    // Step 3: Start session
    $debug['step'] = 'Starting session';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $debug['session_started'] = true;
    $debug['session_id'] = session_id();

    // Step 4: Check session data
    $debug['step'] = 'Checking session';
    $debug['session_data'] = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['user_role'] ?? null,
        'username' => $_SESSION['username'] ?? null
    ];

    // Step 5: Check Auth class
    $debug['step'] = 'Checking Auth class';
    $debug['auth_class_exists'] = class_exists('Auth');

    // Step 6: Try Auth::user()
    $debug['step'] = 'Calling Auth::user()';
    $user = Auth::user();
    $debug['user_result'] = $user;

    $debug['step'] = 'Complete';
    $debug['success'] = true;

} catch (Exception $e) {
    $debug['error'] = $e->getMessage();
    $debug['trace'] = $e->getTraceAsString();
} catch (Error $e) {
    $debug['error'] = $e->getMessage();
    $debug['trace'] = $e->getTraceAsString();
}

echo json_encode($debug, JSON_PRETTY_PRINT);

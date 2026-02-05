<?php
/**
 * Current User API Endpoint
 * GET /backend/api/auth/me.php
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

// Include required files
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';

CORS::handle();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Check if user is authenticated
    file_put_contents(__DIR__ . '/../../logs/debug.log', "Calling Auth::check()\n", FILE_APPEND);

    if (Auth::check()) {
        // Get current user data
        $user = Auth::user();
        file_put_contents(__DIR__ . '/../../logs/debug.log', "Auth successful, returning user data\n", FILE_APPEND);
        Response::success($user, 'User data retrieved successfully');
    } else {
        // Check if user is authenticated as customer
        if (isset($_SESSION['customer_id'])) {
            // User is authenticated as customer, redirect to login.php
            file_put_contents(__DIR__ . '/../../logs/debug.log', "Customer authentication found, redirecting to login.php\n", FILE_APPEND);
            Response::success([
                'authenticated' => false,
                'redirect' => 'login.php'
            ], 'Customer user not authenticated as staff');
        } else {
            // Regular staff not authenticated
            file_put_contents(__DIR__ . '/../../logs/debug.log', "Staff authentication failed, redirecting to simple-login.php\n", FILE_APPEND);
            Response::success([
                'authenticated' => false,
                'redirect' => 'simple-login.php'
            ], 'User not authenticated');
        }
    }

} catch (Exception $e) {
    file_put_contents(__DIR__ . '/../../logs/debug.log', "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    Response::serverError('An error occurred');
}


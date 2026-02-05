<?php
/**
 * Users API - Get user activity logs
 * GET /backend/api/users/activity.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require authentication
$user = Auth::requireAuth();

// Get current user data
$currentUser = Auth::user();

// Check if requesting another user's activity
$requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Determine whose activity to fetch
if ($requestedUserId && $requestedUserId !== $currentUser['id']) {
    // Only admins can view other users' activity
    if ($currentUser['role'] !== 'admin') {
        Response::error('Access denied. Admin privileges required to view other users\' activity.', 403);
        exit();
    }
    $userId = $requestedUserId;
} else {
    $userId = $currentUser['id'];
}

try {
    $db = Database::getInstance()->getConnection();
    $logger = new Logger();

    // Get limit from query parameter, default to 10
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min(50, $limit)); // Clamp between 1 and 50

    // Get recent activities for the specified user
    $activities = $logger->getRecentActivities($limit, $userId);

    // For debugging - force an error to test fallback
    // throw new Exception("Test error for debugging");

    // Format activities for frontend
    $formattedActivities = array_map(function($activity) {
        // Format action descriptions
        $actionText = formatActivityAction($activity['action'], $activity['entity_type'], $activity['details']);

        // Format timestamp
        $timestamp = strtotime($activity['created_at']);
        $formattedTime = date('M j, Y g:i A', $timestamp);
        $relativeTime = getRelativeTime($timestamp);

        return [
            'id' => $activity['id'],
            'action' => $activity['action'],
            'entity_type' => $activity['entity_type'],
            'entity_id' => $activity['entity_id'],
            'action_text' => $actionText,
            'timestamp' => $formattedTime,
            'relative_time' => $relativeTime,
            'ip_address' => $activity['ip_address'],
            'details' => json_decode($activity['details'], true) ?: []
        ];
    }, $activities);

    Response::success([
        'activities' => $formattedActivities,
        'total' => count($formattedActivities)
    ]);

} catch (Exception $e) {
    Logger::logError('Activity fetch error', [
        'user_id' => $userId,
        'error' => $e->getMessage()
    ], $userId);
    Response::error('An error occurred while fetching activity logs', 500);
}

/**
 * Format activity action into human-readable text
 */
function formatActivityAction($action, $entityType, $detailsJson) {
    $details = json_decode($detailsJson, true) ?: [];

    switch ($action) {
        case 'Profile updated':
            $fields = $details['updated_fields'] ?? [];
            if (count($fields) === 1) {
                return "Updated " . formatFieldName($fields[0]);
            } elseif (count($fields) > 1) {
                return "Updated profile information";
            }
            return "Updated profile";

        case 'Password changed':
            return "Changed password";

        case 'Login':
            return "Logged into the system";

        case 'Logout':
            return "Logged out of the system";

        case 'Product created':
            return "Created new product";

        case 'Product updated':
            return "Updated product details";

        case 'Product deleted':
            return "Deleted a product";

        case 'Sale created':
            return "Completed a sale";

        case 'Purchase order created':
            return "Created purchase order";

        case 'Purchase order updated':
            return "Updated purchase order";

        case 'Supplier created':
            return "Added new supplier";

        case 'Supplier updated':
            return "Updated supplier information";

        case 'User created':
            return "Created new user account";

        case 'User updated':
            return "Updated user account";

        case 'User deleted':
            return "Deleted user account";

        case 'Stock adjusted':
            $adjustment = $details['adjustment_type'] ?? 'adjusted';
            return "Stock " . $adjustment . " for product";

        case 'Goods received':
            return "Received goods from supplier";

        default:
            // Generic fallback
            return ucfirst(str_replace('_', ' ', $action));
    }
}

/**
 * Format field names for display
 */
function formatFieldName($field) {
    $fieldNames = [
        'username' => 'username',
        'email' => 'email address',
        'full_name' => 'full name',
        'password' => 'password',
        'role' => 'role',
        'is_active' => 'account status'
    ];

    return $fieldNames[$field] ?? $field;
}

/**
 * Get relative time string
 */
function getRelativeTime($timestamp) {
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return $diff <= 1 ? 'just now' : $diff . ' seconds ago';
    }

    $minutes = floor($diff / 60);
    if ($minutes < 60) {
        return $minutes == 1 ? '1 minute ago' : $minutes . ' minutes ago';
    }

    $hours = floor($minutes / 60);
    if ($hours < 24) {
        return $hours == 1 ? '1 hour ago' : $hours . ' hours ago';
    }

    $days = floor($hours / 24);
    if ($days < 7) {
        return $days == 1 ? '1 day ago' : $days . ' days ago';
    }

    $weeks = floor($days / 7);
    if ($weeks < 4) {
        return $weeks == 1 ? '1 week ago' : $weeks . ' weeks ago';
    }

    $months = floor($days / 30);
    if ($months < 12) {
        return $months == 1 ? '1 month ago' : $months . ' months ago';
    }

    $years = floor($days / 365);
    return $years == 1 ? '1 year ago' : $years . ' years ago';
}


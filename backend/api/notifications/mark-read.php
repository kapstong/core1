<?php
/**
 * Mark Notification as Read API Endpoint
 * POST /backend/api/notifications/mark-read.php
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

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Mark single notification as read
    if (isset($input['notification_id'])) {
        $notificationId = intval($input['notification_id']);

        // Verify notification belongs to user
        $stmt = $conn->prepare("
            SELECT id FROM notifications
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $user['id']
        ]);

        if (!$stmt->fetch()) {
            Response::error('Notification not found', 404);
        }

        // Mark as read
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $user['id']
        ]);

        Response::success(null, 'Notification marked as read');
    }

    // Mark all notifications as read
    if (isset($input['mark_all']) && $input['mark_all'] === true) {
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $user['id']]);

        $count = $stmt->rowCount();
        Response::success(['marked_count' => $count], "Marked $count notification(s) as read");
    }

    Response::error('Missing notification_id or mark_all parameter', 400);

} catch (Exception $e) {
    Response::serverError('Failed to mark notification as read: ' . $e->getMessage());
}

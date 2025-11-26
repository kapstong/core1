<?php
/**
 * Notifications API Endpoint
 * GET /backend/api/notifications/index.php - Get notifications for logged-in user
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Build query
    $query = "
        SELECT *
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 50
    ";

    // Optional filter by read status
    if (isset($_GET['unread_only']) && $_GET['unread_only'] === 'true') {
        $query = "
            SELECT *
            FROM notifications
            WHERE user_id = :user_id AND is_read = 0
            ORDER BY created_at DESC
            LIMIT 50
        ";
    }

    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = :user_id AND is_read = 0
    ");
    $countStmt->execute([':user_id' => $user['id']]);
    $countData = $countStmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'notifications' => $notifications,
        'unread_count' => (int)$countData['unread_count']
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch notifications: ' . $e->getMessage());
}

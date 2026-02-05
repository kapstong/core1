<?php
/**
 * Get Current Supplier's Profile
 * GET /backend/api/supplier/me.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

try {
    $currentUser = Auth::user();

    if ($currentUser['role'] !== 'supplier') {
        Response::error('Access denied. Supplier role required.', 403);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get supplier info from users table
    $query = "SELECT * FROM users WHERE id = :id AND role = 'supplier'";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $currentUser['id']]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        Response::error('Supplier profile not found', 404);
    }

    // Get supplier statistics (purchase orders)
    $statsQuery = "
        SELECT
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_orders,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_orders,
            SUM(CASE WHEN status = 'pending_supplier' THEN 1 ELSE 0 END) as pending_orders,
            SUM(total_amount) as total_spent
        FROM purchase_orders
        WHERE supplier_id = :supplier_id
    ";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute([':supplier_id' => $currentUser['id']]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $isActive = (bool)$supplier['is_active'];

    // Format response
    $formattedSupplier = [
        'id' => $supplier['id'],
        'code' => 'SUP-' . str_pad($supplier['id'], 5, '0', STR_PAD_LEFT),
        'name' => $supplier['full_name'] ?? $supplier['username'],
        'contact_person' => $supplier['full_name'],
        'email' => $supplier['email'],
        'phone' => $supplier['phone'] ?? '',
        'address' => $supplier['address'] ?? '',
        'payment_terms' => $supplier['payment_terms'] ?? '',
        'rating' => $supplier['rating'] ?? 0,
        'is_active' => $isActive,
        'status' => $isActive ? 'active' : 'inactive',
        'supplier_status' => $isActive ? 'approved' : 'pending_approval',
        'notes' => $supplier['notes'] ?? '',
        'created_at' => $supplier['created_at'],
        'updated_at' => $supplier['updated_at'] ?? null,
        'last_login' => $supplier['last_login'] ?? null,
        'statistics' => [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'approved_orders' => (int)($stats['approved_orders'] ?? 0),
            'draft_orders' => (int)($stats['draft_orders'] ?? 0),
            'pending_orders' => (int)($stats['pending_orders'] ?? 0),
            'total_spent' => (float)($stats['total_spent'] ?? 0)
        ]
    ];

    Response::success(['supplier' => $formattedSupplier]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch supplier profile: ' . $e->getMessage());
}


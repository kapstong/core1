<?php
/**
 * Get Single Supplier Details
 * GET /backend/api/suppliers/show.php?id={user_id}
 *
 * Simplified - uses ONLY users table
 */


// Suppress error display for clean JSON responses
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
    $supplierId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($supplierId <= 0) {
        Response::error('Supplier ID is required', 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get supplier from users table JOINED with suppliers table
    $query = "
        SELECT
            u.*,
            s.company_name,
            s.supplier_code,
            s.contact_person,
            s.phone as supplier_phone,
            s.email as supplier_email,
            s.address,
            s.city,
            s.state,
            s.postal_code,
            s.country,
            s.tax_id,
            s.payment_terms,
            s.notes as supplier_notes,
            s.created_at as supplier_created_at,
            s.updated_at as supplier_updated_at
        FROM users u
        LEFT JOIN suppliers s ON u.id = s.user_id
        WHERE u.id = :id AND u.role = 'supplier' AND u.deleted_at IS NULL
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        Response::error('Supplier not found', 404);
    }

    $isActive = (bool)$supplier['is_active'];

    // Get order statistics from purchase_orders table
    $statsQuery = "
        SELECT
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_orders,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_orders,
            COALESCE(SUM(CASE WHEN status IN ('approved', 'partially_received', 'received') THEN total_amount END), 0) as total_spent,
            MAX(CASE WHEN status IN ('approved', 'partially_received', 'received') THEN updated_at END) as last_order_date
        FROM purchase_orders
        WHERE supplier_id = :supplier_id
        AND deleted_at IS NULL
    ";

    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute([':supplier_id' => $supplierId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Format response with supplier information
    $formattedSupplier = [
        'id' => $supplier['id'],
        'user_id' => $supplier['id'],
        'code' => $supplier['supplier_code'] ?? 'SUP-' . str_pad($supplier['id'], 5, '0', STR_PAD_LEFT),
        'name' => $supplier['full_name'] ?? $supplier['username'],
        'company_name' => $supplier['company_name'] ?? $supplier['full_name'] ?? $supplier['username'],
        'username' => $supplier['username'],
        'email' => $supplier['email'] ?? $supplier['supplier_email'],
        'phone' => $supplier['supplier_phone'] ?? $supplier['phone'] ?? '',
        'contact_person' => $supplier['contact_person'] ?? $supplier['full_name'],
        'address' => $supplier['address'] ?? '',
        'city' => $supplier['city'] ?? '',
        'state' => $supplier['state'] ?? '',
        'postal_code' => $supplier['postal_code'] ?? '',
        'country' => $supplier['country'] ?? 'Philippines',
        'tax_id' => $supplier['tax_id'] ?? '',
        'payment_terms' => $supplier['payment_terms'] ?? 'Net 30',
        'notes' => $supplier['supplier_notes'] ?? '',
        'is_active' => $isActive,
        'status' => $isActive ? 'active' : 'inactive',
        'supplier_status' => $isActive ? 'approved' : 'pending_approval', // Determined by is_active
        'created_at' => $supplier['created_at'],
        'updated_at' => $supplier['updated_at'] ?? null,
        'last_login' => $supplier['last_login'] ?? null,
        'supplier_info' => [
            'company_name' => $supplier['company_name'],
            'supplier_code' => $supplier['supplier_code'],
            'contact_person' => $supplier['contact_person'],
            'phone' => $supplier['supplier_phone'],
            'email' => $supplier['supplier_email'],
            'address' => $supplier['address'],
            'city' => $supplier['city'],
            'state' => $supplier['state'],
            'postal_code' => $supplier['postal_code'],
            'country' => $supplier['country'],
            'tax_id' => $supplier['tax_id'],
            'payment_terms' => $supplier['payment_terms'],
            'notes' => $supplier['supplier_notes'],
            'created_at' => $supplier['supplier_created_at'],
            'updated_at' => $supplier['supplier_updated_at']
        ],
        'statistics' => [
            'total_orders' => (int)$stats['total_orders'],
            'approved_orders' => (int)$stats['approved_orders'],
            'draft_orders' => (int)$stats['draft_orders'],
            'total_spent' => (float)$stats['total_spent'],
            'last_order_date' => $stats['last_order_date']
        ]
    ];

    // Wrap in 'supplier' key like frontend expects
    Response::success(['supplier' => $formattedSupplier]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch supplier: ' . $e->getMessage());
}


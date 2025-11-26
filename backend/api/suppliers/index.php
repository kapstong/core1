<?php
/**
 * Suppliers API - List approved suppliers
 * GET /backend/api/suppliers/index.php
 *
 * This endpoint shows approved suppliers from the suppliers table (LEFT JOIN users table).
 * Only shows suppliers who have been approved and have entries in suppliers table.
 * Pending suppliers are managed through User Management > Pending Suppliers section.
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

try {
    $user = Auth::user();
    $db = Database::getInstance();
    $conn = $db->getConnection();

        // Build query based on suppliers table - this shows approved suppliers
    // Show active suppliers from suppliers table JOINED with users
    $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : true;

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
            s.notes,
            s.created_at as supplier_created_at,
            s.updated_at as supplier_updated_at
        FROM suppliers s
        INNER JOIN users u ON s.user_id = u.id AND u.role = 'supplier'
        WHERE 1=1" . ($activeOnly ? " AND u.is_active = 1" : "") . "
        ORDER BY u.created_at DESC
    ";

    // Debug: Log the query and results count
    error_log("Supplier query: " . $query);
    error_log("Active only: " . ($activeOnly ? 'true' : 'false'));

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Count suppliers found
    error_log("Found " . count($suppliers) . " suppliers");

    // Get total orders count for each supplier
    $supplierOrderCounts = [];
    foreach ($suppliers as $supplier) {
        $orderQuery = "SELECT COUNT(*) as total_orders FROM purchase_orders
                      WHERE supplier_id = :supplier_id
                      AND status IN ('approved', 'ordered', 'partially_received', 'received')";
        $stmt = $conn->prepare($orderQuery);
        $stmt->execute(['supplier_id' => $supplier['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $supplierOrderCounts[$supplier['id']] = (int)$result['total_orders'];
    }

    // Format for frontend with complete supplier information
    $formattedSuppliers = array_map(function($supplier) use ($supplierOrderCounts) {
        $isActive = (bool)$supplier['is_active'];

        return [
            // User information
            'id' => $supplier['id'], // User ID is the supplier ID
            'user_id' => $supplier['id'],
            'name' => $supplier['full_name'] ?? $supplier['username'],
            'username' => $supplier['username'],
            'email' => $supplier['email'],
            'phone' => $supplier['supplier_phone'] ?? $supplier['phone'] ?? '',
            'is_active' => $isActive,
            'status' => $isActive ? 'active' : 'inactive',
            'supplier_status' => 'approved',
            'created_at' => $supplier['created_at'],
            'updated_at' => $supplier['updated_at'] ?? null,
            'last_login' => $supplier['last_login'] ?? null,

            // Complete supplier information from suppliers table
            'supplier_code' => $supplier['supplier_code'] ?? 'SUP-' . str_pad($supplier['id'], 5, '0', STR_PAD_LEFT),
            'company_name' => $supplier['company_name'] ?? '',
            'contact_person' => $supplier['contact_person'] ?? '',
            'supplier_email' => $supplier['supplier_email'] ?? '',
            'address' => $supplier['address'] ?? '',
            'city' => $supplier['city'] ?? '',
            'state' => $supplier['state'] ?? '',
            'postal_code' => $supplier['postal_code'] ?? '',
            'country' => $supplier['country'] ?? 'Philippines',
            'tax_id' => $supplier['tax_id'] ?? '',
            'payment_terms' => $supplier['payment_terms'] ?? 'Net 30',
            'notes' => $supplier['notes'] ?? '',
            'supplier_created_at' => $supplier['supplier_created_at'],
            'supplier_updated_at' => $supplier['supplier_updated_at'],

            // Nested supplier data for frontend
            'supplier_data' => [
                'company_name' => $supplier['company_name'] ?? '',
                'supplier_code' => $supplier['supplier_code'] ?? 'SUP-' . str_pad($supplier['id'], 5, '0', STR_PAD_LEFT),
                'contact_person' => $supplier['contact_person'] ?? '',
                'phone' => $supplier['supplier_phone'] ?? '',
                'email' => $supplier['supplier_email'] ?? '',
                'address' => $supplier['address'] ?? '',
                'city' => $supplier['city'] ?? '',
                'state' => $supplier['state'] ?? '',
                'postal_code' => $supplier['postal_code'] ?? '',
                'country' => $supplier['country'] ?? 'Philippines',
                'tax_id' => $supplier['tax_id'] ?? '',
                'payment_terms' => $supplier['payment_terms'] ?? 'Net 30',
                'notes' => $supplier['notes'] ?? '',
                'created_at' => $supplier['supplier_created_at'],
                'updated_at' => $supplier['supplier_updated_at']
            ],

            // Statistics
            'total_orders' => $supplierOrderCounts[$supplier['id']] ?? 0
        ];
    }, $suppliers);

    Response::success([
        'suppliers' => $formattedSuppliers,
        'total' => count($formattedSuppliers)
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch suppliers: ' . $e->getMessage());
}

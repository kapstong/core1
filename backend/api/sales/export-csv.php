<?php
/**
 * Sales Export CSV Endpoint
 * GET /backend/api/sales/export-csv.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

try {
    // Require authentication
    Auth::requireAuth();

    $db = Database::getInstance()->getConnection();

    // Get sales data
    $sql = "SELECT
                s.id,
                s.sale_date,
                s.total_amount,
                s.payment_method,
                s.status,
                u.username as cashier,
                c.name as customer_name,
                c.email as customer_email
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.sale_date DESC";

    $stmt = $db->query($sql);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_export_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8

    // CSV Header
    fputcsv($output, [
        'Sale ID',
        'Date',
        'Total Amount',
        'Payment Method',
        'Status',
        'Cashier',
        'Customer Name',
        'Customer Email'
    ]);

    // Data rows
    foreach ($sales as $sale) {
        fputcsv($output, [
            $sale['id'],
            $sale['sale_date'],
            number_format($sale['total_amount'], 2),
            $sale['payment_method'],
            $sale['status'],
            $sale['cashier'] ?? 'N/A',
            $sale['customer_name'] ?? 'Walk-in',
            $sale['customer_email'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Sales export error: " . $e->getMessage());
    http_response_code(500);
    echo "Error exporting sales data";
}

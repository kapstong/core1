<?php
/**
 * Orders Export CSV Endpoint
 * GET /backend/api/orders/export.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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

    // Get orders data
    $sql = "SELECT
                o.id,
                o.order_number,
                o.order_date,
                o.total_amount,
                o.status,
                o.payment_status,
                o.shipping_method,
                o.tracking_number,
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            ORDER BY o.order_date DESC";

    $stmt = $db->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Header
    fputcsv($output, [
        'Order ID',
        'Order Number',
        'Order Date',
        'Total Amount',
        'Status',
        'Payment Status',
        'Shipping Method',
        'Tracking Number',
        'Customer Name',
        'Customer Email',
        'Customer Phone'
    ]);

    // Data rows
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['order_number'],
            $order['order_date'],
            number_format($order['total_amount'], 2),
            $order['status'],
            $order['payment_status'],
            $order['shipping_method'] ?? '',
            $order['tracking_number'] ?? '',
            $order['customer_name'] ?? '',
            $order['customer_email'] ?? '',
            $order['customer_phone'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Orders export error: " . $e->getMessage());
    http_response_code(500);
    echo "Error exporting orders";
}


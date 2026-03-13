<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';

if (!Auth::check()) {
    Response::error('Unauthorized access', 401);
}

try {
    $conn = Database::getInstance()->getConnection();

    $query = "
        SELECT
            s.id AS sale_id,
            s.invoice_number AS transaction_number,
            s.created_at,
            s.total_amount,
            s.payment_method,
            s.payment_status AS status,
            COALESCE(NULLIF(s.customer_name, ''), 'Walk-in Customer') AS customer_name
        FROM sales s
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY s.created_at DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['sales' => $sales], 'Recent sales retrieved successfully');
} catch (Exception $e) {
    Response::error('Failed to fetch recent sales: ' . $e->getMessage(), 500);
}


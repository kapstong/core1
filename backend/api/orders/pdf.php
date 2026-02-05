<?php
/**
 * Order PDF Generation API Endpoint
 * GET /backend/api/orders/pdf.php?order_id={id} - Generate and download PDF invoice
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/PDF.php';
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

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        generateOrderPDF();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('PDF generation failed: ' . $e->getMessage());
}

function generateOrderPDF() {
    $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;
    $download = isset($_GET['download']) ? filter_var($_GET['download'], FILTER_VALIDATE_BOOLEAN) : true;

    if (!$orderId) {
        Response::error('Order ID is required', 400);
    }

    // Get order details
    $db = Database::getInstance()->getConnection();

    $query = "SELECT
                co.*,
                c.first_name, c.last_name, c.email, c.phone,
                sa.first_name as shipping_first_name, sa.last_name as shipping_last_name,
                sa.address_line_1 as shipping_address_1, sa.address_line_2 as shipping_address_2,
                sa.city as shipping_city, sa.state as shipping_state, sa.postal_code as shipping_postal,
                sa.country as shipping_country,
                ba.first_name as billing_first_name, ba.last_name as billing_last_name,
                ba.address_line_1 as billing_address_1, ba.address_line_2 as billing_address_2,
                ba.city as billing_city, ba.state as billing_state, ba.postal_code as billing_postal,
                ba.country as billing_country
              FROM customer_orders co
              INNER JOIN customers c ON co.customer_id = c.id
              LEFT JOIN customer_addresses sa ON co.shipping_address_id = sa.id
              LEFT JOIN customer_addresses ba ON co.billing_address_id = ba.id
              WHERE co.id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        Response::error('Order not found', 404);
    }

    // Check permissions - users can only access their own orders, admins can access all
    $user = Auth::user();
    if ($user['role'] !== 'admin' && $user['role'] !== 'inventory_manager' && $order['customer_id'] != $user['id']) {
        Response::error('Access denied. You can only view your own orders', 403);
    }

    // Get order items
    $itemsQuery = "SELECT * FROM customer_order_items WHERE order_id = :order_id ORDER BY id";
    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $itemsStmt->execute();
    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare customer data
    $customer = [
        'first_name' => $order['first_name'],
        'last_name' => $order['last_name'],
        'email' => $order['email'],
        'phone' => $order['phone']
    ];

    // Generate PDF
    $pdf = new PDF();
    $pdfContent = $pdf->generateInvoice($order, $customer);

    if ($download) {
        // Send PDF as download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice_' . $order['order_number'] . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    } else {
        // Return PDF data as base64
        Response::success([
            'order_number' => $order['order_number'],
            'pdf_base64' => base64_encode($pdfContent),
            'filename' => 'invoice_' . $order['order_number'] . '.pdf'
        ]);
    }
}


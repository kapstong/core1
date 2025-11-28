<?php
/**
 * Sales Receipt API Endpoint
 * GET /backend/api/sales/receipt.php?id={id} - Get printable receipt
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication first
if (!Auth::check()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Validate sale ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo 'Sale ID is required';
    exit;
}

$saleId = (int)$_GET['id'];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $sale = null;
    $items = [];
    $saleType = null;

    // First try to get from sales table (POS)
    $posQuery = "SELECT s.*, u.full_name as cashier_name
                 FROM sales s
                 LEFT JOIN users u ON s.cashier_id = u.id
                 WHERE s.id = :id";

    $stmt = $conn->prepare($posQuery);
    $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $stmt->execute();
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sale) {
        $saleType = 'pos';
        // Get POS sale items
        $itemsQuery = "SELECT si.*, p.name as product_name, p.sku
                       FROM sale_items si
                       LEFT JOIN products p ON si.product_id = p.id
                       WHERE si.sale_id = :sale_id";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Try customer_orders table (online)
        $onlineQuery = "SELECT co.*,
                        CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
                        c.email as customer_email,
                        c.phone as customer_phone
                        FROM customer_orders co
                        LEFT JOIN customers c ON co.customer_id = c.id
                        WHERE co.id = :id";

        $stmt = $conn->prepare($onlineQuery);
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            http_response_code(404);
            echo 'Sale not found';
            exit;
        }

        $saleType = 'online';
        // Get online order items
        $itemsQuery = "SELECT coi.*, p.name as product_name, p.sku
                       FROM customer_order_items coi
                       LEFT JOIN products p ON coi.product_id = p.id
                       WHERE coi.order_id = :order_id";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bindParam(':order_id', $saleId, PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$sale) {
        http_response_code(404);
        echo 'Sale not found';
        exit;
    }

    // Get cashier name if POS
    $cashierName = $sale['cashier_name'] ?? ($saleType === 'online' ? 'Online Shop' : 'Unknown');
    $invoiceNumber = $saleType === 'pos' ? $sale['invoice_number'] : $sale['order_number'];
    $saleDate = $saleType === 'pos' ? $sale['sale_date'] : ($sale['order_date'] ?? $sale['created_at']);
    $customerName = $sale['customer_name'] ?? ($saleType === 'pos' ? 'Walk-in Customer' : 'Guest');

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - <?php echo htmlspecialchars($invoiceNumber); ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Courier New', monospace;
                background: #f5f5f5;
                padding: 20px;
            }

            .receipt-container {
                max-width: 400px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border: 1px solid #ddd;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .receipt-header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #333;
            }

            .store-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .store-info {
                font-size: 12px;
                color: #666;
            }

            .invoice-info {
                font-size: 12px;
                margin: 15px 0;
                padding: 10px;
                background: #f9f9f9;
            }

            .invoice-row {
                display: flex;
                justify-content: space-between;
                margin: 3px 0;
            }

            .invoice-label {
                font-weight: bold;
            }

            .customer-info {
                font-size: 12px;
                margin: 15px 0;
                padding: 10px;
                background: #f9f9f9;
            }

            .items-section {
                margin: 20px 0;
                padding: 15px 0;
                border-top: 1px solid #ddd;
                border-bottom: 1px solid #ddd;
            }

            .items-header {
                display: grid;
                grid-template-columns: 1fr 2fr 1fr 1fr;
                gap: 5px;
                font-weight: bold;
                font-size: 11px;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #ccc;
            }

            .item-row {
                display: grid;
                grid-template-columns: 1fr 2fr 1fr 1fr;
                gap: 5px;
                font-size: 11px;
                margin-bottom: 5px;
            }

            .item-qty {
                text-align: center;
            }

            .item-price {
                text-align: right;
            }

            .item-total {
                text-align: right;
                font-weight: bold;
            }

            .totals-section {
                margin: 15px 0;
            }

            .total-row {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                margin: 5px 0;
            }

            .total-row.grand-total {
                font-weight: bold;
                font-size: 14px;
                border-top: 2px solid #333;
                border-bottom: 2px solid #333;
                padding: 5px 0;
                margin: 10px 0;
            }

            .payment-info {
                font-size: 12px;
                margin: 10px 0;
                padding: 10px;
                background: #f9f9f9;
            }

            .receipt-footer {
                text-align: center;
                margin-top: 20px;
                font-size: 11px;
                color: #666;
                padding-top: 10px;
                border-top: 1px dashed #999;
            }

            .thank-you {
                text-align: center;
                font-weight: bold;
                margin-top: 10px;
                font-size: 12px;
            }

            @media print {
                body {
                    background: white;
                    padding: 0;
                }

                .receipt-container {
                    max-width: 100%;
                    box-shadow: none;
                    border: none;
                }

                .print-button {
                    display: none;
                }
            }

            .print-button {
                display: block;
                margin: 20px auto;
                padding: 10px 20px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }

            .print-button:hover {
                background: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <!-- Header -->
            <div class="receipt-header">
                <div class="store-name">PC Parts Central</div>
                <div class="store-info">
                    Electronics & Computer Parts<br>
                    www.pcpartscentral.com
                </div>
            </div>

            <!-- Invoice Info -->
            <div class="invoice-info">
                <div class="invoice-row">
                    <span class="invoice-label">Invoice #:</span>
                    <span><?php echo htmlspecialchars($invoiceNumber); ?></span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Date:</span>
                    <span><?php echo date('m/d/Y H:i', strtotime($saleDate)); ?></span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Cashier:</span>
                    <span><?php echo htmlspecialchars($cashierName); ?></span>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="customer-info">
                <div class="invoice-row">
                    <span class="invoice-label">Customer:</span>
                    <span><?php echo htmlspecialchars($customerName); ?></span>
                </div>
                <?php if (!empty($sale['customer_email'])): ?>
                <div class="invoice-row">
                    <span class="invoice-label">Email:</span>
                    <span><?php echo htmlspecialchars($sale['customer_email']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sale['customer_phone'])): ?>
                <div class="invoice-row">
                    <span class="invoice-label">Phone:</span>
                    <span><?php echo htmlspecialchars($sale['customer_phone']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <div class="items-section">
                <div class="items-header">
                    <div>QTY</div>
                    <div>PRODUCT</div>
                    <div>PRICE</div>
                    <div>TOTAL</div>
                </div>

                <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <div class="item-qty"><?php echo (int)$item['quantity']; ?></div>
                    <div><?php echo htmlspecialchars(substr($item['product_name'] ?? 'Unknown', 0, 15)); ?></div>
                    <div class="item-price">₱<?php echo number_format($item['unit_price'], 2); ?></div>
                    <div class="item-total">₱<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Totals -->
            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₱<?php echo number_format($sale['subtotal'] ?? 0, 2); ?></span>
                </div>

                <?php if ((float)($sale['discount_amount'] ?? 0) > 0): ?>
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-₱<?php echo number_format($sale['discount_amount'], 2); ?></span>
                </div>
                <?php endif; ?>

                <div class="total-row">
                    <span>Tax (<?php echo (float)($sale['tax_rate'] ?? 0.12) * 100; ?>%):</span>
                    <span>₱<?php echo number_format($sale['tax_amount'] ?? 0, 2); ?></span>
                </div>

                <div class="total-row grand-total">
                    <span>TOTAL:</span>
                    <span>₱<?php echo number_format($sale['total_amount'] ?? 0, 2); ?></span>
                </div>
            </div>

            <!-- Payment Info -->
            <div class="payment-info">
                <div class="invoice-row">
                    <span class="invoice-label">Payment Method:</span>
                    <span><?php echo htmlspecialchars(ucfirst($sale['payment_method'] ?? 'Unknown')); ?></span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Status:</span>
                    <span><?php echo htmlspecialchars(ucfirst($sale['payment_status'] ?? 'Unknown')); ?></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="receipt-footer">
                <div class="thank-you">Thank you for your purchase!</div>
                <div style="margin-top: 10px;">
                    For inquiries, please contact us at:<br>
                    support@pcpartscentral.com
                </div>
            </div>
        </div>

        <button class="print-button" onclick="window.print()">🖨️ Print Receipt</button>

        <script>
            // Auto-print if requested
            if (new URLSearchParams(window.location.search).get('print') === '1') {
                window.setTimeout(() => window.print(), 500);
            }
        </script>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}
?>

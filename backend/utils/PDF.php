<?php
/**
 * PDF Generation Utility Class
 * Generates PDF invoices and documents using TCPDF or FPDF
 */

class PDF {
    private $pdf;
    private $settings;

    public function __construct() {
        // Try to load TCPDF first, fallback to basic implementation
        if ($this->loadTCPDF()) {
            $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $this->setupTCPDF();
        } else {
            // Fallback to basic HTML-to-PDF simulation (would need proper PDF library in production)
            $this->pdf = null;
        }

        $this->loadSettings();
    }

    private function loadTCPDF() {
        // Try to load TCPDF if available
        $tcpdfPath = __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdfPath)) {
            require_once $tcpdfPath;
            return true;
        }

        // Try alternative paths
        $paths = [
            __DIR__ . '/../vendor/tcpdf/tcpdf.php',
            __DIR__ . '/../../lib/tcpdf/tcpdf.php'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return true;
            }
        }

        return false;
    }

    private function setupTCPDF() {
        if (!$this->pdf) return;

        // Set document information
        $this->pdf->SetCreator('PC Parts System');
        $this->pdf->SetAuthor('PC Parts Store');
        $this->pdf->SetTitle('Invoice');

        // Set default header data
        $this->pdf->SetHeaderData('', 0, $this->settings['system_name'] ?? 'PC Parts Store', '');

        // Set header and footer fonts
        $this->pdf->setHeaderFont(['helvetica', '', 10]);
        $this->pdf->setFooterFont(['helvetica', '', 8]);

        // Set default monospaced font
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $this->pdf->SetMargins(15, 27, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);

        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, 25);

        // Set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set font
        $this->pdf->SetFont('helvetica', '', 10);
    }

    private function loadSettings() {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance()->getConnection();

            $settings = [
                'system_name' => 'PC Parts Store',
                'currency' => 'PHP',
                'currency_symbol' => '₱',
                'tax_rate' => '0.08'
            ];

            foreach ($settings as $key => $default) {
                $query = "SELECT setting_value FROM settings WHERE setting_key = :key";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->execute();

                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $settings[$key] = $result ? $result['setting_value'] : $default;
            }

            $this->settings = $settings;
        } catch (Exception $e) {
            $this->settings = [
                'system_name' => 'PC Parts Store',
                'currency' => 'PHP',
                'currency_symbol' => '₱',
                'tax_rate' => '0.08'
            ];
        }
    }

    /**
     * Generate invoice PDF for customer order
     */
    public function generateInvoice($order, $customer) {
        if ($this->pdf) {
            return $this->generateInvoiceTCPDF($order, $customer);
        } else {
            return $this->generateInvoiceHTML($order, $customer);
        }
    }

    private function generateInvoiceTCPDF($order, $customer) {
        // Add a page
        $this->pdf->AddPage();

        // Set font
        $this->pdf->SetFont('helvetica', 'B', 16);

        // Title
        $this->pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
        $this->pdf->Ln(5);

        // Invoice details
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(100, 6, 'Invoice Number: ' . $order['order_number'], 0, 0);
        $this->pdf->Cell(0, 6, 'Date: ' . date('F j, Y', strtotime($order['order_date'])), 0, 1);
        $this->pdf->Ln(2);

        // Customer details
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 8, 'Bill To:', 0, 1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, $customer['first_name'] . ' ' . $customer['last_name'], 0, 1);
        if (!empty($customer['phone'])) {
            $this->pdf->Cell(0, 6, 'Phone: ' . $customer['phone'], 0, 1);
        }
        $this->pdf->Cell(0, 6, 'Email: ' . $customer['email'], 0, 1);
        $this->pdf->Ln(5);

        // Shipping address
        if (isset($order['shipping_first_name'])) {
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->Cell(0, 8, 'Ship To:', 0, 1);
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->Cell(0, 6, $order['shipping_first_name'] . ' ' . $order['shipping_last_name'], 0, 1);
            $this->pdf->Cell(0, 6, $order['shipping_address_1'], 0, 1);
            if (!empty($order['shipping_address_2'])) {
                $this->pdf->Cell(0, 6, $order['shipping_address_2'], 0, 1);
            }
            $this->pdf->Cell(0, 6, $order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_postal'], 0, 1);
            $this->pdf->Cell(0, 6, $order['shipping_country'], 0, 1);
            $this->pdf->Ln(5);
        }

        // Order items table
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(240, 240, 240);

        // Table headers
        $this->pdf->Cell(80, 8, 'Product', 1, 0, 'L', true);
        $this->pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Price', 1, 0, 'R', true);
        $this->pdf->Cell(30, 8, 'Total', 1, 1, 'R', true);

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetFillColor(255, 255, 255);

        // Table rows
        foreach ($order['items'] as $item) {
            $this->pdf->Cell(80, 6, $item['product_name'] . ' (' . $item['product_sku'] . ')', 1, 0, 'L');
            $this->pdf->Cell(20, 6, $item['quantity'], 1, 0, 'C');
            $this->pdf->Cell(30, 6, $this->settings['currency_symbol'] . number_format($item['unit_price'], 2), 1, 0, 'R');
            $this->pdf->Cell(30, 6, $this->settings['currency_symbol'] . number_format($item['total_price'], 2), 1, 1, 'R');
        }

        // Totals
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', 'B', 10);

        $this->pdf->Cell(130, 6, 'Subtotal:', 0, 0, 'R');
        $this->pdf->Cell(30, 6, $this->settings['currency_symbol'] . number_format($order['subtotal'], 2), 0, 1, 'R');

        $this->pdf->Cell(130, 6, 'Tax:', 0, 0, 'R');
        $this->pdf->Cell(30, 6, $this->settings['currency_symbol'] . number_format($order['tax_amount'], 2), 0, 1, 'R');

        $this->pdf->Cell(130, 6, 'Shipping:', 0, 0, 'R');
        $this->pdf->Cell(30, 6, $this->settings['currency_symbol'] . number_format($order['shipping_amount'], 2), 0, 1, 'R');

        if ($order['discount_amount'] > 0) {
            $this->pdf->Cell(130, 6, 'Discount:', 0, 0, 'R');
            $this->pdf->Cell(30, 6, '-' . $this->settings['currency_symbol'] . number_format($order['discount_amount'], 2), 0, 1, 'R');
        }

        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(130, 8, 'Total:', 0, 0, 'R');
        $this->pdf->Cell(30, 8, $this->settings['currency_symbol'] . number_format($order['total_amount'], 2), 0, 1, 'R');

        // Payment info
        $this->pdf->Ln(10);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Payment Information:', 0, 1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'Payment Method: ' . ucfirst(str_replace('_', ' ', $order['payment_method'])), 0, 1);
        $this->pdf->Cell(0, 6, 'Payment Status: ' . ucfirst($order['payment_status']), 0, 1);

        // Footer
        $this->pdf->Ln(15);
        $this->pdf->SetFont('helvetica', 'I', 8);
        $this->pdf->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
        $this->pdf->Cell(0, 5, $this->settings['system_name'] . ' - All rights reserved', 0, 1, 'C');

        // Output PDF
        return $this->pdf->Output('invoice_' . $order['order_number'] . '.pdf', 'S');
    }

    private function generateInvoiceHTML($order, $customer) {
        // Fallback HTML version (would need a proper PDF library in production)
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Invoice {$order['order_number']}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                .invoice-details { margin-bottom: 20px; }
                .customer-details, .shipping-details { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .total-row { font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>{$this->settings['system_name']}</h1>
                <h2>INVOICE</h2>
            </div>

            <div class='invoice-details'>
                <strong>Invoice Number:</strong> {$order['order_number']}<br>
                <strong>Date:</strong> " . date('F j, Y', strtotime($order['order_date'])) . "<br>
                <strong>Status:</strong> " . ucfirst($order['status']) . "
            </div>

            <div class='customer-details'>
                <h3>Bill To:</h3>
                <p>
                    {$customer['first_name']} {$customer['last_name']}<br>
                    " . (!empty($customer['phone']) ? "Phone: {$customer['phone']}<br>" : "") . "
                    Email: {$customer['email']}
                </p>
            </div>
        ";

        if (isset($order['shipping_first_name'])) {
            $html .= "
            <div class='shipping-details'>
                <h3>Ship To:</h3>
                <p>
                    {$order['shipping_first_name']} {$order['shipping_last_name']}<br>
                    {$order['shipping_address_1']}<br>
                    " . (!empty($order['shipping_address_2']) ? "{$order['shipping_address_2']}<br>" : "") . "
                    {$order['shipping_city']}, {$order['shipping_state']} {$order['shipping_postal']}<br>
                    {$order['shipping_country']}
                </p>
            </div>
            ";
        }

        $html .= "
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class='text-center'>Qty</th>
                        <th class='text-right'>Price</th>
                        <th class='text-right'>Total</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($order['items'] as $item) {
            $html .= "
                    <tr>
                        <td>{$item['product_name']} ({$item['product_sku']})</td>
                        <td class='text-center'>{$item['quantity']}</td>
                        <td class='text-right'>{$this->settings['currency_symbol']}" . number_format($item['unit_price'], 2) . "</td>
                        <td class='text-right'>{$this->settings['currency_symbol']}" . number_format($item['total_price'], 2) . "</td>
                    </tr>
            ";
        }

        $html .= "
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='3' class='text-right'>Subtotal:</td>
                        <td class='text-right'>{$this->settings['currency_symbol']}" . number_format($order['subtotal'], 2) . "</td>
                    </tr>
                    <tr>
                        <td colspan='3' class='text-right'>Tax:</td>
                        <td class='text-right'>{$this->settings['currency_symbol']}" . number_format($order['tax_amount'], 2) . "</td>
                    </tr>
                    <tr>
                        <td colspan='3' class='text-right'>Shipping:</td>
                        <td class='text-right'>{$this->settings['currency_symbol']}" . number_format($order['shipping_amount'], 2) . "</td>
                    </tr>
        ";

        if ($order['discount_amount'] > 0) {
            $html .= "
                    <tr>
                        <td colspan='3' class='text-right'>Discount:</td>
                        <td class='text-right'>-{$this->settings['currency_symbol']}" . number_format($order['discount_amount'], 2) . "</td>
                    </tr>
            ";
        }

        $html .= "
                    <tr class='total-row'>
                        <td colspan='3' class='text-right'>Total:</td>
                        <td class='text-right'>{$this->settings['currency_symbol']}" . number_format($order['total_amount'], 2) . "</td>
                    </tr>
                </tfoot>
            </table>

            <div style='margin: 20px 0;'>
                <strong>Payment Method:</strong> " . ucfirst(str_replace('_', ' ', $order['payment_method'])) . "<br>
                <strong>Payment Status:</strong> " . ucfirst($order['payment_status']) . "
            </div>

            <div class='footer'>
                <p>Thank you for your business!</p>
                <p>{$this->settings['system_name']} - All rights reserved</p>
            </div>
        </body>
        </html>
        ";

        return $html;
    }
}

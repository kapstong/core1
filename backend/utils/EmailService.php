<?php
/**
 * Email Notification Service
 * Handles all email notifications for the system
 */

class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;

    public function __construct() {
        // Load email configuration
        $this->loadConfig();
    }

    private function loadConfig() {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE category = 'email'");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->smtp_host = $settings['email_smtp_host'] ?? '';
        $this->smtp_port = $settings['email_smtp_port'] ?? '587';
        $this->smtp_user = $settings['email_smtp_user'] ?? '';
        $this->smtp_pass = $settings['email_smtp_pass'] ?? '';
        $this->from_email = $settings['email_from_address'] ?? 'noreply@core1.com';
        $this->from_name = $settings['email_from_name'] ?? 'PC Parts Core1';
    }

    public function sendOrderConfirmation($orderId) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get order details
        $stmt = $conn->prepare("
            SELECT co.*, c.email, c.first_name, c.last_name 
            FROM customer_orders co
            JOIN customers c ON co.customer_id = c.id
            WHERE co.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order not found");
        }

        // Get order items
        $stmt = $conn->prepare("
            SELECT * FROM customer_order_items 
            WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare email content
        $subject = "Order Confirmation - Order #{$order['order_number']}";
        $message = $this->getOrderConfirmationTemplate($order, $items);

        // Send email
        return $this->send($order['email'], $subject, $message);
    }

    public function sendLowStockAlert($productId, $currentStock) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get product details
        $stmt = $conn->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Product not found");
        }

        // Get inventory manager email
        $stmt = $conn->prepare("
            SELECT email FROM users 
            WHERE role = 'inventory_manager' AND is_active = 1
        ");
        $stmt->execute();
        $managers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($managers)) {
            throw new Exception("No active inventory managers found");
        }

        // Prepare email content
        $subject = "Low Stock Alert - {$product['name']}";
        $message = $this->getLowStockAlertTemplate($product, $currentStock);

        // Send to all inventory managers
        $sent = true;
        foreach ($managers as $email) {
            $sent = $sent && $this->send($email, $subject, $message);
        }

        return $sent;
    }

    public function sendPasswordReset($userId, $resetToken) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user details
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("User not found");
        }

        $resetUrl = "http://{$_SERVER['HTTP_HOST']}/reset-password.php?token=" . urlencode($resetToken);
        
        // Prepare email content
        $subject = "Password Reset Request";
        $message = $this->getPasswordResetTemplate($user['full_name'], $resetUrl);

        // Send email
        return $this->send($user['email'], $subject, $message);
    }

    private function send($to, $subject, $message) {
        // If SMTP is not configured, save to file
        if (empty($this->smtp_host)) {
            return $this->saveToFile($to, $subject, $message);
        }

        // Set up PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_user;
            $mail->Password = $this->smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;

            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            // Fallback to file storage
            return $this->saveToFile($to, $subject, $message);
        }
    }

    private function saveToFile($to, $subject, $message) {
        $emailDir = __DIR__ . '/../../logs/emails';
        if (!is_dir($emailDir)) {
            mkdir($emailDir, 0777, true);
        }

        $filename = $emailDir . '/' . date('Y-m-d_H-i-s') . '_' . md5(uniqid()) . '.html';
        $content = "To: $to\nSubject: $subject\n\n$message";

        return file_put_contents($filename, $content) !== false;
    }

    private function getOrderConfirmationTemplate($order, $items) {
        ob_start();
        include __DIR__ . '/../../templates/emails/order_confirmation.php';
        return ob_get_clean();
    }

    private function getLowStockAlertTemplate($product, $currentStock) {
        ob_start();
        include __DIR__ . '/../../templates/emails/low_stock_alert.php';
        return ob_get_clean();
    }

    private function getPasswordResetTemplate($name, $resetUrl) {
        ob_start();
        include __DIR__ . '/../../templates/emails/password_reset.php';
        return ob_get_clean();
    }
}
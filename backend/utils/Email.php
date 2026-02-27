<?php
/**
 * Email Utility Class
 * Handles sending emails using SMTP or PHP mail()
 */

class Email {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    private $baseUrl;

    public function __construct() {
        // Load email settings from database
        $this->loadSettings();
        // Get base URL from settings or use auto-detection
        $this->baseUrl = $this->getBaseUrl();
    }

    private function loadSettings() {
        require_once __DIR__ . '/../config/env.php';
        Env::load();

        $envSettings = [
            'smtp_host' => trim((string)(Env::get('SMTP_HOST', '') ?? '')),
            'smtp_port' => (int)(Env::get('SMTP_PORT', 587) ?? 587),
            'smtp_username' => trim((string)(Env::get('SMTP_USERNAME', '') ?? '')),
            'smtp_password' => trim((string)(Env::get('SMTP_PASSWORD', '') ?? '')),
            'email_from' => trim((string)(Env::get('SMTP_FROM_EMAIL', 'noreply@pcparts.com') ?? 'noreply@pcparts.com')),
            'system_name' => trim((string)(Env::get('SMTP_FROM_NAME', 'PC Parts Merchandising System') ?? 'PC Parts Merchandising System'))
        ];

        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance()->getConnection();

            $settingKeys = [
                'smtp_host' => ['smtp_host', 'email_smtp_host'],
                'smtp_port' => ['smtp_port', 'email_smtp_port'],
                'smtp_username' => ['smtp_username', 'email_smtp_user'],
                'smtp_password' => ['smtp_password', 'email_smtp_pass'],
                'email_from' => ['email_from', 'email_from_address'],
                'system_name' => ['system_name', 'email_from_name', 'store_name']
            ];

            $resolved = $envSettings;

            foreach ($settingKeys as $targetKey => $candidates) {
                // Keep explicit .env values as highest priority.
                if (isset($resolved[$targetKey]) && trim((string)$resolved[$targetKey]) !== '') {
                    continue;
                }

                foreach ($candidates as $candidateKey) {
                    $query = "SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindValue(':key', $candidateKey);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$result) {
                        continue;
                    }

                    $value = isset($result['setting_value']) ? trim((string)$result['setting_value']) : '';
                    if ($value === '') {
                        continue;
                    }

                    $resolved[$targetKey] = $value;
                    break;
                }
            }

            $this->smtpHost = trim((string)$resolved['smtp_host']);
            $this->smtpPort = max(1, (int)$resolved['smtp_port']);
            $this->smtpUsername = trim((string)$resolved['smtp_username']);
            $this->smtpPassword = (string)$resolved['smtp_password'];
            $this->fromEmail = trim((string)$resolved['email_from']);
            $this->fromName = trim((string)$resolved['system_name']);

        } catch (Exception $e) {
            // Fallback to env values if database is unavailable
            $this->smtpHost = trim((string)$envSettings['smtp_host']);
            $this->smtpPort = max(1, (int)$envSettings['smtp_port']);
            $this->smtpUsername = trim((string)$envSettings['smtp_username']);
            $this->smtpPassword = (string)$envSettings['smtp_password'];
            $this->fromEmail = trim((string)$envSettings['email_from']);
            $this->fromName = trim((string)$envSettings['system_name']);
        }

        if ($this->fromEmail === '') {
            $this->fromEmail = $this->smtpUsername !== '' ? $this->smtpUsername : 'noreply@pcparts.com';
        }
        if ($this->fromName === '') {
            $this->fromName = 'PC Parts Merchandising System';
        }
    }

    /**
     * Get base URL from settings or auto-detect
     */
    private function getBaseUrl() {
        try {
            // Try to get from database settings
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance()->getConnection();

            $query = "SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && !empty($result['setting_value'])) {
                return rtrim($result['setting_value'], '/');
            }
        } catch (Exception $e) {
            // Database not available, continue to auto-detection
        }

        // Auto-detect from server variables
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Remove /backend/... from the path to get the base directory
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = '';

        // Try to extract the base path (e.g., /core1)
        if (preg_match('#^(/[^/]+)/#', $scriptPath, $matches)) {
            $basePath = $matches[1];
        }

        return $protocol . '://' . $host . $basePath;
    }

    /**
     * Send supplier approval notification
     */
    public function sendSupplierApprovalNotification($email, $name, $supplierCode) {
        $subject = 'Your Supplier Account Has Been Approved';
        
        $template = file_get_contents(__DIR__ . '/../email_templates/supplier_approval.html');
        if (!$template) {
            $template = "
                <h2>Welcome to PC Parts!</h2>
                <p>Dear {name},</p>
                <p>Your supplier account has been approved. You can now log in to the system using your registered email and password.</p>
                <p><strong>Your Supplier Code:</strong> {supplierCode}</p>
                <p>Please keep this code for your records as it will be used for all transactions.</p>
                <p><a href='{loginUrl}'>Click here to login</a></p>
                <p>Best regards,<br>{systemName}</p>
            ";
        }

        $message = strtr($template, [
            '{name}' => $name,
            '{supplierCode}' => $supplierCode,
            '{loginUrl}' => $this->baseUrl . '/login.php',
            '{systemName}' => $this->fromName
        ]);

        return $this->send($email, $subject, $message);
    }

    /**
     * Send supplier rejection notification
     */
    public function sendSupplierRejectionNotification($email, $name, $reason) {
        $subject = 'Supplier Registration Status Update';
        
        $template = file_get_contents(__DIR__ . '/../email_templates/supplier_rejection.html');
        if (!$template) {
            $template = "
                <h2>Supplier Registration Update</h2>
                <p>Dear {name},</p>
                <p>We regret to inform you that your supplier account registration has not been approved at this time.</p>
                <p><strong>Reason:</strong> {reason}</p>
                <p>If you believe this was in error or would like to provide additional information, please contact our support team.</p>
                <p>Best regards,<br>{systemName}</p>
            ";
        }

        $message = strtr($template, [
            '{name}' => $name,
            '{reason}' => $reason,
            '{systemName}' => $this->fromName
        ]);

        return $this->send($email, $subject, $message);
    }

    /**
     * Send an email
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message HTML message content
     * @param string $toName Recipient name (optional)
     * @param array $attachments Array of file paths to attach (optional)
     * @return bool Success status
     */
    public function send($to, $subject, $message, $toName = '', $attachments = []) {
        // If SMTP is not configured, use PHP mail as fallback
        if (empty($this->smtpHost) || empty($this->smtpUsername)) {
            $phpMailResult = $this->sendWithPHPMail($to, $subject, $message, $toName);
            if (!$phpMailResult) {
                error_log('Email send failed: SMTP is not configured and PHP mail fallback failed.');
            }
            return $phpMailResult;
        }

        // Use SMTP first, then try PHP mail fallback if SMTP fails.
        $smtpResult = $this->sendWithSMTP($to, $subject, $message, $toName, $attachments);
        if ($smtpResult) {
            return true;
        }

        error_log('SMTP send failed, attempting PHP mail fallback.');
        return $this->sendWithPHPMail($to, $subject, $message, $toName);
    }

    private function sendWithPHPMail($to, $subject, $message, $toName = '') {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];

        $toHeader = $toName ? $toName . ' <' . $to . '>' : $to;

        // Suppress any output from mail() function to prevent HTML corruption of JSON responses
        ob_start();
        $result = mail($toHeader, $subject, $message, implode("\r\n", $headers));
        ob_end_clean();

        return $result;
    }

    private function sendWithSMTP($to, $subject, $message, $toName = '', $attachments = []) {
        $boundary = md5(time());

        // Create email content
        $emailContent = $this->buildMIMEMessage($boundary, $message, $attachments, $to, $subject, $toName);

        // Send via SMTP
        return $this->sendViaSMTP($to, $subject, $emailContent, $toName);
    }

    private function buildMIMEMessage($boundary, $message, $attachments, $to, $subject, $toName) {
        // Email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'To: ' . ($toName ? $toName . ' <' . $to . '>' : $to),
            'Subject: ' . $subject,
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PC Parts System',
            'Date: ' . date('r')
        ];

        // Build message body
        $body = '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n";
        $body .= $message . "\r\n\r\n";

        // Add attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $filename = basename($attachment);
                $fileContent = file_get_contents($attachment);
                $encodedContent = chunk_split(base64_encode($fileContent));

                $body .= '--' . $boundary . "\r\n";
                $body .= 'Content-Type: application/octet-stream; name="' . $filename . '"' . "\r\n";
                $body .= 'Content-Transfer-Encoding: base64' . "\r\n";
                $body .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
                $body .= $encodedContent . "\r\n\r\n";
            }
        }

        $body .= '--' . $boundary . '--';

        return [
            'headers' => $headers,
            'body' => $body
        ];
    }

    private function sendViaSMTP($to, $subject, $emailContent, $toName = '') {
        $host = $this->smtpHost;
        $port = $this->smtpPort;
        $username = $this->smtpUsername;
        $password = $this->smtpPassword;
        $from = $this->fromEmail;

        // Port 465 uses implicit TLS, while 587 uses STARTTLS.
        $socketHost = ($port === 465) ? 'ssl://' . $host : $host;
        $socket = fsockopen($socketHost, $port, $errno, $errstr, 30);

        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }

        // Set timeout
        stream_set_timeout($socket, 30);

        $response = $this->readResponse($socket);

        // Send EHLO
        fputs($socket, "EHLO $host\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("EHLO failed: $response");
            fclose($socket);
            return false;
        }

        // Start TLS if port 587 (required for Gmail)
        if ($port == 587) {
            fputs($socket, "STARTTLS\r\n");
            $response = $this->readResponse($socket);
            if (!$this->isSuccess($response)) {
                error_log("STARTTLS failed: $response");
                fclose($socket);
                return false;
            }

            // Enable encryption
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        // Send EHLO again after encryption
        fputs($socket, "EHLO $host\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("Post-TLS EHLO failed: $response");
            fclose($socket);
            return false;
        }

        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("AUTH LOGIN failed: $response");
            fclose($socket);
            return false;
        }

        // Send username (base64 encoded)
        fputs($socket, base64_encode($username) . "\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("Username auth failed: $response");
            fclose($socket);
            return false;
        }

        // Send password (base64 encoded)
        fputs($socket, base64_encode($password) . "\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("Password auth failed: $response");
            fclose($socket);
            return false;
        }

        // Send MAIL FROM
        fputs($socket, "MAIL FROM:<$from>\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("MAIL FROM failed: $response");
            fclose($socket);
            return false;
        }

        // Send RCPT TO
        fputs($socket, "RCPT TO:<$to>\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("RCPT TO failed: $response");
            fclose($socket);
            return false;
        }

        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("DATA command failed: $response");
            fclose($socket);
            return false;
        }

        // Send message headers
        foreach ($emailContent['headers'] as $header) {
            fputs($socket, "$header\r\n");
        }
        fputs($socket, "\r\n");

        // Send message body
        fputs($socket, $emailContent['body'] . "\r\n");
        fputs($socket, ".\r\n");

        $response = $this->readResponse($socket);
        if (!$this->isSuccess($response)) {
            error_log("Message send failed: $response");
            fclose($socket);
            return false;
        }

        // Send QUIT
        fputs($socket, "QUIT\r\n");
        $response = $this->readResponse($socket);

        fclose($socket);
        return true;
    }

    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function isSuccess($response) {
        $code = substr($response, 0, 3);
        return $code >= 200 && $code < 400;
    }

    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($order, $customer) {
        $subject = 'Order Confirmation - ' . $order['order_number'];

        $message = $this->buildOrderConfirmationHTML($order, $customer);

        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    /**
     * Send order status update email
     */
    public function sendOrderStatusUpdate($order, $customer, $oldStatus, $newStatus) {
        $subject = 'Order Status Update - ' . $order['order_number'];

        $message = $this->buildOrderStatusUpdateHTML($order, $customer, $oldStatus, $newStatus);

        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset($customer, $resetToken) {
        $subject = 'Password Reset Request';

        $message = $this->buildPasswordResetHTML($customer, $resetToken);

        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    /**
     * Send welcome email to new customers
     */
    public function sendWelcomeEmail($customer) {
        $subject = 'Welcome to PC Parts Store!';

        $message = $this->buildWelcomeEmailHTML($customer);

        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    private function buildOrderConfirmationHTML($order, $customer) {
        $itemsHTML = '';
        foreach ($order['items'] as $item) {
            $itemsHTML .= "
                <tr>
                    <td>{$item['product_name']} ({$item['product_sku']})</td>
                    <td style='text-align: center;'>{$item['quantity']}</td>
                    <td style='text-align: right;'>$" . number_format($item['unit_price'], 2) . "</td>
                    <td style='text-align: right;'>$" . number_format($item['total_price'], 2) . "</td>
                </tr>
            ";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Order Confirmation</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Order Confirmation</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>Thank you for your order! Here are the details:</p>

                <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                    <strong>Order Number:</strong> {$order['order_number']}<br>
                    <strong>Order Date:</strong> " . date('F j, Y \a\t g:i A', strtotime($order['order_date'])) . "<br>
                    <strong>Status:</strong> " . ucfirst($order['status']) . "
                </div>

                <h3>Order Items</h3>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <thead>
                        <tr style='background: #3498db; color: white;'>
                            <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Product</th>
                            <th style='padding: 10px; text-align: center; border: 1px solid #ddd;'>Qty</th>
                            <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Price</th>
                            <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHTML}
                    </tbody>
                    <tfoot>
                        <tr style='border-top: 2px solid #3498db;'>
                            <td colspan='3' style='padding: 10px; text-align: right; font-weight: bold;'>Subtotal:</td>
                            <td style='padding: 10px; text-align: right; font-weight: bold;'>$" . number_format($order['subtotal'], 2) . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 10px; text-align: right;'>Tax:</td>
                            <td style='padding: 10px; text-align: right;'>$" . number_format($order['tax_amount'], 2) . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 10px; text-align: right;'>Shipping:</td>
                            <td style='padding: 10px; text-align: right;'>$" . number_format($order['shipping_amount'], 2) . "</td>
                        </tr>
                        <tr style='background: #e8f4fd;'>
                            <td colspan='3' style='padding: 10px; text-align: right; font-weight: bold; font-size: 16px;'>Total:</td>
                            <td style='padding: 10px; text-align: right; font-weight: bold; font-size: 16px;'>$" . number_format($order['total_amount'], 2) . "</td>
                        </tr>
                    </tfoot>
                </table>

                <p>If you have any questions about your order, please contact our customer service.</p>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildOrderStatusUpdateHTML($order, $customer, $oldStatus, $newStatus) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Order Status Update</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Order Status Update</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>Your order status has been updated:</p>

                <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                    <strong>Order Number:</strong> {$order['order_number']}<br>
                    <strong>Previous Status:</strong> " . ucfirst($oldStatus) . "<br>
                    <strong>New Status:</strong> " . ucfirst($newStatus) . "
                </div>

                <p>You can track your order status by logging into your account.</p>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildPasswordResetHTML($customer, $resetToken) {
        $resetLink = $this->baseUrl . "/public/reset-password.php?token=" . $resetToken;

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Password Reset Request</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>You have requested to reset your password. Click the link below to create a new password:</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </p>

                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p><a href='{$resetLink}'>{$resetLink}</a></p>

                <p>This link will expire in 1 hour for security reasons.</p>

                <p>If you didn't request this password reset, please ignore this email.</p>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildWelcomeEmailHTML($customer) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to PC Parts Store</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Welcome to PC Parts Store!</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>Welcome to PC Parts Store! Your account has been successfully created.</p>

                <p>You can now:</p>
                <ul>
                    <li>Browse our extensive catalog of PC parts</li>
                    <li>Place orders online</li>
                    <li>Track your order history</li>
                    <li>Manage your account and addresses</li>
                </ul>

                <p>Start shopping by visiting our store: <a href='{$this->baseUrl}/public/index.php'>PC Parts Store</a></p>

                <p>If you have any questions, feel free to contact our customer service.</p>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Send email verification email
     */
    public function sendEmailVerification($customer, $verificationUrl) {
        $subject = 'Verify Your Email Address';
        $message = $this->buildEmailVerificationHTML($customer, $verificationUrl);
        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    /**
     * Send password reset email (URL version)
     */
    public function sendPasswordResetEmail($customer, $resetUrl) {
        $subject = 'Password Reset Request';
        $message = $this->buildPasswordResetEmailHTML($customer, $resetUrl);
        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    /**
     * Send password changed confirmation email
     */
    public function sendPasswordChangedEmail($customer) {
        $subject = 'Password Changed Successfully';
        $message = $this->buildPasswordChangedHTML($customer);
        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name'] . ' ' . $customer['last_name']
        );
    }

    /**
     * Send staff login notification
     */
    public function sendStaffLoginNotification($staff, $sessionInfo = null) {
        $subject = 'Secure Access: Staff Login Notification - ' . $this->fromName;

        $message = $this->buildStaffLoginNotificationHTML($staff, $sessionInfo);

        return $this->send(
            $staff['email'],
            $subject,
            $message,
            $staff['full_name']
        );
    }

    /**
     * Send customer login notification
     */
    public function sendCustomerLoginNotification($customer, $sessionInfo = null) {
        $subject = 'Welcome Back! You\'ve successfully logged into ' . $this->fromName;

        $message = $this->buildCustomerLoginNotificationHTML($customer, $sessionInfo);

        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name']
        );
    }

    /**
     * Send 2FA verification code
     */
    public function sendTwoFactorAuthCode($user, $verificationCode, $sessionInfo) {
        $subject = 'Security Alert: 2FA Verification Code - ' . $this->fromName;

        $message = $this->buildTwoFactorAuthCodeHTML($user, $verificationCode, $sessionInfo);

        return $this->send(
            $user['email'],
            $subject,
            $message,
            $user['full_name'] ?? $user['first_name']
        );
    }

    /**
     * Send customer purchase confirmation (Order Pending Approval)
     */
    public function sendCustomerPurchaseNotification($customer, $order) {
        $subject = 'Order Received - Pending Approval: Order #' . (isset($order['order_number']) ? $order['order_number'] : $order['id']) . ' - ' . $this->fromName;

        $message = $this->buildCustomerPurchaseNotificationHTML($customer, $order);

        return $this->send(
            $customer['email'],
            $subject,
            $message,
            $customer['first_name']
        );
    }

    /**
     * Send supplier transaction notification
     */
    public function sendSupplierTransactionNotification($supplier, $transactionType, $transactionData) {
        $subject = ucfirst($transactionType) . ' Transaction Notification - ' . $this->fromName;

        $message = $this->buildSupplierTransactionNotificationHTML($supplier, $transactionType, $transactionData);

        return $this->send(
            $supplier['email'],
            $subject,
            $message,
            isset($supplier['company_name']) ? $supplier['company_name'] : $supplier['full_name']
        );
    }

    /**
     * Send staff transaction notification (for auditing)
     */
    public function sendStaffTransactionNotification($staff, $transactionType, $transactionData) {
        $subject = 'Transaction Alert: ' . ucfirst($transactionType) . ' - ' . $this->fromName;

        $message = $this->buildStaffTransactionNotificationHTML($staff, $transactionType, $transactionData);

        return $this->send(
            $staff['email'],
            $subject,
            $message,
            $staff['full_name']
        );
    }

    /**
     * Get current email settings (for testing)
     */
    public function getSettings() {
        return [
            'smtp_host' => $this->smtpHost,
            'smtp_port' => $this->smtpPort,
            'smtp_username' => $this->smtpUsername,
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
            'site_url' => $this->baseUrl
        ];
    }

    private function buildCustomerLoginNotificationHTML($customer) {
        $loginTime = date('F j, Y \a\t g:i A');

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Welcome Back - PC Parts Central</title>
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 600px; }
                .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 30px; margin: 20px 0; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2); }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { background: linear-gradient(45deg, #0066cc, #00ccff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 28px; font-weight: 900; }
                .gradient-text { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700; }
                .login-badge { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; font-weight: 600; }
                .cta-button { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 14px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <div class='header'>
                        <h1 class='logo'>PC Parts Central</h1>
                        <p class='gradient-text' style='margin: 10px 0; font-size: 18px;'>Secure Login Confirmed</p>
                    </div>

                    <p style='color: #666; margin-bottom: 20px;'>Hello <strong>{$customer['first_name']}</strong>,</p>

                    <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>You've successfully logged into your PC Parts Central account. Welcome back!</p>

                    <div style='text-align: center; margin: 30px 0;'>
                        <div class='login-badge'>
                            <i class='fas fa-check-circle'></i> Login Successful
                        </div>
                        <p style='color: #999; margin: 15px 0; font-size: 14px;'>{$loginTime}</p>
                    </div>

                    <div style='background: rgba(0, 102, 204, 0.05); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid #0066cc;'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'>What you can do now:</h3>
                        <ul style='color: #666; margin: 0; padding-left: 20px;'>
                            <li>Browse our latest premium PC components</li>
                            <li>Manage your saved carts and wishlists</li>
                            <li>Track your order status and history</li>
                            <li>Access exclusive member discounts</li>
                        </ul>
                    </div>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->baseUrl}/public/dashboard.php' class='cta-button' style='text-decoration: none;'>
                            <i class='fas fa-shopping-bag'></i> Continue Shopping
                        </a>
                    </div>

                    <p style='color: #666; font-size: 14px; text-align: center; margin-top: 25px;'>
                        If you didn't log in recently, please <a href='{$this->baseUrl}/public/profile.php' style='color: #0066cc; text-decoration: none;'>review your account security settings</a>.
                    </p>
                </div>

                <div class='footer'>
                    <p>PC Parts Central • Premium Gaming & Workstation Components</p>
                    <p>For questions, contact our support team</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function buildCustomerPurchaseNotificationHTML($customer, $order) {
        $orderNumber = $order['order_number'] ?? $order['id'];
        $orderDate = date('F j, Y \a\t g:i A', strtotime($order['created_at'] ?? $order['order_date'] ?? 'now'));

        // Build order items HTML
        $itemsHTML = '';
        if (isset($order['items'])) {
            foreach ($order['items'] as $item) {
                $itemsHTML .= "
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                            <strong>{$item['product_name']}</strong><br>
                            <small style='color: #666;'>SKU: {$item['product_sku']}</small>
                        </td>
                        <td style='padding: 10px; text-align: center; border-bottom: 1px solid #eee;'>{$item['quantity']}</td>
                        <td style='padding: 10px; text-align: right; border-bottom: 1px solid #eee;'>₱" . number_format($item['unit_price'], 2) . "</td>
                        <td style='padding: 10px; text-align: right; border-bottom: 1px solid #eee; font-weight: 600;'>₱" . number_format($item['total_price'], 2) . "</td>
                    </tr>
                ";
            }
        }

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Order Confirmed - PC Parts Central</title>
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 800px; }
                .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 30px; margin: 20px 0; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2); }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { background: linear-gradient(45deg, #0066cc, #00ccff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 28px; font-weight: 900; }
                .order-number { background: linear-gradient(135deg, #28a745 0%, #66cc66 100%); color: white; padding: 12px 24px; border-radius: 25px; display: inline-block; font-weight: 600; margin: 15px 0; }
                .price { background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%); color: white; padding: 8px 16px; border-radius: 15px; font-weight: 700; display: inline-block; }
                .cta-button { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; display: inline-block; margin: 20px 0; }
                .product-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; border-radius: 10px; overflow: hidden; }
                .product-table th { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); color: white; padding: 15px; text-align: left; }
                .product-table td { padding: 15px; vertical-align: top; }
                .total-row { background: rgba(0, 102, 204, 0.1); font-weight: 700; }
                .footer { text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 14px; margin-top: 30px; }
                .status { background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); color: white; padding: 6px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <div class='header'>
                        <h1 class='logo'>PC Parts Central</h1>
                        <p style='color: #666; margin: 5px 0;'>Order Received - Pending Approval</p>
                        <div class='order-number'>
                            <i class='fas fa-clock'></i> Order #{$orderNumber}
                        </div>
                    </div>

                    <p style='color: #666; margin-bottom: 20px;'>Hello <strong>{$customer['first_name']}</strong>,</p>

                    <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>Thank you for your order! Your order has been received and is currently <strong>pending staff review</strong>. You'll receive a confirmation email once our team approves your order.</p>

                    <div style='background: rgba(0, 102, 204, 0.05); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid #0066cc;'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'>Order Details:</h3>
                        <p style='margin: 0; color: #666;'><strong>Order Date:</strong> {$orderDate}</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Status:</strong> <span class='status'>{$order['status']}</span></p>
                    </div>

                    <table class='product-table'>
                        <thead>
                            <tr>
                                <th style='background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%);'>Product</th>
                                <th style='background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); text-align: center;'>Qty</th>
                                <th style='background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); text-align: right;'>Price</th>
                                <th style='background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHTML}
                            <tr class='total-row'>
                                <td colspan='3' style='padding: 15px; text-align: right; font-size: 16px;'>Total Order Amount:</td>
                                <td style='padding: 15px; text-align: right; font-size: 18px; color: #0066cc;'>₱" . number_format(($order['total_amount'] ?? 0), 2) . "</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style='background: rgba(251, 191, 36, 0.1); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid #fbbf24;'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'><i class='fas fa-info-circle'></i> What Happens Next:</h3>
                        <ul style='color: #666; margin: 0; padding-left: 20px;'>
                            <li>Our staff will review your order shortly</li>
                            <li>You'll receive an email once your order is approved</li>
                            <li>Your order will then be processed and prepared for delivery</li>
                            <li>Track your order status in your account dashboard</li>
                            <li>Questions? Contact our support team</li>
                        </ul>
                    </div>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->baseUrl}/public/orders.php' class='cta-button' style='text-decoration: none;'>
                            <i class='fas fa-list'></i> View Order Details
                        </a>
                        <p style='color: #999; font-size: 14px; margin: 15px 0 0 0;'>Or <a href='{$this->baseUrl}/public/index.php' style='color: #0066cc; text-decoration: none;'>continue shopping</a></p>
                    </div>

                    <div style='border-top: 1px solid #eee; padding-top: 25px; margin-top: 30px;'>
                        <p style='color: #666; font-size: 14px; text-align: center; margin: 0;'>
                            Need help? <a href='mailto:support@pcpartscentral.com' style='color: #0066cc; text-decoration: none;'>Contact our support team</a><br>
                            Questions about your order? Reply to this email
                        </p>
                    </div>
                </div>

                <div class='footer'>
                    <p>PC Parts Central • Premium Gaming & Workstation Components</p>
                    <p>Thank you for choosing us! 🎯</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function buildStaffLoginNotificationHTML($staff) {
        $loginTime = date('F j, Y \a\t g:i A');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Staff Login Notification</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Staff Login Notification</h1>

                <p>Dear {$staff['full_name']},</p>

                <p>This is to notify you that your account has been successfully accessed in the {$this->fromName} system.</p>

                <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #3498db;'>
                    <strong>Login Details:</strong><br>
                    <strong>Time:</strong> {$loginTime}<br>
                    <strong>IP Address:</strong> {$ipAddress}<br>
                    <strong>Role:</strong> " . ucfirst($staff['role']) . "<br>
                    <strong>Email:</strong> {$staff['email']}
                </div>

                <p><strong>Security Note:</strong> If you did not perform this login, please contact your system administrator immediately and change your password.</p>

                <p>This is an automated notification. Please do not reply to this email.</p>

                <p>Best regards,<br>{$this->fromName} Security System</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildEmailVerificationHTML($customer, $verificationUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Verify Your Email</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Verify Your Email Address</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>Thank you for registering with PC Parts Store! Please verify your email address by clicking the link below:</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationUrl}' style='background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verify Email Address</a>
                </p>

                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p><a href='{$verificationUrl}'>{$verificationUrl}</a></p>

                <p>This link will expire in 24 hours for security reasons.</p>

                <p>If you didn't create this account, please ignore this email.</p>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildPasswordResetEmailHTML($customer, $resetUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Password Reset Request</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>You have requested to reset your password. Click the link below to create a new password:</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}' style='background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </p>

                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p><a href='{$resetUrl}'>{$resetUrl}</a></p>

                <p>This link will expire in 1 hour for security reasons.</p>

                <p>If you didn't request this password reset, please ignore this email.</p>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildPasswordChangedHTML($customer) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Changed</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Password Changed Successfully</h1>

                <p>Dear {$customer['first_name']} {$customer['last_name']},</p>

                <p>Your password has been successfully changed.</p>

                <p>If you did not make this change, please contact our customer service immediately.</p>

                <p>For your security, you may want to:</p>
                <ul>
                    <li>Review your recent account activity</li>
                    <li>Update your password on other sites if you used the same password</li>
                    <li>Enable two-factor authentication if available</li>
                </ul>

                <p>Best regards,<br>PC Parts Store Team</p>
            </div>
        </body>
        </html>
        ";
    }

    private function buildTwoFactorAuthCodeHTML($user, $verificationCode, $sessionInfo) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>2FA Verification Required - PC Parts Central</title>
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 600px; }
                .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 30px; margin: 20px 0; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2); }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { background: linear-gradient(45deg, #0066cc, #00ccff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 28px; font-weight: 900; }
                .alert-box { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 1px solid #f39c12; border-radius: 10px; padding: 20px; margin: 20px 0; }
                .verification-code { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; font-size: 36px; font-weight: 900; letter-spacing: 8px; padding: 20px; border-radius: 15px; text-align: center; margin: 25px 0; border: none; user-select: all; }
                .device-info { background: rgba(0, 0, 0, 0.05); border-radius: 10px; padding: 15px; margin: 20px 0; font-size: 14px; }
                .cta-button { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; display: inline-block; margin: 20px 0; text-align: center; }
                .footer { text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 14px; margin-top: 30px; }
                .security-note { background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 10px; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <div class='header'>
                        <h1 class='logo'>PC Parts Central</h1>
                        <p style='color: #666; margin: 5px 0; font-size: 16px;'>Security Verification Required</p>
                    </div>

                    <div class='alert-box'>
                        <div style='text-align: center; margin-bottom: 10px;'>
                            <i class='fas fa-shield-alt' style='font-size: 48px; color: #f39c12;'></i>
                        </div>
                        <p style='margin: 0; font-weight: 600; color: #8b4513;'><strong>2FA Verification Required</strong></p>
                        <p style='margin: 10px 0 0 0; color: #8b4513;'>We detected a login from a new device or location. For your security, please verify this login with the code below.</p>
                    </div>

                    <p style='color: #666; margin-bottom: 20px;'>Hello <strong>" . ($user['full_name'] ?? $user['first_name'] ?? 'User') . "</strong>,</p>

                    <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>A login attempt was made from a new device or location. To complete the login process, please enter the verification code:</p>

                    <div style='text-align: center; margin: 30px 0;'>
                        <div class='verification-code'>{$verificationCode}</div>
                        <p style='color: #999; font-size: 14px; margin: 10px 0;'>
                            <strong>Code expires in 10 minutes</strong><br>
                            Enter this code where prompted to complete your login
                        </p>
                    </div>

                    <div class='device-info'>
                        <h4 style='margin: 0 0 10px 0; color: #333;'><i class='fas fa-desktop'></i> Login Details:</h4>
                        <p style='margin: 0; color: #666;'><strong>Location:</strong> " . ($sessionInfo['city'] ?? 'Unknown') . ", " . ($sessionInfo['country'] ?? 'Unknown') . "</p>
                        <p style='margin: 5px 0; color: #666;'><strong>IP Address:</strong> " . ($sessionInfo['ip_address'] ?? 'Unknown') . "</p>
                        <p style='margin: 5px 0 0 0; color: #666;'><strong>Time:</strong> " . date('F j, Y \a\t g:i A', strtotime($sessionInfo['login_time'] ?? 'now')) . "</p>
                    </div>

                    <div class='security-note'>
                        <p style='margin: 0; color: #c0392b; font-weight: 600;'><i class='fas fa-exclamation-triangle'></i> Security Notice</p>
                        <p style='margin: 10px 0 0 0; color: #c0392b;'>If you did not initiate this login, please:</p>
                        <ul style='margin: 10px 0 0 0; padding-left: 20px; color: #c0392b;'>
                            <li>Change your password immediately</li>
                            <li>Contact system administrator</li>
                            <li>Do not enter the code</li>
                        </ul>
                    </div>

                    <div style='border-top: 1px solid #eee; padding-top: 25px; margin-top: 30px; text-align: center;'>
                        <p style='color: #666; font-size: 14px; margin: 0;'>
                            Having trouble with verification?<br>
                            <a href='mailto:support@pcpartscentral.com' style='color: #0066cc; text-decoration: none;'>Contact our support team</a>
                        </p>
                    </div>
                </div>

                <div class='footer'>
                    <p>PC Parts Central • Security First</p>
                    <p>This is an automated security notification</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function buildSupplierTransactionNotificationHTML($supplier, $transactionType, $transactionData) {
        $details = '';
        $amount = 0;

        switch ($transactionType) {
            case 'payment':
                $details = "Payment received for " . ($transactionData['description'] ?? 'services');
                $amount = $transactionData['amount'] ?? 0;
                break;
            case 'order':
                $details = "New purchase order received";
                break;
            case 'approval':
                $details = "Your supplier account has been approved";
                break;
            case 'rejection':
                $details = "Supplier registration update available for review";
                break;
            default:
                $details = "Transaction notification";
        }

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Transaction Notification - PC Parts Central</title>
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 700px; }
                .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 30px; margin: 20px 0; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2); }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { background: linear-gradient(45deg, #0066cc, #00ccff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 28px; font-weight: 900; }
                .status-badge { background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); color: white; padding: 6px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; text-transform: uppercase; margin: 15px 0; }
                .cta-button { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 14px; margin-top: 30px; }
                .transaction-details { background: rgba(0, 102, 204, 0.05); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid #0066cc; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <div class='header'>
                        <h1 class='logo'>PC Parts Central</h1>
                        <p style='color: #666; margin: 5px 0; font-size: 16px;'>Transaction Notification</p>
                        <div class='status-badge'>" . strtoupper($transactionType) . "</div>
                    </div>

                    <p style='color: #666; margin-bottom: 20px;'>Dear <strong>" . ($supplier['company_name'] ?? $supplier['full_name']) . "</strong>,</p>

                    <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>{$details}</p>

                    <div class='transaction-details'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'>Transaction Details:</h3>
                        <p style='margin: 0; color: #666;'><strong>Type:</strong> " . ucfirst($transactionType) . "</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>" .
                        ($amount > 0 ? "<p style='margin: 5px 0; color: #666;'><strong>Amount:</strong> ₱" . number_format($amount, 2) . "</p>" : "") . "
                        <p style='margin: 5px 0; color: #666;'><strong>Reference:</strong> " . ($transactionData['reference'] ?? 'N/A') . "</p>
                    </div>

                    <div style='background: rgba(40, 167, 69, 0.1); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid #28a745;'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'>Next Steps:</h3>
                        <ul style='color: #666; margin: 0; padding-left: 20px;'>
                            <li>Please review this transaction in your supplier dashboard</li>
                            <li>Contact us if you have any questions or concerns</li>
                            <li>Keep this notification for your records</li>
                        </ul>
                    </div>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->baseUrl}/supplier/dashboard.php' class='cta-button' style='text-decoration: none;'>
                            <i class='fas fa-tachometer-alt'></i> View Dashboard
                        </a>
                    </div>

                    <div style='border-top: 1px solid #eee; padding-top: 25px; margin-top: 30px;'>
                        <p style='color: #666; font-size: 14px; text-align: center; margin: 0;'>
                            Need assistance? <a href='mailto:support@pcpartscentral.com' style='color: #0066cc; text-decoration: none;'>Contact our support team</a><br>
                            This is an automated transaction notification
                        </p>
                    </div>
                </div>

                <div class='footer'>
                    <p>PC Parts Central • Transaction Notification System</p>
                    <p>Thank you for your partnership! 🤝</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function buildStaffTransactionNotificationHTML($staff, $transactionType, $transactionData) {
        $details = '';
        $severity = 'info';

        switch ($transactionType) {
            case 'login':
                $details = "New account login detected";
                $severity = 'success';
                break;
            case 'inventory':
                $details = "Inventory adjustment made";
                $severity = 'warning';
                break;
            case 'order':
                $details = "Order processed";
                $severity = 'success';
                break;
            case 'supplier':
                $details = "Supplier account update";
                $severity = 'info';
                break;
            default:
                $details = "System transaction completed";
                $severity = 'info';
        }

        $severityColor = [
            'success' => '#28a745',
            'warning' => '#f39c12',
            'info' => '#0066cc',
            'danger' => '#e74c3c'
        ][$severity] ?? '#0066cc';

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Transaction Alert - PC Parts Central</title>
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 700px; }
                .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 15px; padding: 30px; margin: 20px 0; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2); }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { background: linear-gradient(45deg, #0066cc, #00ccff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 28px; font-weight: 900; }
                .severity-badge { background: linear-gradient(135deg, {$severityColor} 0%, " . ($severity === 'success' ? '#66cc66' : ($severity === 'warning' ? '#ffb347' : ($severity === 'danger' ? '#ff6b6b' : '#6699ff'))) . " 100%); color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; font-weight: 600; text-transform: uppercase; font-size: 12px; margin: 15px 0; }
                .transaction-details { background: rgba(0, 102, 204, 0.05); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid {$severityColor}; }
                .cta-button { background: linear-gradient(135deg, #0066cc 0%, #00ccff 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 14px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <div class='header'>
                        <h1 class='logo'>PC Parts Central</h1>
                        <p style='color: #666; margin: 5px 0; font-size: 16px;'>Transaction Alert</p>
                        <div class='severity-badge'>" . strtoupper($transactionType) . "</div>
                    </div>

                    <p style='color: #666; margin-bottom: 20px;'>Dear <strong>{$staff['full_name']}</strong>,</p>

                    <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>{$details}</p>

                    <div class='transaction-details'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'>Transaction Information:</h3>
                        <p style='margin: 0; color: #666;'><strong>Type:</strong> " . ucfirst($transactionType) . "</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Time:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Reference:</strong> " . ($transactionData['reference'] ?? 'System Generated') . "</p>
                        <p style='margin: 5px 0; color: #666;'><strong>Description:</strong> " . ($transactionData['description'] ?? 'System transaction completed successfully') . "</p>
                    </div>

                    <div style='background: rgba(40, 167, 69, 0.1); border-radius: 10px; padding: 20px; margin: 25px 0; border-left: 4px solid #28a745;'>
                        <h3 style='margin: 0 0 15px 0; color: #333;'>Action Items:</h3>
                        <ul style='color: #666; margin: 0; padding-left: 20px;'>
                            <li>This is an automated notification for your records</li>
                            <li>Review transaction details in the admin dashboard if needed</li>
                            <li>Contact system administrator if you notice any irregularities</li>
                        </ul>
                    </div>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->baseUrl}/dashboard.php' class='cta-button' style='text-decoration: none;'>
                            <i class='fas fa-tachometer-alt'></i> Access Dashboard
                        </a>
                    </div>

                    <div style='border-top: 1px solid #eee; padding-top: 25px; margin-top: 30px;'>
                        <p style='color: #666; font-size: 14px; text-align: center; margin: 0;'>
                            System-generated notification<br>
                            Please review and take appropriate action if necessary
                        </p>
                    </div>
                </div>

                <div class='footer'>
                    <p>PC Parts Central • Staff Notification System</p>
                    <p>Maintaining system transparency and security</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

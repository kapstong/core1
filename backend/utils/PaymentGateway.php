<?php
/**
 * Payment Gateway Integration Utility Class
 * Supports multiple payment gateways (Stripe, PayPal, etc.)
 */

class PaymentGateway {
    private $gateway;
    private $config;
    private $testMode;

    public function __construct($gateway = 'stripe', $testMode = true) {
        $this->gateway = $gateway;
        $this->testMode = $testMode;
        $this->loadConfiguration();
    }

    private function loadConfiguration() {
        // Load gateway-specific settings from database
        $db = Database::getInstance()->getConnection();

        $settings = [
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'paypal_client_id' => '',
            'paypal_client_secret' => '',
            'currency' => 'PHP'
        ];

        foreach ($settings as $key => $default) {
            $query = "SELECT setting_value FROM settings WHERE setting_key = :key";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $settings[$key] = $result ? $result['setting_value'] : $default;
        }

        $this->config = $settings;
    }

    /**
     * Process payment
     */
    public function processPayment($amount, $currency, $paymentMethod, $paymentData = []) {
        switch ($this->gateway) {
            case 'stripe':
                return $this->processStripePayment($amount, $currency, $paymentMethod, $paymentData);

            case 'paypal':
                return $this->processPayPalPayment($amount, $currency, $paymentMethod, $paymentData);

            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported payment gateway'
                ];
        }
    }

    /**
     * Process Stripe payment
     */
    private function processStripePayment($amount, $currency, $paymentMethod, $paymentData) {
        // Check if Stripe is configured
        if (empty($this->config['stripe_secret_key'])) {
            return [
                'success' => false,
                'error' => 'Stripe payment gateway not configured'
            ];
        }

        try {
            // In a real implementation, you would use the Stripe PHP SDK
            // For now, we'll simulate the payment process

            // Validate payment data
            if (!isset($paymentData['token']) && !isset($paymentData['payment_method_id'])) {
                return [
                    'success' => false,
                    'error' => 'Payment token or method ID required'
                ];
            }

            // Simulate API call to Stripe
            $transactionId = 'stripe_' . time() . '_' . rand(100000, 999999);

            // Simulate success/failure randomly (90% success rate for demo)
            $success = (rand(1, 10) <= 9);

            if ($success) {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'gateway' => 'stripe',
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'completed',
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment declined by card issuer',
                    'gateway' => 'stripe'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Stripe payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process PayPal payment
     */
    private function processPayPalPayment($amount, $currency, $paymentMethod, $paymentData) {
        // Check if PayPal is configured
        if (empty($this->config['paypal_client_id']) || empty($this->config['paypal_client_secret'])) {
            return [
                'success' => false,
                'error' => 'PayPal payment gateway not configured'
            ];
        }

        try {
            // In a real implementation, you would use the PayPal PHP SDK
            // For now, we'll simulate the payment process

            $transactionId = 'paypal_' . time() . '_' . rand(100000, 999999);

            // Simulate success/failure randomly (85% success rate for demo)
            $success = (rand(1, 10) <= 8.5);

            if ($success) {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'gateway' => 'paypal',
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'completed',
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'PayPal payment failed',
                    'gateway' => 'paypal'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'PayPal payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create payment intent (for Stripe)
     */
    public function createPaymentIntent($amount, $currency, $metadata = []) {
        if ($this->gateway !== 'stripe') {
            return [
                'success' => false,
                'error' => 'Payment intent only supported for Stripe'
            ];
        }

        // Simulate creating a payment intent
        $intentId = 'pi_' . time() . '_' . rand(100000, 999999);
        $clientSecret = 'pi_' . time() . '_secret_' . rand(100000, 999999);

        return [
            'success' => true,
            'intent_id' => $intentId,
            'client_secret' => $clientSecret,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'requires_payment_method'
        ];
    }

    /**
     * Refund payment
     */
    public function refundPayment($transactionId, $amount = null, $reason = 'requested_by_customer') {
        try {
            // Simulate refund process
            $refundId = 'ref_' . time() . '_' . rand(100000, 999999);

            return [
                'success' => true,
                'refund_id' => $refundId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'completed',
                'processed_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Refund processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus($transactionId) {
        // Simulate checking payment status
        $statuses = ['completed', 'pending', 'failed', 'cancelled'];
        $randomStatus = $statuses[array_rand($statuses)];

        return [
            'transaction_id' => $transactionId,
            'status' => $randomStatus,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Validate payment data
     */
    public function validatePaymentData($paymentMethod, $paymentData) {
        $errors = [];

        switch ($paymentMethod) {
            case 'credit_card':
                if (!isset($paymentData['card_number']) || !preg_match('/^\d{13,19}$/', $paymentData['card_number'])) {
                    $errors[] = 'Invalid card number';
                }
                if (!isset($paymentData['expiry_month']) || !preg_match('/^(0[1-9]|1[0-2])$/', $paymentData['expiry_month'])) {
                    $errors[] = 'Invalid expiry month';
                }
                if (!isset($paymentData['expiry_year']) || !preg_match('/^\d{4}$/', $paymentData['expiry_year'])) {
                    $errors[] = 'Invalid expiry year';
                }
                if (!isset($paymentData['cvv']) || !preg_match('/^\d{3,4}$/', $paymentData['cvv'])) {
                    $errors[] = 'Invalid CVV';
                }
                break;

            case 'paypal':
                if (!isset($paymentData['paypal_email']) || !filter_var($paymentData['paypal_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid PayPal email';
                }
                break;

            case 'bank_transfer':
                if (!isset($paymentData['account_number']) || empty($paymentData['account_number'])) {
                    $errors[] = 'Account number required';
                }
                if (!isset($paymentData['routing_number']) || empty($paymentData['routing_number'])) {
                    $errors[] = 'Routing number required';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get supported payment methods
     */
    public function getSupportedMethods() {
        return [
            'credit_card' => [
                'name' => 'Credit Card',
                'currencies' => ['PHP', 'USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                'fields' => ['card_number', 'expiry_month', 'expiry_year', 'cvv', 'cardholder_name']
            ],
            'paypal' => [
                'name' => 'PayPal',
                'currencies' => ['PHP', 'USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                'fields' => ['paypal_email']
            ],
            'bank_transfer' => [
                'name' => 'Bank Transfer',
                'currencies' => ['PHP', 'USD', 'EUR', 'GBP'],
                'fields' => ['account_number', 'routing_number', 'account_holder']
            ],
            'cash_on_delivery' => [
                'name' => 'Cash on Delivery',
                'currencies' => ['PHP', 'USD'],
                'fields' => []
            ]
        ];
    }

    /**
     * Calculate payment fees
     */
    public function calculateFees($amount, $method) {
        $fees = [
            'credit_card' => $amount * 0.029 + 0.30, // 2.9% + $0.30
            'paypal' => $amount * 0.024 + 0.49,     // 2.4% + $0.49
            'bank_transfer' => 0,                   // No fees
            'cash_on_delivery' => 0                 // No fees
        ];

        return isset($fees[$method]) ? round($fees[$method], 2) : 0;
    }

    /**
     * Check if gateway is configured
     */
    public function isConfigured() {
        switch ($this->gateway) {
            case 'stripe':
                return !empty($this->config['stripe_secret_key']);

            case 'paypal':
                return !empty($this->config['paypal_client_id']) && !empty($this->config['paypal_client_secret']);

            default:
                return false;
        }
    }

    /**
     * Get gateway configuration status
     */
    public function getConfigurationStatus() {
        $status = [
            'gateway' => $this->gateway,
            'configured' => $this->isConfigured(),
            'test_mode' => $this->testMode
        ];

        switch ($this->gateway) {
            case 'stripe':
                $status['publishable_key_configured'] = !empty($this->config['stripe_publishable_key']);
                $status['secret_key_configured'] = !empty($this->config['stripe_secret_key']);
                break;

            case 'paypal':
                $status['client_id_configured'] = !empty($this->config['paypal_client_id']);
                $status['client_secret_configured'] = !empty($this->config['paypal_client_secret']);
                break;
        }

        return $status;
    }
}

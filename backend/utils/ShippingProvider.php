<?php
/**
 * Shipping Provider Integration Utility Class
 * Supports multiple shipping providers (FedEx, UPS, USPS, etc.)
 */

class ShippingProvider {
    private $provider;
    private $config;
    private $testMode;

    public function __construct($provider = 'fedex', $testMode = true) {
        $this->provider = $provider;
        $this->testMode = $testMode;
        $this->loadConfiguration();
    }

    private function loadConfiguration() {
        // Load shipping provider settings from database
        $db = Database::getInstance()->getConnection();

        $settings = [
            'fedex_api_key' => '',
            'fedex_secret_key' => '',
            'fedex_account_number' => '',
            'ups_access_key' => '',
            'ups_username' => '',
            'ups_password' => '',
            'usps_username' => '',
            'usps_password' => '',
            'default_weight_unit' => 'LB',
            'default_dimension_unit' => 'IN'
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
     * Calculate shipping rates
     */
    public function calculateRates($fromAddress, $toAddress, $packageDetails, $serviceType = null) {
        switch ($this->provider) {
            case 'fedex':
                return $this->calculateFedExRates($fromAddress, $toAddress, $packageDetails, $serviceType);

            case 'ups':
                return $this->calculateUPSRates($fromAddress, $toAddress, $packageDetails, $serviceType);

            case 'usps':
                return $this->calculateUSPSRates($fromAddress, $toAddress, $packageDetails, $serviceType);

            default:
                return $this->calculateFallbackRates($fromAddress, $toAddress, $packageDetails);
        }
    }

    /**
     * Create shipping label
     */
    public function createLabel($shipmentDetails) {
        switch ($this->provider) {
            case 'fedex':
                return $this->createFedExLabel($shipmentDetails);

            case 'ups':
                return $this->createUPSLabel($shipmentDetails);

            case 'usps':
                return $this->createUSPSLabel($shipmentDetails);

            default:
                return [
                    'success' => false,
                    'error' => 'Label creation not supported for this provider'
                ];
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment($trackingNumber) {
        switch ($this->provider) {
            case 'fedex':
                return $this->trackFedExShipment($trackingNumber);

            case 'ups':
                return $this->trackUPSShipment($trackingNumber);

            case 'usps':
                return $this->trackUSPSShipment($trackingNumber);

            default:
                return $this->trackFallbackShipment($trackingNumber);
        }
    }

    private function calculateFedExRates($fromAddress, $toAddress, $packageDetails, $serviceType) {
        // Check if FedEx is configured
        if (empty($this->config['fedex_api_key'])) {
            return $this->calculateFallbackRates($fromAddress, $toAddress, $packageDetails);
        }

        try {
            // In a real implementation, you would use the FedEx API
            // For now, we'll simulate rate calculation

            $weight = $packageDetails['weight'] ?? 1;
            $distance = $this->calculateDistance($fromAddress, $toAddress);

            $baseRate = 8.50; // Base rate
            $weightRate = $weight * 0.75; // Per pound
            $distanceRate = $distance * 0.05; // Per mile

            $totalRate = $baseRate + $weightRate + $distanceRate;

            $services = [
                [
                    'service_code' => 'FEDEX_GROUND',
                    'service_name' => 'FedEx Ground',
                    'rate' => round($totalRate, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+3 days')),
                    'guaranteed' => true
                ],
                [
                    'service_code' => 'FEDEX_2_DAY',
                    'service_name' => 'FedEx 2Day',
                    'rate' => round($totalRate * 1.5, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+2 days')),
                    'guaranteed' => true
                ],
                [
                    'service_code' => 'FEDEX_EXPRESS_SAVER',
                    'service_name' => 'FedEx Express Saver',
                    'rate' => round($totalRate * 2.2, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+1 day')),
                    'guaranteed' => true
                ]
            ];

            return [
                'success' => true,
                'provider' => 'fedex',
                'services' => $services
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'FedEx rate calculation failed: ' . $e->getMessage()
            ];
        }
    }

    private function calculateUPSRates($fromAddress, $toAddress, $packageDetails, $serviceType) {
        // Check if UPS is configured
        if (empty($this->config['ups_access_key'])) {
            return $this->calculateFallbackRates($fromAddress, $toAddress, $packageDetails);
        }

        try {
            // Simulate UPS rate calculation
            $weight = $packageDetails['weight'] ?? 1;
            $distance = $this->calculateDistance($fromAddress, $toAddress);

            $baseRate = 9.25;
            $weightRate = $weight * 0.85;
            $distanceRate = $distance * 0.06;

            $totalRate = $baseRate + $weightRate + $distanceRate;

            $services = [
                [
                    'service_code' => '03', // UPS Ground
                    'service_name' => 'UPS Ground',
                    'rate' => round($totalRate, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+2-3 days')),
                    'guaranteed' => true
                ],
                [
                    'service_code' => '02', // UPS 2nd Day Air
                    'service_name' => 'UPS 2nd Day Air',
                    'rate' => round($totalRate * 1.6, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+2 days')),
                    'guaranteed' => true
                ],
                [
                    'service_code' => '01', // UPS Next Day Air
                    'service_name' => 'UPS Next Day Air',
                    'rate' => round($totalRate * 2.5, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+1 day')),
                    'guaranteed' => true
                ]
            ];

            return [
                'success' => true,
                'provider' => 'ups',
                'services' => $services
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'UPS rate calculation failed: ' . $e->getMessage()
            ];
        }
    }

    private function calculateUSPSRates($fromAddress, $toAddress, $packageDetails, $serviceType) {
        // Check if USPS is configured
        if (empty($this->config['usps_username'])) {
            return $this->calculateFallbackRates($fromAddress, $toAddress, $packageDetails);
        }

        try {
            // Simulate USPS rate calculation
            $weight = $packageDetails['weight'] ?? 1;
            $distance = $this->calculateDistance($fromAddress, $toAddress);

            // USPS rates are more complex, but simplified here
            $baseRate = 6.50;
            $weightRate = $weight * 0.50;
            $distanceRate = min($distance * 0.03, 15.00); // Capped distance rate

            $totalRate = $baseRate + $weightRate + $distanceRate;

            $services = [
                [
                    'service_code' => 'PRIORITY',
                    'service_name' => 'USPS Priority Mail',
                    'rate' => round($totalRate, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+2-3 days')),
                    'guaranteed' => true
                ],
                [
                    'service_code' => 'FIRST_CLASS',
                    'service_name' => 'USPS First-Class Package',
                    'rate' => round($totalRate * 0.7, 2),
                    'currency' => 'PHP',
                    'estimated_delivery' => date('Y-m-d', strtotime('+3-5 days')),
                    'guaranteed' => false
                ]
            ];

            return [
                'success' => true,
                'provider' => 'usps',
                'services' => $services
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'USPS rate calculation failed: ' . $e->getMessage()
            ];
        }
    }

    private function calculateFallbackRates($fromAddress, $toAddress, $packageDetails) {
        // Fallback calculation when no provider is configured
        $weight = $packageDetails['weight'] ?? 1;
        $distance = $this->calculateDistance($fromAddress, $toAddress);

        $baseRate = 10.00;
        $weightRate = $weight * 1.00;
        $distanceRate = $distance * 0.10;

        $totalRate = $baseRate + $weightRate + $distanceRate;

        $services = [
            [
                'service_code' => 'STANDARD',
                'service_name' => 'Standard Shipping',
                'rate' => round($totalRate, 2),
                'currency' => 'PHP',
                'estimated_delivery' => date('Y-m-d', strtotime('+5-7 days')),
                'guaranteed' => false
            ],
            [
                'service_code' => 'EXPRESS',
                'service_name' => 'Express Shipping',
                'rate' => round($totalRate * 1.8, 2),
                'currency' => 'PHP',
                'estimated_delivery' => date('Y-m-d', strtotime('+2-3 days')),
                'guaranteed' => true
            ]
        ];

        return [
            'success' => true,
            'provider' => 'fallback',
            'services' => $services
        ];
    }

    private function calculateDistance($fromAddress, $toAddress) {
        // Simplified distance calculation
        // In a real implementation, you would use a mapping service API

        // For demo purposes, calculate rough distance based on zip codes or coordinates
        // This is a very simplified calculation

        $fromZip = $fromAddress['postal_code'] ?? '10001';
        $toZip = $toAddress['postal_code'] ?? '10001';

        // Simple distance calculation (not accurate, just for demo)
        $zipDiff = abs((int)$fromZip - (int)$toZip);

        // Assume average distance per zip code difference
        $distance = min($zipDiff * 5, 2000); // Max 2000 miles

        return max($distance, 50); // Minimum 50 miles
    }

    private function createFedExLabel($shipmentDetails) {
        // Simulate label creation
        $trackingNumber = 'FEDEX' . time() . rand(1000, 9999);

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => '/api/shipping/labels/' . $trackingNumber . '.pdf',
            'provider' => 'fedex',
            'service_type' => $shipmentDetails['service_code'] ?? 'FEDEX_GROUND'
        ];
    }

    private function createUPSLabel($shipmentDetails) {
        // Simulate label creation
        $trackingNumber = '1Z' . rand(100, 999) . rand(100, 999) . rand(100, 999) . rand(100, 999) . rand(100, 999) . 'US';

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => '/api/shipping/labels/' . $trackingNumber . '.pdf',
            'provider' => 'ups',
            'service_type' => $shipmentDetails['service_code'] ?? '03'
        ];
    }

    private function createUSPSLabel($shipmentDetails) {
        // Simulate label creation
        $trackingNumber = '9400' . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999) . 'US';

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'label_url' => '/api/shipping/labels/' . $trackingNumber . '.pdf',
            'provider' => 'usps',
            'service_type' => $shipmentDetails['service_code'] ?? 'PRIORITY'
        ];
    }

    private function trackFedExShipment($trackingNumber) {
        // Simulate tracking
        $statuses = ['Label Created', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered'];
        $randomStatus = $statuses[array_rand($statuses)];

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider' => 'fedex',
            'status' => $randomStatus,
            'estimated_delivery' => date('Y-m-d', strtotime('+2 days')),
            'tracking_history' => [
                [
                    'date' => date('Y-m-d H:i:s'),
                    'status' => $randomStatus,
                    'location' => 'Distribution Center'
                ]
            ]
        ];
    }

    private function trackUPSShipment($trackingNumber) {
        // Simulate tracking
        $statuses = ['Shipment Received', 'In Transit', 'Out for Delivery', 'Delivered'];
        $randomStatus = $statuses[array_rand($statuses)];

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider' => 'ups',
            'status' => $randomStatus,
            'estimated_delivery' => date('Y-m-d', strtotime('+1 day')),
            'tracking_history' => [
                [
                    'date' => date('Y-m-d H:i:s'),
                    'status' => $randomStatus,
                    'location' => 'Shipping Facility'
                ]
            ]
        ];
    }

    private function trackUSPSShipment($trackingNumber) {
        // Simulate tracking
        $statuses = ['Accepted', 'Processed', 'Departed', 'Arrived', 'Delivered'];
        $randomStatus = $statuses[array_rand($statuses)];

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider' => 'usps',
            'status' => $randomStatus,
            'estimated_delivery' => date('Y-m-d', strtotime('+3 days')),
            'tracking_history' => [
                [
                    'date' => date('Y-m-d H:i:s'),
                    'status' => $randomStatus,
                    'location' => 'Post Office'
                ]
            ]
        ];
    }

    private function trackFallbackShipment($trackingNumber) {
        // Fallback tracking
        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider' => 'unknown',
            'status' => 'In Transit',
            'estimated_delivery' => date('Y-m-d', strtotime('+5 days')),
            'tracking_history' => [
                [
                    'date' => date('Y-m-d H:i:s'),
                    'status' => 'In Transit',
                    'location' => 'Unknown'
                ]
            ]
        ];
    }

    /**
     * Get supported providers
     */
    public function getSupportedProviders() {
        return [
            'fedex' => [
                'name' => 'FedEx',
                'services' => ['GROUND', '2_DAY', 'EXPRESS_SAVER', 'STANDARD_OVERNIGHT'],
                'requires_credentials' => true
            ],
            'ups' => [
                'name' => 'UPS',
                'services' => ['GROUND', '2ND_DAY_AIR', 'NEXT_DAY_AIR'],
                'requires_credentials' => true
            ],
            'usps' => [
                'name' => 'USPS',
                'services' => ['PRIORITY', 'FIRST_CLASS', 'MEDIA_MAIL'],
                'requires_credentials' => true
            ],
            'fallback' => [
                'name' => 'Standard Shipping',
                'services' => ['STANDARD', 'EXPRESS'],
                'requires_credentials' => false
            ]
        ];
    }

    /**
     * Check if provider is configured
     */
    public function isConfigured() {
        switch ($this->provider) {
            case 'fedex':
                return !empty($this->config['fedex_api_key']);

            case 'ups':
                return !empty($this->config['ups_access_key']);

            case 'usps':
                return !empty($this->config['usps_username']);

            default:
                return true; // Fallback is always available
        }
    }

    /**
     * Get provider configuration status
     */
    public function getConfigurationStatus() {
        $status = [
            'provider' => $this->provider,
            'configured' => $this->isConfigured(),
            'test_mode' => $this->testMode
        ];

        switch ($this->provider) {
            case 'fedex':
                $status['api_key_configured'] = !empty($this->config['fedex_api_key']);
                $status['account_number_configured'] = !empty($this->config['fedex_account_number']);
                break;

            case 'ups':
                $status['access_key_configured'] = !empty($this->config['ups_access_key']);
                $status['username_configured'] = !empty($this->config['ups_username']);
                break;

            case 'usps':
                $status['username_configured'] = !empty($this->config['usps_username']);
                break;
        }

        return $status;
    }
}

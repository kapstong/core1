<?php
/**
 * Barcode Generator Utility Class
 * Generates barcodes for products using various formats
 */

class BarcodeGenerator {
    private $templates;

    public function __construct() {
        $this->loadTemplates();
    }

    private function loadTemplates() {
        // Load barcode templates from database
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance()->getConnection();

            $stmt = $db->query("SELECT * FROM barcode_templates WHERE is_default = 1 LIMIT 1");
            $this->templates = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$this->templates) {
                // Fallback to default settings
                $this->templates = [
                    'type' => 'code128',
                    'width' => 300,
                    'height' => 100,
                    'font_size' => 12,
                    'show_text' => 1
                ];
            }
        } catch (Exception $e) {
            // Fallback if database not available
            $this->templates = [
                'type' => 'code128',
                'width' => 300,
                'height' => 100,
                'font_size' => 12,
                'show_text' => 1
            ];
        }
    }

    /**
     * Generate barcode image
     */
    public function generateBarcode($data, $type = null, $options = []) {
        $type = $type ?? $this->templates['type'];

        switch ($type) {
            case 'code128':
                return $this->generateCode128($data, $options);

            case 'code39':
                return $this->generateCode39($data, $options);

            case 'ean13':
                return $this->generateEAN13($data, $options);

            case 'qrcode':
                return $this->generateQRCode($data, $options);

            default:
                throw new Exception("Unsupported barcode type: {$type}");
        }
    }

    /**
     * Generate Code 128 barcode
     */
    private function generateCode128($data, $options = []) {
        $width = $options['width'] ?? $this->templates['width'];
        $height = $options['height'] ?? $this->templates['height'];
        $showText = $options['show_text'] ?? $this->templates['show_text'];

        // Code 128 encoding patterns (simplified implementation)
        $patterns = $this->getCode128Patterns();

        // Start with START-A (103)
        $encoded = $patterns[103];

        // Encode each character
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];

            // Check if character is in patterns
            if (isset($patterns[$char])) {
                $encoded .= $patterns[$char];
            } else {
                // Use space pattern for unsupported characters
                $encoded .= $patterns[' '];
            }
        }

        // Add stop pattern
        $encoded .= $patterns[106]; // STOP

        return $this->renderBarcodeImage($encoded, $width, $height, $data, $showText);
    }

    /**
     * Generate Code 39 barcode
     */
    private function generateCode39($data, $options = []) {
        $width = $options['width'] ?? $this->templates['width'];
        $height = $options['height'] ?? $this->templates['height'];
        $showText = $options['show_text'] ?? $this->templates['show_text'];

        // Convert to uppercase for Code 39
        $data = strtoupper($data);

        $patterns = $this->getCode39Patterns();

        // Start with asterisk
        $encoded = $patterns['*'];

        // Encode each character
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            if (!isset($patterns[$char])) {
                throw new Exception("Invalid character for Code 39: {$char}");
            }
            $encoded .= $patterns[$char];
        }

        // End with asterisk
        $encoded .= $patterns['*'];

        return $this->renderBarcodeImage($encoded, $width, $height, $data, $showText);
    }

    /**
     * Generate EAN-13 barcode
     */
    private function generateEAN13($data, $options = []) {
        $width = $options['width'] ?? $this->templates['width'];
        $height = $options['height'] ?? $this->templates['height'];
        $showText = $options['show_text'] ?? $this->templates['show_text'];

        // EAN-13 must be exactly 12 digits (13th is check digit)
        if (!preg_match('/^\d{12,13}$/', $data)) {
            throw new Exception("EAN-13 must be 12-13 digits");
        }

        // Calculate check digit if not provided
        if (strlen($data) === 12) {
            $data .= $this->calculateEAN13CheckDigit($data);
        }

        // EAN-13 encoding (simplified)
        $leftPatterns = $this->getEAN13LeftPatterns();
        $rightPatterns = $this->getEAN13RightPatterns();

        // Start guard
        $encoded = '101';

        // Left side (first 6 digits)
        for ($i = 0; $i < 6; $i++) {
            $digit = $data[$i];
            $encoded .= $leftPatterns[$digit];
        }

        // Middle guard
        $encoded .= '01010';

        // Right side (last 6 digits)
        for ($i = 6; $i < 12; $i++) {
            $digit = $data[$i];
            $encoded .= $rightPatterns[$digit];
        }

        // End guard
        $encoded .= '101';

        return $this->renderBarcodeImage($encoded, $width, $height, $data, $showText);
    }

    /**
     * Generate QR Code (simplified - returns placeholder)
     */
    private function generateQRCode($data, $options = []) {
        // For a full implementation, you would need a QR code library
        // For now, return a placeholder
        $width = $options['width'] ?? 200;
        $height = $options['height'] ?? 200;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        // Draw a simple QR-like pattern (placeholder)
        $blockSize = 10;
        for ($y = 20; $y < $height - 20; $y += $blockSize) {
            for ($x = 20; $x < $width - 20; $x += $blockSize) {
                if (($x + $y) % (2 * $blockSize) < $blockSize) {
                    imagefilledrectangle($image, $x, $y, $x + $blockSize - 1, $y + $blockSize - 1, $black);
                }
            }
        }

        // Add text
        if ($options['show_text'] ?? $this->templates['show_text']) {
            $fontSize = 3;
            $textWidth = imagefontwidth($fontSize) * strlen($data);
            $x = ($width - $textWidth) / 2;
            $y = $height - 10;
            imagestring($image, $fontSize, $x, $y, $data, $black);
        }

        return $image;
    }

    /**
     * Render barcode as image
     */
    private function renderBarcodeImage($encoded, $width, $height, $text, $showText) {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        $barcodeHeight = $showText ? $height - 20 : $height;
        $x = 10;
        $barWidth = ($width - 20) / strlen($encoded);

        // Draw bars
        for ($i = 0; $i < strlen($encoded); $i++) {
            if ($encoded[$i] === '1') {
                $barHeight = $barcodeHeight * 0.8; // 80% height for narrow bars
                $y = ($height - $barcodeHeight) / 2;
                imagefilledrectangle($image, $x, $y, $x + $barWidth - 1, $y + $barHeight, $black);
            }
            $x += $barWidth;
        }

        // Add text
        if ($showText) {
            $fontSize = 3;
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $x = ($width - $textWidth) / 2;
            $y = $height - 15;
            imagestring($image, $fontSize, $x, $y, $text, $black);
        }

        return $image;
    }

    /**
     * Get Code 128 patterns
     */
    private function getCode128Patterns() {
        // Simplified Code 128 patterns (only basic characters)
        return [
            ' ' => '11011001100', '!' => '11001101100', '"' => '11001100110',
            '#' => '10010011000', '$' => '10010001100', '%' => '10001001100',
            '&' => '10011001000', "'" => '10011000100', '(' => '10001100100',
            ')' => '11001001000', '*' => '11001000100', '+' => '11000100100',
            ',' => '10110011100', '-' => '10011011100', '.' => '10011001110',
            '/' => '10111001100', '0' => '10011101100', '1' => '10011100110',
            '2' => '11001110010', '3' => '11001011100', '4' => '11001001110',
            '5' => '11011100100', '6' => '11001110100', '7' => '11101101110',
            '8' => '11101001100', '9' => '11100101100', ':' => '11100100110',
            ';' => '11101100100', '<' => '11100110100', '=' => '11100110010',
            '>' => '11011011000', '?' => '11011000110', '@' => '11000110110',
            'A' => '10100011000', 'B' => '10001011000', 'C' => '10001000110',
            'D' => '10110001000', 'E' => '10001101000', 'F' => '10001100010',
            'G' => '11010001000', 'H' => '11000101000', 'I' => '11000100010',
            'J' => '10110111000', 'K' => '10110001110', 'L' => '10001101110',
            'M' => '10111011000', 'N' => '10111000110', 'O' => '10001110110',
            'P' => '11101110110', 'Q' => '11010001110', 'R' => '11000101110',
            'S' => '11011101000', 'T' => '11011100010', 'U' => '11011101110',
            'V' => '11101011000', 'W' => '11101000110', 'X' => '11100010110',
            'Y' => '11101101000', 'Z' => '11101100010', '[' => '11100011010',
            '\\' => '11101111010', ']' => '11001000010', '^' => '11110001010',
            '_' => '10100110000', '`' => '10100001100', 'a' => '10010110000',
            'b' => '10010000110', 'c' => '10000101100', 'd' => '10000100110',
            'e' => '10110010000', 'f' => '10110000100', 'g' => '10011010000',
            'h' => '10011000010', 'i' => '10000110100', 'j' => '10000110010',
            'k' => '11000010010', 'l' => '11001010000', 'm' => '11110111010',
            'n' => '11000010100', 'o' => '10001111010', 'p' => '10100111100',
            'q' => '10010111100', 'r' => '10010011110', 's' => '10111100100',
            't' => '10011110100', 'u' => '10011110010', 'v' => '11110100100',
            'w' => '11110010100', 'x' => '11110010010', 'y' => '11011011110',
            'z' => '11011110110', '{' => '11110110110', '|' => '10101111000',
            '}' => '10100011110', '~' => '10001011110',
            // Special characters
            103 => '11010000100', // START-A
            106 => '11000111010'  // STOP
        ];
    }

    /**
     * Get Code 39 patterns
     */
    private function getCode39Patterns() {
        return [
            '0' => '101000111011101', '1' => '111010001010111', '2' => '101110001010111',
            '3' => '111011100010101', '4' => '101000111010111', '5' => '111010001110101',
            '6' => '101110001110101', '7' => '101000101110111', '8' => '111010001011101',
            '9' => '101110001011101', 'A' => '111010100010111', 'B' => '101110100010111',
            'C' => '111011101000101', 'D' => '101011100010111', 'E' => '111010111000101',
            'F' => '101110111000101', 'G' => '101010001110111', 'H' => '111010100011101',
            'I' => '101110100011101', 'J' => '101011100011101', 'K' => '111010101000111',
            'L' => '101110101000111', 'M' => '111011101010001', 'N' => '101011101000111',
            'O' => '111010111010001', 'P' => '101110111010001', 'Q' => '101010111000111',
            'R' => '111010101110001', 'S' => '101110101110001', 'T' => '101011101110001',
            'U' => '111000101010111', 'V' => '100011101010111', 'W' => '111000111010101',
            'X' => '100010111010111', 'Y' => '111000101110101', 'Z' => '100011101110101',
            '-' => '100010101110111', '.' => '111000101011101', ' ' => '100011101011101',
            '*' => '100010111011101', '$' => '100010001010001', '/' => '100010001010001',
            '+' => '100010100010001', '%' => '101000100010001'
        ];
    }

    /**
     * Get EAN-13 left side patterns
     */
    private function getEAN13LeftPatterns() {
        return [
            '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101',
            '4' => '0100011', '5' => '0110001', '6' => '0101111', '7' => '0111011',
            '8' => '0110111', '9' => '0001011'
        ];
    }

    /**
     * Get EAN-13 right side patterns
     */
    private function getEAN13RightPatterns() {
        return [
            '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010',
            '4' => '1011100', '5' => '1001110', '6' => '1010000', '7' => '1000100',
            '8' => '1001000', '9' => '1110100'
        ];
    }

    /**
     * Calculate EAN-13 check digit
     */
    private function calculateEAN13CheckDigit($data) {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = intval($data[$i]);
            $sum += $digit * (($i % 2 === 0) ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit;
    }

    /**
     * Save barcode as PNG file
     */
    public function saveBarcode($data, $filename, $type = null, $options = []) {
        $image = $this->generateBarcode($data, $type, $options);

        // Ensure directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagepng($image, $filename);
        imagedestroy($image);

        return $filename;
    }

    /**
     * Output barcode directly to browser
     */
    public function outputBarcode($data, $type = null, $options = []) {
        $image = $this->generateBarcode($data, $type, $options);

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }

    /**
     * Get supported barcode types
     */
    public function getSupportedTypes() {
        return [
            'code128' => [
                'name' => 'Code 128',
                'description' => 'High-density alphanumeric barcode',
                'max_length' => null
            ],
            'code39' => [
                'name' => 'Code 39',
                'description' => 'Alphanumeric barcode with check digit',
                'max_length' => null
            ],
            'ean13' => [
                'name' => 'EAN-13',
                'description' => '13-digit product barcode',
                'max_length' => 13
            ],
            'qrcode' => [
                'name' => 'QR Code',
                'description' => '2D barcode for various data',
                'max_length' => null
            ]
        ];
    }
}

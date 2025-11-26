<?php
/**
 * Barcode Generation Service
 * Handles creation and management of product barcodes
 */

require_once __DIR__ . '/vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService {
    private $generator;
    private $db;
    private $outputDir;

    public function __construct() {
        $this->generator = new BarcodeGeneratorPNG();
        $this->db = new Database();
        $this->outputDir = __DIR__ . '/../../public/barcodes';

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }

    public function generateForProduct($productId) {
        try {
            $conn = $this->db->getConnection();
            
            // Get product details
            $stmt = $conn->prepare("SELECT sku, name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Product not found");
            }

            // Generate barcode
            $barcodeData = $this->generator->getBarcode(
                $product['sku'],
                $this->generator::TYPE_CODE_128
            );

            // Save barcode image
            $filename = $this->sanitizeFilename($product['sku']) . '.png';
            $filepath = $this->outputDir . '/' . $filename;
            file_put_contents($filepath, $barcodeData);

            // Create SVG version for high-quality printing
            $svgGenerator = new BarcodeGeneratorSVG();
            $svgData = $svgGenerator->getBarcode(
                $product['sku'],
                $svgGenerator::TYPE_CODE_128
            );
            $svgFilepath = $this->outputDir . '/' . $this->sanitizeFilename($product['sku']) . '.svg';
            file_put_contents($svgFilepath, $svgData);

            // Update product record
            $stmt = $conn->prepare("
                UPDATE products 
                SET barcode_path = ?, 
                    barcode_svg_path = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                '/barcodes/' . $filename,
                '/barcodes/' . $this->sanitizeFilename($product['sku']) . '.svg',
                $productId
            ]);

            return [
                'png_url' => '/barcodes/' . $filename,
                'svg_url' => '/barcodes/' . $this->sanitizeFilename($product['sku']) . '.svg'
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to generate barcode: " . $e->getMessage());
        }
    }

    public function generateBarcodeLabel($productId, $template = 'default') {
        try {
            $conn = $this->db->getConnection();
            
            // Get product details
            $stmt = $conn->prepare("
                SELECT p.*, c.name as category_name 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Product not found");
            }

            // Get template settings
            $stmt = $conn->prepare("
                SELECT * FROM barcode_templates 
                WHERE name = ? OR is_default = 1 
                ORDER BY is_default DESC 
                LIMIT 1
            ");
            $stmt->execute([$template]);
            $templateSettings = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate label with barcode
            $label = $this->createLabelImage($product, $templateSettings);

            // Save label
            $filename = 'label_' . $this->sanitizeFilename($product['sku']) . '.png';
            $filepath = $this->outputDir . '/' . $filename;
            imagepng($label, $filepath);
            imagedestroy($label);

            return '/barcodes/' . $filename;
        } catch (Exception $e) {
            throw new Exception("Failed to generate barcode label: " . $e->getMessage());
        }
    }

    public function generateBulkBarcodes($productIds) {
        $results = [];
        foreach ($productIds as $productId) {
            try {
                $results[$productId] = $this->generateForProduct($productId);
            } catch (Exception $e) {
                $results[$productId] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private function createLabelImage($product, $template) {
        // Create image based on template settings
        $width = $template['width'] ?? 300;
        $height = $template['height'] ?? 150;
        $fontSize = $template['font_size'] ?? 12;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Fill background
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        // Generate barcode
        $barcodeData = $this->generator->getBarcode(
            $product['sku'],
            $this->generator::TYPE_CODE_128
        );
        
        // Add barcode to image
        $barcode = imagecreatefromstring($barcodeData);
        $barcodeWidth = imagesx($barcode);
        $barcodeHeight = imagesy($barcode);
        
        imagecopy(
            $image,
            $barcode,
            ($width - $barcodeWidth) / 2,
            10,
            0,
            0,
            $barcodeWidth,
            $barcodeHeight
        );

        // Add text
        $font = __DIR__ . '/../../assets/fonts/Arial.ttf';
        
        // Product name
        imagettftext(
            $image,
            $fontSize,
            0,
            10,
            $height - 40,
            $black,
            $font,
            $product['name']
        );

        // SKU
        imagettftext(
            $image,
            $fontSize - 2,
            0,
            10,
            $height - 20,
            $black,
            $font,
            'SKU: ' . $product['sku']
        );

        // Price
        imagettftext(
            $image,
            $fontSize,
            0,
            $width - 100,
            $height - 20,
            $black,
            $font,
            'P' . number_format($product['selling_price'], 2)
        );

        return $image;
    }

    private function sanitizeFilename($filename) {
        // Remove or replace special characters
        $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $filename);
        return strtolower($filename);
    }
}
<?php
/**
 * Image Upload Utility Class
 * Handles product image uploads, validation, and processing
 */

class ImageUpload {
    private $uploadDir;
    private $maxFileSize;
    private $allowedTypes;
    private $maxWidth;
    private $maxHeight;
    private $createThumbnails;

    public function __construct($config = []) {
        $this->uploadDir = $config['upload_dir'] ?? __DIR__ . '/../../public/assets/img/';
        $this->maxFileSize = $config['max_file_size'] ?? 5 * 1024 * 1024; // 5MB
        $this->allowedTypes = $config['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->maxWidth = $config['max_width'] ?? 1200;
        $this->maxHeight = $config['max_height'] ?? 1200;
        $this->createThumbnails = $config['create_thumbnails'] ?? true;

        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Create products subdirectory if it doesn't exist
        $productsDir = $this->uploadDir . 'products/';
        if (!is_dir($productsDir)) {
            mkdir($productsDir, 0755, true);
        }

        // Create thumbnails directory if needed
        if ($this->createThumbnails && !is_dir($productsDir . 'thumbnails/')) {
            mkdir($productsDir . 'thumbnails/', 0755, true);
        }

        // Update uploadDir to products subdirectory
        $this->uploadDir = $productsDir;
    }

    /**
     * Upload and process product image
     *
     * @param array $file $_FILES array element
     * @param string $productId Product ID for naming (optional for temp uploads)
     * @return array Result with success status and file info
     */
    public function uploadProductImage($file, $productId = null) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }

            // Generate filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $prefix = $productId ? 'product_' . $productId : 'temp_' . session_id();
            $filename = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $filepath = $this->uploadDir . $filename;

            // Process and save image
            $imageInfo = $this->processImage($file['tmp_name'], $filepath, $extension);

            if (!$imageInfo) {
                return [
                    'success' => false,
                    'error' => 'Failed to process image'
                ];
            }

            // Create thumbnail if enabled
            $thumbnailPath = null;
            if ($this->createThumbnails) {
                $thumbnailPath = $this->createThumbnail($filepath, $filename);
            }

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $filepath,
                'url' => $this->getImageUrl($filename),
                'thumbnail_url' => $thumbnailPath ? $this->getThumbnailUrl(basename($thumbnailPath)) : null,
                'size' => $imageInfo['size'],
                'width' => $imageInfo['width'],
                'height' => $imageInfo['height'],
                'mime_type' => $imageInfo['mime']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => $this->getUploadErrorMessage($file['error'])
            ];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB'
            ];
        }

        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes)
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!in_array($mimeType, $allowedMimes)) {
            return [
                'valid' => false,
                'error' => 'Invalid image file'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Process and save image
     */
    private function processImage($sourcePath, $destinationPath, $extension) {
        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mime = $imageInfo['mime'];

        // Resize if too large
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $resized = $this->resizeImage($sourcePath, $destinationPath, $width, $height, $extension);
            if (!$resized) {
                return false;
            }

            // Get new dimensions
            $newInfo = getimagesize($destinationPath);
            $width = $newInfo[0];
            $height = $newInfo[1];
        } else {
            // Just move the file
            if (!move_uploaded_file($sourcePath, $destinationPath)) {
                return false;
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'size' => filesize($destinationPath),
            'mime' => $mime
        ];
    }

    /**
     * Resize image while maintaining aspect ratio
     */
    private function resizeImage($sourcePath, $destinationPath, $width, $height, $extension) {
        // Calculate new dimensions
        $aspectRatio = $width / $height;

        if ($width > $height) {
            $newWidth = $this->maxWidth;
            $newHeight = $this->maxWidth / $aspectRatio;
        } else {
            $newHeight = $this->maxHeight;
            $newWidth = $this->maxHeight * $aspectRatio;
        }

        // Ensure we don't exceed both dimensions
        if ($newWidth > $this->maxWidth) {
            $newWidth = $this->maxWidth;
            $newHeight = $newWidth / $aspectRatio;
        }

        if ($newHeight > $this->maxHeight) {
            $newHeight = $this->maxHeight;
            $newWidth = $newHeight * $aspectRatio;
        }

        $newWidth = round($newWidth);
        $newHeight = round($newHeight);

        // Create image resource based on type
        $sourceImage = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case 'webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
        }

        if (!$sourceImage) {
            return false;
        }

        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        // Resize
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save resized image
        $success = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($newImage, $destinationPath, 90);
                break;
            case 'png':
                $success = imagepng($newImage, $destinationPath, 9);
                break;
            case 'gif':
                $success = imagegif($newImage, $destinationPath);
                break;
            case 'webp':
                $success = imagewebp($newImage, $destinationPath, 90);
                break;
        }

        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($newImage);

        return $success;
    }

    /**
     * Create thumbnail image
     */
    private function createThumbnail($sourcePath, $filename) {
        $thumbnailPath = $this->uploadDir . 'thumbnails/thumb_' . $filename;

        // Create 150x150 thumbnail
        return $this->resizeImage($sourcePath, $thumbnailPath, $this->maxWidth, $this->maxHeight, pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Get full image URL
     */
    private function getImageUrl($filename) {
        // Return relative path that works with htaccess routing
        return 'assets/img/products/' . $filename;
    }

    /**
     * Get thumbnail URL
     */
    private function getThumbnailUrl($filename) {
        // Return relative path that works with htaccess routing
        return 'assets/img/products/thumbnails/' . $filename;
    }

    /**
     * Delete product image
     */
    public function deleteProductImage($filename) {
        $filepath = $this->uploadDir . $filename;
        $thumbnailPath = $this->uploadDir . 'thumbnails/thumb_' . $filename;

        $deleted = [];
        if (file_exists($filepath)) {
            unlink($filepath);
            $deleted[] = 'main image';
        }

        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
            $deleted[] = 'thumbnail';
        }

        return $deleted;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive in HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}

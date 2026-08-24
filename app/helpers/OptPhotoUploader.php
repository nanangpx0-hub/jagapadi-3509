<?php
/**
 * OPT Photo Uploader
 * Comprehensive photo upload system for OPT (Organisme Pengganggu Tumbuhan)
 * 
 * Features:
 * - File organization by year/month
 * - Unique filename with timestamp + hash
 * - Automatic compression for files >2MB
 * - Maintain aspect ratio
 * - Target quality 80% for JPEG
 * - Max dimensions 1920px
 * - MIME type and file signature validation
 * - Sanitized filenames
 * - Automatic rollback on error
 */

class OptPhotoUploader {
    
    // Configuration constants
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB max upload (before compression)
    const COMPRESS_THRESHOLD = 2 * 1024 * 1024; // 2MB - compress if larger
    const MAX_WIDTH = 1920;
    const MAX_HEIGHT = 1920;
    const JPEG_QUALITY = 80;
    const PNG_COMPRESSION = 6; // 0-9, 6 is good balance
    
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/jpg',
        'image/webp'
    ];
    
    // File signatures (magic bytes) for validation
    const FILE_SIGNATURES = [
        'image/jpeg' => ['FFD8FF'],
        'image/png' => ['89504E47'],
        'image/gif' => ['47494638'],
        'image/webp' => ['52494646'] // RIFF header for WebP
    ];
    
    // Thumbnail configuration
    const THUMB_WIDTH = 200;
    const THUMB_HEIGHT = 200;
    
    private $uploadDir = 'public/uploads/opt/';
    private $errors = [];
    
    /**
     * Upload photo for OPT
     * 
     * @param array $file $_FILES array
     * @param int|null $optId OPT ID for update (null for new)
     * @param string|null $oldPhotoPath Old photo path to delete (for update)
     * @return array Result array with 'success', 'path', 'message', etc.
     */
    public function upload($file, $optId = null, $oldPhotoPath = null, ?string $label = null) {
        $this->errors = [];
        
        try {
            // 1. Rate limiting check
            if (!$this->checkRateLimit()) {
                return [
                    'success' => false,
                    'error' => 'Terlalu banyak request upload. Silakan coba lagi dalam beberapa saat.',
                    'code' => 'RATE_LIMIT'
                ];
            }
            
            // 2. Basic validation
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error'],
                    'code' => $validation['code'] ?? 'VALIDATION_ERROR'
                ];
            }
            
            // 3. Validate MIME type and file signature
            $mimeValidation = $this->validateMimeType($file['tmp_name']);
            if (!$mimeValidation['valid']) {
                return [
                    'success' => false,
                    'error' => $mimeValidation['error'],
                    'code' => 'MIME_VALIDATION_ERROR'
                ];
            }
            
            $mimeType = $mimeValidation['mime_type'];
            $extension = $this->getExtensionFromMime($mimeType);
            
            // 4. Prepare directory structure (year/month)
            $year = date('Y');
            $month = date('m');
            $targetDir = ROOT_PATH . '/' . $this->uploadDir . $year . '/' . $month . '/';

            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $this->logError('Failed to create upload directory: ' . $targetDir);
                    return [
                        'success' => false,
                        'error' => 'Gagal membuat direktori upload. Hubungi administrator.',
                        'code' => 'DIRECTORY_ERROR'
                    ];
                }
            }

            // Lindungi modul upload OPT: blokir eksekusi skrip, gambar tetap dapat disajikan.
            $this->createHtaccess(ROOT_PATH . '/' . $this->uploadDir);
            
            // 5. Generate unique filename (mengikuti nama opsi/OPT bila tersedia)
            $filename = $this->generateUniqueFilename($extension, $optId, $label);
            $targetPath = $targetDir . $filename;
            $relativePath = $this->uploadDir . $year . '/' . $month . '/' . $filename;
            
            // 6. Process and save image
            $fileSize = filesize($file['tmp_name']);
            $needsCompression = $fileSize > self::COMPRESS_THRESHOLD;
            
            if ($needsCompression) {
                $result = $this->compressAndSave($file['tmp_name'], $targetPath, $mimeType);
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'error' => $result['error'],
                        'code' => 'COMPRESSION_ERROR'
                    ];
                }
                $originalSize = $fileSize;
                $finalSize = filesize($targetPath);
                $compressed = true;
            } else {
                // Check if resizing is needed (dimensions)
                $dimensions = getimagesize($file['tmp_name']);
                if ($dimensions && ($dimensions[0] > self::MAX_WIDTH || $dimensions[1] > self::MAX_HEIGHT)) {
                    $result = $this->compressAndSave($file['tmp_name'], $targetPath, $mimeType);
                    if (!$result['success']) {
                        return [
                            'success' => false,
                            'error' => $result['error'],
                            'code' => 'RESIZE_ERROR'
                        ];
                    }
                    $originalSize = $fileSize;
                    $finalSize = filesize($targetPath);
                    $compressed = true;
                } else {
                    // Just move the file
                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $this->logError('Failed to move uploaded file to: ' . $targetPath);
                        return [
                            'success' => false,
                            'error' => 'Gagal menyimpan file. Coba lagi.',
                            'code' => 'UPLOAD_ERROR'
                        ];
                    }
                    $originalSize = $finalSize = $fileSize;
                    $compressed = false;
                }
            }
            
            // 7. Delete old photo if updating
            if ($oldPhotoPath) {
                $this->deletePhoto($oldPhotoPath);
            }
            
            // 8. Record rate limit
            $this->recordRateLimit();
            
            // 9. Return success with proper path format (without 'public/' prefix for consistency)
            // Path format: uploads/opt/YYYY/MM/filename.jpg
            $pathForDb = str_replace('public/', '', $relativePath);
            
            return [
                'success' => true,
                'path' => $pathForDb,
                'full_path' => $targetPath,
                'original_size' => $originalSize,
                'final_size' => $finalSize,
                'compressed' => $compressed,
                'reduction_percent' => $compressed ? round((1 - ($finalSize / $originalSize)) * 100, 2) : 0,
                'message' => $compressed 
                    ? "Foto berhasil diupload dan dikompresi dari " . $this->formatBytes($originalSize) . " menjadi " . $this->formatBytes($finalSize)
                    : "Foto berhasil diupload"
            ];
            
        } catch (Exception $e) {
            $this->logError('Upload exception: ' . $e->getMessage());
            
            // Rollback: delete file if it was created
            if (isset($targetPath) && file_exists($targetPath)) {
                @unlink($targetPath);
            }
            
            return [
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengupload foto: ' . $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }
    
    /**
     * Delete photo
     */
    public function deletePhoto($photoPath) {
        if (empty($photoPath)) {
            return true;
        }
        
        // Handle both relative paths (with/without public/) and absolute paths
        $fullPath = $photoPath;
        if (strpos($photoPath, '/') !== 0 && strpos($photoPath, 'public/') !== 0) {
            $fullPath = ROOT_PATH . '/public/' . ltrim($photoPath, '/');
        } elseif (strpos($photoPath, 'public/') === 0) {
            $fullPath = ROOT_PATH . '/' . $photoPath;
        } elseif (strpos($photoPath, '/') === 0) {
            $fullPath = ROOT_PATH . '/public' . $photoPath;
        }
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            return @unlink($fullPath);
        }
        
        return true;
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Parameter file tidak valid', 'code' => 'INVALID_PARAMETER'];
        }
        
        // Check upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'error' => 'Tidak ada file yang diupload', 'code' => 'NO_FILE'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'error' => 'File terlalu besar. Maksimum ' . $this->formatBytes(self::MAX_FILE_SIZE), 'code' => 'FILE_TOO_LARGE'];
            case UPLOAD_ERR_PARTIAL:
                return ['valid' => false, 'error' => 'File hanya terupload sebagian', 'code' => 'PARTIAL_UPLOAD'];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['valid' => false, 'error' => 'Direktori temporary tidak ditemukan', 'code' => 'NO_TMP_DIR'];
            case UPLOAD_ERR_CANT_WRITE:
                return ['valid' => false, 'error' => 'Gagal menulis file ke disk', 'code' => 'WRITE_ERROR'];
            case UPLOAD_ERR_EXTENSION:
                return ['valid' => false, 'error' => 'Upload dihentikan oleh ekstensi PHP', 'code' => 'EXTENSION_ERROR'];
            default:
                return ['valid' => false, 'error' => 'Error tidak dikenal saat upload', 'code' => 'UNKNOWN_ERROR'];
        }
        
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'File terlalu besar. Maksimum ' . $this->formatBytes(self::MAX_FILE_SIZE), 'code' => 'FILE_TOO_LARGE'];
        }
        
        if ($file['size'] == 0) {
            return ['valid' => false, 'error' => 'File kosong', 'code' => 'EMPTY_FILE'];
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return ['valid' => false, 'error' => 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF', 'code' => 'INVALID_EXTENSION'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate MIME type and file signature
     */
    private function validateMimeType($tmpPath) {
        // Use finfo to detect MIME type
        if (!function_exists('finfo_open')) {
            // Fallback: use getimagesize
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => 'File bukan gambar yang valid', 'code' => 'INVALID_IMAGE'];
            }
            $mimeType = $imageInfo['mime'];
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpPath);
        }
        
        // Check MIME type
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'error' => 'Tipe file tidak diizinkan', 'code' => 'INVALID_MIME'];
        }
        
        // Validate file signature (magic bytes)
        $handle = @fopen($tmpPath, 'rb');
        if ($handle) {
            // Read first 8 bytes for PNG (PNG signature is 8 bytes), 4 bytes for others
            $readBytes = ($mimeType === 'image/png') ? 8 : 4;
            $bytes = fread($handle, $readBytes);
            fclose($handle);
            
            if (strlen($bytes) < 4) {
                return ['valid' => false, 'error' => 'File terlalu kecil atau rusak', 'code' => 'INVALID_FILE'];
            }
            
            $hex = bin2hex($bytes);
            $hexUpper = strtoupper($hex);
            
            $validSignature = false;
            foreach (self::FILE_SIGNATURES as $type => $signatures) {
                if ($mimeType === $type) {
                    foreach ($signatures as $signature) {
                        $signatureUpper = strtoupper($signature);
                        // For PNG, check first 8 bytes (16 hex chars), for others check first 4 bytes (8 hex chars)
                        if ($type === 'image/png') {
                            // PNG signature: 89 50 4E 47 0D 0A 1A 0A (first 8 bytes = 16 hex chars)
                            // First 4 bytes (8 hex chars): 89504E47
                            // If we read 8 bytes, verify full PNG signature
                            if ($readBytes >= 8) {
                                $pngFullSignature = '89504E470D0A1A0A';
                                if (strlen($hexUpper) >= 16 && substr($hexUpper, 0, 16) === $pngFullSignature) {
                                    $validSignature = true;
                                    break 2;
                                }
                            }
                            // Fallback: if we only read 4 bytes, check first 4 bytes (8 hex chars)
                            if (strlen($hexUpper) >= 8 && substr($hexUpper, 0, 8) === $signatureUpper) {
                                $validSignature = true;
                                break 2;
                            }
                        } else {
                            // For JPEG and GIF, check first 4 bytes (8 hex chars)
                            if (strlen($hexUpper) >= strlen($signatureUpper) && substr($hexUpper, 0, strlen($signatureUpper)) === $signatureUpper) {
                                $validSignature = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if (!$validSignature) {
                // Log for debugging
                $this->logError("Signature validation failed. MIME: $mimeType, Hex: $hexUpper, File: $tmpPath");
                return ['valid' => false, 'error' => 'Signature file tidak valid. File mungkin rusak atau bukan gambar yang valid.', 'code' => 'INVALID_SIGNATURE'];
            }
        } else {
            // If we can't open file, log it but don't fail validation (fallback to getimagesize check)
            $this->logError("Cannot open file for signature validation: $tmpPath");
        }
        
        return ['valid' => true, 'mime_type' => $mimeType];
    }
    
    /**
     * Compress and save image
     */
    private function compressAndSave($sourcePath, $targetPath, $mimeType) {
        try {
            // Get image dimensions
            $imageInfo = @getimagesize($sourcePath);
            if ($imageInfo === false) {
                return ['success' => false, 'error' => 'Tidak dapat membaca informasi gambar'];
            }
            
            list($width, $height, $type) = $imageInfo;
            
            // Create image resource
            $sourceImage = null;
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = @imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = @imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = @imagecreatefromgif($sourcePath);
                    break;
                default:
                    return ['success' => false, 'error' => 'Format gambar tidak didukung'];
            }
            
            if (!$sourceImage) {
                return ['success' => false, 'error' => 'Gagal membuat resource gambar'];
            }
            
            // Calculate new dimensions maintaining aspect ratio
            $newWidth = $width;
            $newHeight = $height;
            
            if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
                $ratio = $width / $height;
                if ($width > $height) {
                    $newWidth = self::MAX_WIDTH;
                    $newHeight = intval($newWidth / $ratio);
                } else {
                    $newHeight = self::MAX_HEIGHT;
                    $newWidth = intval($newHeight * $ratio);
                }
            }
            
            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            // Resample image
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            // Save image
            $saved = false;
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $saved = imagejpeg($newImage, $targetPath, self::JPEG_QUALITY);
                    break;
                case 'image/png':
                    $saved = imagepng($newImage, $targetPath, self::PNG_COMPRESSION);
                    break;
                case 'image/gif':
                    $saved = imagegif($newImage, $targetPath);
                    break;
            }
            
            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            
            if (!$saved) {
                return ['success' => false, 'error' => 'Gagal menyimpan gambar yang dikompres'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate unique filename
     * Nama file mengikuti nama opsi/OPT (slug aman) + timestamp + hash acak.
     */
    private function generateUniqueFilename($extension, $optId = null, ?string $label = null) {
        $timestamp = time();
        $hash = bin2hex(random_bytes(8));

        // Slug dari nama opsi/OPT: huruf/angka/dash, maks 40 karakter
        $slug = '';
        if ($label !== null && trim($label) !== '') {
            $slug = strtolower(trim((string) $label));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim($slug, '-');
            $slug = substr($slug, 0, 40);
        }

        $prefix = $optId ? 'opt' . $optId . '_' : 'opt_';
        $prefix .= ($slug !== '' ? $slug . '_' : '');

        // Sanitize: remove special characters, keep only alphanumeric, dash, underscore
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix . $timestamp . '_' . $hash);

        return $sanitized . '.' . strtolower($extension);
    }
    
    /**
     * Get extension from MIME type
     */
    private function getExtensionFromMime($mimeType) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        return $mimeMap[$mimeType] ?? 'jpg';
    }
    
    /**
     * Generate thumbnail for an image
     * @param string $sourcePath Full path to source image
     * @param int $width Thumbnail width (default 200)
     * @param int $height Thumbnail height (default 200)
     * @return array Result with 'success', 'path', or 'error'
     */
    public function generateThumbnail($sourcePath, $width = null, $height = null) {
        $width = $width ?? self::THUMB_WIDTH;
        $height = $height ?? self::THUMB_HEIGHT;
        
        try {
            // Create thumbs directory
            $thumbDir = dirname($sourcePath) . '/thumbs/';
            if (!is_dir($thumbDir)) {
                if (!mkdir($thumbDir, 0755, true)) {
                    return ['success' => false, 'error' => 'Cannot create thumbnail directory'];
                }
            }
            
            $thumbPath = $thumbDir . basename($sourcePath);
            
            // Get image info
            $imageInfo = @getimagesize($sourcePath);
            if (!$imageInfo) {
                return ['success' => false, 'error' => 'Cannot read source image'];
            }
            
            list($origWidth, $origHeight, $type) = $imageInfo;
            $mimeType = $imageInfo['mime'];
            
            // Create source image resource
            $sourceImage = null;
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = @imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = @imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = @imagecreatefromgif($sourcePath);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $sourceImage = @imagecreatefromwebp($sourcePath);
                    }
                    break;
            }
            
            if (!$sourceImage) {
                return ['success' => false, 'error' => 'Cannot create image resource'];
            }
            
            // Calculate thumbnail dimensions (cover mode - crop to fill)
            $ratio = max($width / $origWidth, $height / $origHeight);
            $newWidth = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);
            $offsetX = (int)(($newWidth - $width) / 2);
            $offsetY = (int)(($newHeight - $height) / 2);
            
            // Create thumbnail
            $thumbImage = imagecreatetruecolor($width, $height);
            
            // Preserve transparency
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
                $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                imagefilledrectangle($thumbImage, 0, 0, $width, $height, $transparent);
            }
            
            // Resize and crop
            imagecopyresampled(
                $thumbImage, $sourceImage,
                -$offsetX, -$offsetY, 0, 0,
                $newWidth, $newHeight, $origWidth, $origHeight
            );
            
            // Save thumbnail
            $saved = false;
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $saved = imagejpeg($thumbImage, $thumbPath, 85);
                    break;
                case 'image/png':
                    $saved = imagepng($thumbImage, $thumbPath, 6);
                    break;
                case 'image/gif':
                    $saved = imagegif($thumbImage, $thumbPath);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        $saved = imagewebp($thumbImage, $thumbPath, 85);
                    }
                    break;
            }
            
            // Cleanup
            imagedestroy($sourceImage);
            imagedestroy($thumbImage);
            
            if (!$saved) {
                return ['success' => false, 'error' => 'Cannot save thumbnail'];
            }
            
            return [
                'success' => true,
                'path' => $thumbPath,
                'relative_path' => str_replace(ROOT_PATH . '/', '', $thumbPath)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create .htaccess to prevent script execution while keeping images servable.
     * Pola sama dengan direktori upload modul lain (feedback/irigasi/laporan/usulan-opt).
     * JANGAN memakai "Deny from all" — itu memblokir tampilan gambar (HTTP 403).
     */
    private function createHtaccess($dir) {
        $htaccessPath = $dir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $content = "Options -ExecCGI -Indexes\n";
            $content .= "AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .sh .cgi\n";
            $content .= "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)\$\">\n";
            $content .= "    Require all denied\n";
            $content .= "</FilesMatch>\n";
            @file_put_contents($htaccessPath, $content);
        }
    }
    
    /**
     * Get rate limit file path for specific user
     * @param int|null $userId User ID (null for anonymous)
     * @return string File path
     */
    private function getRateLimitFile($userId = null) {
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? 'anonymous';
        }
        return 'storage/rate_limit_upload_user_' . $userId . '.json';
    }
    
    /**
     * Check rate limit (per-user)
     */
    private function checkRateLimit($userId = null) {
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? 'anonymous';
        }
        
        $rateLimitDir = ROOT_PATH . '/storage/';
        if (!is_dir($rateLimitDir)) {
            @mkdir($rateLimitDir, 0755, true);
        }
        
        $rateLimitPath = ROOT_PATH . '/storage/' . $this->getRateLimitFile($userId);
        $maxUploads = 10; // Max 10 uploads
        $timeWindow = 60; // Per minute
        
        if (file_exists($rateLimitPath)) {
            $data = json_decode(file_get_contents($rateLimitPath), true);
            $currentTime = time();
            
            // Remove old entries outside time window
            $data = array_filter($data, function($timestamp) use ($currentTime, $timeWindow) {
                return ($currentTime - $timestamp) < $timeWindow;
            });
            
            // Check if limit exceeded
            if (count($data) >= $maxUploads) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Record rate limit (per-user)
     */
    private function recordRateLimit($userId = null) {
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? 'anonymous';
        }
        
        $rateLimitPath = ROOT_PATH . '/storage/' . $this->getRateLimitFile($userId);
        $data = [];
        
        if (file_exists($rateLimitPath)) {
            $data = json_decode(file_get_contents($rateLimitPath), true) ?: [];
        }
        
        $data[] = time();
        file_put_contents($rateLimitPath, json_encode($data));
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Log error
     */
    private function logError($message) {
        $logFile = ROOT_PATH . '/storage/logs/opt_upload_errors.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

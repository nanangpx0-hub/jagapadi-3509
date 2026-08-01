<?php
declare(strict_types=1);

class BaseApiController {
    
    /**
     * Send JSON response
     */
    protected function sendResponse(mixed $data, string $message = 'Success', int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $flags = defined('APP_ENV') && APP_ENV === 'development' ? JSON_PRETTY_PRINT : 0;
        $flags |= JSON_UNESCAPED_UNICODE;
        
        $response = [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, $flags);
        exit;
    }
    
    /**
     * Send error response
     */
    protected function sendError(string $message, int $statusCode = 400, array $errors = []): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $flags = defined('APP_ENV') && APP_ENV === 'development' ? JSON_PRETTY_PRINT : 0;
        $flags |= JSON_UNESCAPED_UNICODE;
        
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, $flags);
        exit;
    }

    /**
     * Backward-compatible alias for JSON success response
     */
    protected function jsonResponse(mixed $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $flags = defined('APP_ENV') && APP_ENV === 'development' ? JSON_PRETTY_PRINT : 0;
        $flags |= JSON_UNESCAPED_UNICODE;
        echo json_encode($data, $flags);
        exit;
    }

    /**
     * Backward-compatible alias for JSON error response
     */
    protected function errorResponse(string $message, int $statusCode = 500, array $errors = []): void {
        $this->sendError($message, $statusCode, $errors);
    }

    /**
     * Consistent response for endpoints intentionally not ready yet
     */
    protected function notImplemented(string $feature = 'Endpoint'): void {
        $this->sendError($feature . ' belum diimplementasikan', 501);
    }
    
    /**
     * Get request data
     */
    protected function getRequestData(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        
        return $_REQUEST;
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired(array $data, array $requiredFields): array {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $errors[] = "Field '{$field}' wajib diisi";
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data - only trim, do NOT htmlspecialchars (escape at output, not input)
     */
    protected function sanitizeData(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeData'], $data);
        }
        
        if (is_string($data)) {
            return trim($data);
        }
        
        return $data;
    }
    
    /**
     * Check user permissions
     */
    protected function checkPermission(?string $requiredRole = null): void {
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Unauthorized', 401);
        }
        
        if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
            if ($requiredRole === 'admin' || 
                ($requiredRole === 'operator' && !in_array($_SESSION['role'], ['admin', 'operator']))) {
                $this->sendError('Forbidden', 403);
            }
        }
    }
    
    /**
     * Get pagination parameters
     */
    protected function getPaginationParams(): array {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Format pagination response
     */
    protected function formatPaginatedResponse(array $data, int $total, int $page, int $limit): array {
        $totalPages = ceil($total / $limit);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Handle file upload with magic bytes validation (MIME spoofing protection)
     * 
     * @param array $file $_FILES['field_name'] array
     * @param string $subdirectory Subdirectory under public/uploads/
     * @param int $maxSizeBytes Maximum file size in bytes (default 5MB)
     * @return string Relative path to uploaded file
     * @throws Exception If validation fails or upload fails
     */
    protected function handleFileUpload(array $file, string $subdirectory = 'uploads', int $maxSizeBytes = 5 * 1024 * 1024): string {
        $uploadDir = ROOT_PATH . '/public/uploads/' . trim($subdirectory, '/') . '/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 1. Validate file size
        if ($file['size'] > $maxSizeBytes) {
            throw new Exception('Ukuran file maksimal ' . ($maxSizeBytes / (1024 * 1024)) . 'MB.');
        }
        
        // 2. Validate MIME type using finfo (magic bytes) - prevents MIME spoofing
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new Exception('Tipe file tidak diizinkan. Hanya JPG, PNG, WEBP yang diperbolehkan.');
        }
        
        // 3. Generate random filename (not from user input)
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => throw new Exception('Tipe MIME tidak dikenal: ' . $mimeType),
        };
        
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // 4. Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Gagal menyimpan file.');
        }
        
        // Return relative path from public directory
        return 'uploads/' . trim($subdirectory, '/') . '/' . $filename;
    }
}
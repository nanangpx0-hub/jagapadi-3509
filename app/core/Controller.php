<?php
class Controller {
    protected Container $container;

    public function __construct(?Container $container = null) {
        $this->container = $container ?? Container::getInstance();
    }

    protected function container(): Container {
        if (!isset($this->container)) {
            $this->container = Container::getInstance();
        }

        return $this->container;
    }

    protected function view($view, $data = []) {
        $data = is_array($data) ? $data : [];
        extract($data);
        require_once ROOT_PATH . '/app/views/' . $view . '.php';
    }
    
    protected function model($model) {
        require_once ROOT_PATH . '/app/models/' . $model . '.php';
        return $this->container()->make($model);
    }
    
    protected function redirect($url) {
        header('Location: ' . BASE_URL . $url);
        exit;
    }
    
    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('auth/login');
        }
        
        // Ensure CSRF token exists for authenticated sessions
        if (class_exists('Security')) {
            Security::generateCsrfToken();
        }
    }

    protected function validateCsrfToken() {
        // Only validate for state-changing requests
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = Security::getRequestCsrfToken();

            if (!Security::validateCsrfToken($token)) {
                Security::logSecurityEvent('CSRF_VIOLATION', 'Invalid CSRF token detected', $_SESSION['user_id'] ?? null);
                http_response_code(403);
                $this->json(['error' => 'CSRF token validation failed'], 403);
            }
        }
    }

    protected function requireRequestMethod($methods): void {
        $allowedMethods = array_map('strtoupper', (array)$methods);
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($requestMethod, $allowedMethods, true)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));

            if ($this->expectsJson()) {
                $this->json(['error' => 'Method not allowed'], 405);
            }

            echo '405 - Method Not Allowed';
            exit;
        }
    }

    protected function requireCsrfToken(): void {
        $token = Security::getRequestCsrfToken();

        if (!Security::validateCsrfToken($token)) {
            Security::logSecurityEvent('CSRF_VIOLATION', 'Invalid CSRF token detected', $_SESSION['user_id'] ?? null);

            if ($this->expectsJson()) {
                $this->json(['error' => 'CSRF token validation failed'], 403);
            }

            http_response_code(403);
            echo '403 - CSRF token validation failed';
            exit;
        }
    }

    protected function requireStateChangingRequest($methods = ['POST', 'PUT', 'DELETE', 'PATCH']): void {
        $this->requireRequestMethod($methods);
        $this->requireCsrfToken();

        // Double-submit guard: token sekali-pakai (opsional agar kompatibel
        // dengan form lama). Duplikat ditolak sebelum menyentuh model.
        if (!Security::consumeIdempotencyToken()) {
            $_SESSION['error'] = 'Permintaan ini sudah diproses sebelumnya. Data tidak dikirim dua kali.';
            $backTo = 'dashboard';
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if ($referer !== '' && stripos($referer, BASE_URL) === 0) {
                $path = substr($referer, strlen(BASE_URL));
                $path = strtok($path, '?');
                if (is_string($path) && $path !== '') { $backTo = rtrim($path, '/'); }
            }
            $this->redirect($backTo);
        }
    }

    protected function expectsJson(): bool {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return stripos($accept, 'application/json') !== false
            || strtolower($requestedWith) === 'xmlhttprequest';
    }
    
    protected function checkRole($roles = [], $customMessage = null) {
        $this->checkAuth();
        if (!in_array($_SESSION['role'], $roles)) {
            $message = $customMessage ?? 'Anda tidak memiliki akses ke halaman ini';
            $_SESSION['error'] = $message;
            $this->redirect('dashboard');
        }
    }
    
    protected function getCurrentUser() {
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'nama_lengkap' => $_SESSION['nama_lengkap']
            ];
        }
        return null;
    }

    protected function sanitizeRequestData(): array {
        $clean = [];
        foreach ($_POST as $key => $value) {
            $clean[$key] = is_string($value) ? trim($value) : $value;
        }
        return $clean;
    }

    /**
     * Sanitize CSV row against formula injection (CSV Injection / Formula Injection).
     *
     * Cells starting with =, +, -, @, TAB atau CR diberi awalan apostrof
     * agar tidak dieksekusi sebagai formula oleh Excel/LibreOffice.
     *
     * @param array $row Baris data mentah
     * @return array Baris data yang sudah disanitasi
     */
    protected function sanitizeCsvRow(array $row): array {
        return array_map(function ($val) {
            if (is_string($val) && strlen($val) > 0
                && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $val;
            }
            return $val;
        }, $row);
    }

    /**
     * Invalidate cached AJAX stats/chart responses setelah operasi tulis.
     *
     * @param array $prefixes Prefix cache key yang harus dihapus
     */
    protected function invalidateStatsCache(array $prefixes): void {
        if (!class_exists('CacheManager')) {
            return;
        }
        // Standardisasi: ringkasan dashboard selalu memakai key
        // dash_summary_{role}_{userId} — pastikan prefix ini ikut dibersihkan
        // dari SEMUA titik invalidasi tanpa kecuali.
        if (!in_array('dash_summary_', $prefixes, true)) {
            $prefixes[] = 'dash_summary_';
        }
        if (!in_array('dashboard:', $prefixes, true)) {
            $prefixes[] = 'dashboard:';
        }
        $cache = CacheManager::getInstance();
        if ($cache->isAvailable()) {
            foreach ($prefixes as $prefix) {
                $cache->clearPrefix($prefix);
            }
        }
    }

    /**
     * Ubah path foto tersimpan menjadi URL absolut yang dapat dirender.
     */
    protected function photoUrl(?string $path): string {
        if ($path === null || $path === '') {
            return '';
        }
        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            return $path;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (strpos($path, 'public/') !== 0) {
            $path = 'public/' . $path;
        }
        return BASE_URL . $path;
    }
}

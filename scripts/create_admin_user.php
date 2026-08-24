<?php
/**
 * Create New Admin User Script
 * 
 * Skrip untuk membuat user admin baru dengan validasi lengkap dan keamanan tinggi.
 * 
 * Fitur:
 * - Email validation menggunakan filter_var()
 * - Username uniqueness check
 * - bcrypt password hashing dengan cost 12
 * - Atomic transaction support
 * - Comprehensive logging
 * - Activity logging
 * - Error handling dengan rollback
 * 
 * @author Antigravity AI Assistant
 * @version 1.0.0
 * @date 2026-01-02
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define ROOT_PATH
define('ROOT_PATH', dirname(__DIR__));

// Load configuration
require_once __DIR__ . '/lib/db_bootstrap.php';

/**
 * Admin User Creator Class
 */
class AdminUserCreator {
    private $db;
    private $logFile;
    private $startTime;
    private $bcryptCost = 12; // Higher cost for admin accounts
    private $result = null;
    
    // User data to create — WAJIB dari environment, jangan hardcode kredensial.
    private $userData = [];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $timestamp = date('Y-m-d_H-i-s');
        $this->logFile = ROOT_PATH . '/logs/create_admin_user_' . $timestamp . '.log';
        $this->ensureLogDirectory();

        $this->userData = [
            'username' => jp_env_required('ADMIN_USERNAME'),
            'email' => jp_env_required('ADMIN_EMAIL'),
            'password' => jp_env_required('ADMIN_PASSWORD'),
            'role' => jp_env_optional('ADMIN_ROLE', 'admin'),
            'nama_lengkap' => jp_env_optional('ADMIN_NAME', jp_env_required('ADMIN_USERNAME')),
            'phone' => jp_env_optional('ADMIN_PHONE'),
            'aktif' => 1,
        ];
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = ROOT_PATH . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }
    
    /**
     * Log message to file and console
     */
    private function log($level, $context, $status, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] [$context] [$status] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        // Output to console with color coding
        $color = match($level) {
            'ERROR' => "\033[31m", // Red
            'WARNING' => "\033[33m", // Yellow
            'SUCCESS' => "\033[32m", // Green
            default => "\033[0m" // Default
        };
        echo $color . $logEntry . "\033[0m";
    }
    
    /**
     * Connect to database with retry mechanism
     */
    private function connectDatabase($maxRetries = 3) {
        $retries = 0;
        $lastError = null;
        
        while ($retries < $maxRetries) {
            try {
                $this->db = Database::getInstance()->getConnection();
                $this->log('INFO', 'SYSTEM', 'SUCCESS', 'Database connection established');
                return true;
            } catch (Exception $e) {
                $retries++;
                $lastError = $e->getMessage();
                $this->log('ERROR', 'SYSTEM', 'RETRY', "Connection attempt $retries failed: $lastError");
                
                if ($retries < $maxRetries) {
                    sleep(2); // Wait 2 seconds before retry
                }
            }
        }
        
        $this->log('ERROR', 'SYSTEM', 'FAILED', "Database connection failed after $maxRetries attempts: $lastError");
        return false;
    }
    
    /**
     * Validate email format
     */
    private function validateEmail($email) {
        if (empty($email)) {
            return ['valid' => false, 'message' => 'Email tidak boleh kosong'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Format email tidak valid'];
        }
        
        // Additional email format checks
        if (strlen($email) > 100) {
            return ['valid' => false, 'message' => 'Email terlalu panjang (maksimal 100 karakter)'];
        }
        
        return ['valid' => true, 'message' => 'Email valid'];
    }
    
    /**
     * Check if username already exists
     */
    private function checkUsernameExists($username) {
        $stmt = $this->db->prepare("SELECT id, username, email, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            $this->log('WARNING', 'VALIDATION', 'DUPLICATE', "Username '{$username}' already exists (ID: {$existingUser['id']}, Role: {$existingUser['role']})");
            return [
                'exists' => true,
                'user' => $existingUser
            ];
        }
        
        return ['exists' => false];
    }
    
    /**
     * Hash password using bcrypt with specified cost
     */
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->bcryptCost]);
    }
    
    /**
     * Validate role
     */
    private function validateRole($role) {
        $validRoles = ['admin', 'operator', 'viewer'];
        
        if (!in_array($role, $validRoles)) {
            return [
                'valid' => false,
                'message' => "Role tidak valid. Harus salah satu dari: " . implode(', ', $validRoles)
            ];
        }
        
        return ['valid' => true, 'message' => 'Role valid'];
    }
    
    /**
     * Create new admin user
     */
    private function createUser($userData, $hashedPassword) {
        $sql = "INSERT INTO users (username, password, role, nama_lengkap, email, phone, aktif, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $userData['username'],
            $hashedPassword,
            $userData['role'],
            $userData['nama_lengkap'],
            $userData['email'],
            $userData['phone'],
            $userData['aktif']
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'user_id' => $this->db->lastInsertId()
            ];
        }
        
        return ['success' => false, 'message' => 'Insert query failed'];
    }
    
    /**
     * Verify created user and password
     */
    private function verifyUser($userId, $plainPassword) {
        $stmt = $this->db->prepare("SELECT id, username, email, password, role, nama_lengkap, aktif FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['verified' => false, 'message' => 'User not found after creation'];
        }
        
        // Verify password hash
        if (!password_verify($plainPassword, $user['password'])) {
            return ['verified' => false, 'message' => 'Password verification failed'];
        }
        
        return [
            'verified' => true,
            'user' => $user,
            'message' => 'User verified successfully'
        ];
    }
    
    /**
     * Log activity for user creation
     */
    private function logActivity($userId, $username) {
        try {
            // Check if activity_log table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'activity_log'");
            if ($checkTable->rowCount() === 0) {
                $this->log('WARNING', $username, 'SKIPPED', 'activity_log table does not exist, skipping activity logging');
                return true;
            }
            
            $sql = "INSERT INTO activity_log (user_id, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $userId,
                'USER_CREATED',
                'User admin baru dibuat via script create_admin_user.php',
                $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
            
            if ($result) {
                $this->log('INFO', $username, 'LOGGED', 'Activity logged to database');
            }
            
            return $result;
        } catch (Exception $e) {
            $this->log('WARNING', $username, 'SKIPPED', 'Failed to log activity: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Main execution method
     */
    public function run() {
        $this->log('INFO', 'SYSTEM', 'START', '========================================');
        $this->log('INFO', 'SYSTEM', 'START', 'Creating new ADMIN user');
        $this->log('INFO', 'SYSTEM', 'INFO', "bcrypt cost: {$this->bcryptCost}");
        $this->log('INFO', 'SYSTEM', 'START', '========================================');
        
        // Connect to database
        if (!$this->connectDatabase()) {
            return $this->generateReport('failed', 'Database connection failed');
        }
        
        try {
            // Step 1: Validate email format
            $this->log('INFO', 'VALIDATION', 'CHECK', "Validating email: {$this->userData['email']}");
            $emailValidation = $this->validateEmail($this->userData['email']);
            
            if (!$emailValidation['valid']) {
                throw new Exception("Email validation failed: {$emailValidation['message']}");
            }
            
            $this->log('SUCCESS', 'VALIDATION', 'VALID', "Email format valid: {$this->userData['email']}");
            
            // Step 2: Validate role
            $this->log('INFO', 'VALIDATION', 'CHECK', "Validating role: {$this->userData['role']}");
            $roleValidation = $this->validateRole($this->userData['role']);
            
            if (!$roleValidation['valid']) {
                throw new Exception("Role validation failed: {$roleValidation['message']}");
            }
            
            $this->log('SUCCESS', 'VALIDATION', 'VALID', "Role valid: {$this->userData['role']}");
            
            // Step 3: Check username uniqueness
            $this->log('INFO', 'VALIDATION', 'CHECK', "Checking username uniqueness: {$this->userData['username']}");
            $usernameCheck = $this->checkUsernameExists($this->userData['username']);
            
            if ($usernameCheck['exists']) {
                $existingUser = $usernameCheck['user'];
                return $this->generateReport(
                    'duplicate', 
                    "Username already exists",
                    [
                        'existing_user' => [
                            'id' => $existingUser['id'],
                            'username' => $existingUser['username'],
                            'email' => $existingUser['email'],
                            'role' => $existingUser['role']
                        ]
                    ]
                );
            }
            
            $this->log('SUCCESS', 'VALIDATION', 'UNIQUE', "Username is unique: {$this->userData['username']}");
            
            // Step 4: Hash password
            $this->log('INFO', 'SECURITY', 'HASH', 'Generating bcrypt password hash...');
            $hashedPassword = $this->hashPassword($this->userData['password']);
            $this->log('SUCCESS', 'SECURITY', 'HASH', "Password hash generated with bcrypt cost {$this->bcryptCost}");
            
            // Step 5: Begin transaction
            $this->db->beginTransaction();
            $this->log('INFO', 'SYSTEM', 'TRANSACTION', 'Transaction started');
            
            // Step 6: Create user
            $this->log('INFO', 'DATABASE', 'INSERT', "Creating user: {$this->userData['username']}");
            $createResult = $this->createUser($this->userData, $hashedPassword);
            
            if (!$createResult['success']) {
                throw new Exception($createResult['message'] ?? 'Failed to create user');
            }
            
            $userId = $createResult['user_id'];
            $this->log('SUCCESS', 'DATABASE', 'CREATED', "User created successfully with ID: $userId");
            
            // Step 7: Verify user creation and password
            $this->log('INFO', 'VERIFICATION', 'CHECK', 'Verifying user creation and password hash...');
            $verifyResult = $this->verifyUser($userId, $this->userData['password']);
            
            if (!$verifyResult['verified']) {
                throw new Exception($verifyResult['message'] ?? 'User verification failed');
            }
            
            $this->log('SUCCESS', 'VERIFICATION', 'VERIFIED', 'User and password verified successfully');
            
            // Step 8: Log activity
            $this->logActivity($userId, $this->userData['username']);
            
            // Step 9: Commit transaction
            $this->db->commit();
            $this->log('INFO', 'SYSTEM', 'TRANSACTION', 'Transaction committed successfully');
            
            // Store result
            $this->result = [
                'user_id' => $userId,
                'username' => $this->userData['username'],
                'email' => $this->userData['email'],
                'role' => $this->userData['role'],
                'nama_lengkap' => $this->userData['nama_lengkap'],
                'aktif' => $this->userData['aktif'],
                'password_hash' => substr($hashedPassword, 0, 20) . '...' // Show only first 20 chars for security
            ];
            
            return $this->generateReport('success', 'Admin user created successfully');
            
        } catch (Exception $e) {
            // Rollback transaction
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                $this->log('ERROR', 'SYSTEM', 'ROLLBACK', 'Transaction rolled back: ' . $e->getMessage());
            }
            
            return $this->generateReport('failed', 'User creation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate execution report
     */
    private function generateReport($overallStatus, $message, $additionalData = []) {
        $executionTime = microtime(true) - $this->startTime;
        
        $report = [
            'overall_status' => $overallStatus,
            'message' => $message,
            'execution_time' => round($executionTime, 3) . ' seconds',
            'bcrypt_cost' => $this->bcryptCost,
            'log_file' => $this->logFile,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($this->result) {
            $report['user_data'] = $this->result;
        }
        
        // Merge additional data
        $report = array_merge($report, $additionalData);
        
        $this->log('INFO', 'SYSTEM', 'COMPLETE', '========================================');
        $this->log('INFO', 'SYSTEM', 'COMPLETE', "Status: $overallStatus");
        $this->log('INFO', 'SYSTEM', 'COMPLETE', "Message: $message");
        $this->log('INFO', 'SYSTEM', 'COMPLETE', 'Log file: ' . $this->logFile);
        $this->log('INFO', 'SYSTEM', 'COMPLETE', '========================================');
        
        return $report;
    }
}

// ============================================
// Main Execution
// ============================================

try {
    echo "\n";
    echo "===========================================\n";
    echo "  JAGAPADI - Create Admin User\n";
    echo "===========================================\n\n";
    
    // Display user information
    echo "📋 User Information:\n";
    echo "   Username: {$this->userData['username']}\n";
    echo "   Email: {$this->userData['email']}\n";
    echo "   Role: {$this->userData['role']}\n";
    echo "   Nama Lengkap: {$this->userData['nama_lengkap']}\n";
    echo "   Phone: " . ($this->userData['phone'] !== '' ? $this->userData['phone'] : '-') . "\n";
    echo "   Password: ******** (akan di-hash dengan bcrypt)\n\n";
    
    // Confirmation prompt
    echo "⚠️  PERINGATAN: Script ini akan membuat user baru dengan role 'admin'\n";
    echo "   Hash: bcrypt dengan cost 12 (keamanan tinggi)\n\n";
    
    // Check if running in non-interactive mode
    $isInteractive = php_sapi_name() === 'cli' && (function_exists('stream_isatty') ? stream_isatty(STDIN) : true);
    
    if ($isInteractive) {
        echo "Ketik 'yes' untuk melanjutkan atau tekan Enter untuk membatalkan: ";
        $confirmation = trim(fgets(STDIN));
        
        if (strtolower($confirmation) !== 'yes') {
            echo "\n❌ Operasi dibatalkan oleh user.\n\n";
            exit(0);
        }
    } else {
        echo "ℹ️  Running in non-interactive mode. Proceeding automatically...\n\n";
    }
    
    echo "\n";
    
    $creator = new AdminUserCreator();
    $report = $creator->run();
    
    // Output JSON report
    echo "\n===========================================\n";
    echo "  Execution Report\n";
    echo "===========================================\n\n";
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
    
    // Save report to file
    $reportFile = ROOT_PATH . '/logs/create_admin_user_report_' . date('Y-m-d_His') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "📄 Report saved to: $reportFile\n\n";
    
    // Final summary
    if ($report['overall_status'] === 'success') {
        echo "✅ Admin user created successfully!\n";
        echo "   User ID: {$report['user_data']['user_id']}\n";
        echo "   Username: {$report['user_data']['username']}\n";
        echo "   Email: {$report['user_data']['email']}\n";
        echo "   Role: {$report['user_data']['role']}\n\n";
        echo "ℹ️  Anda dapat login dengan:\n";
        echo "   Username: {$report['user_data']['username']}\n";
        echo "   Password: (password yang Anda tentukan)\n\n";
    } else if ($report['overall_status'] === 'duplicate') {
        echo "⚠️  User already exists!\n";
        echo "   Existing User ID: {$report['existing_user']['id']}\n";
        echo "   Username: {$report['existing_user']['username']}\n";
        echo "   Email: {$report['existing_user']['email']}\n";
        echo "   Role: {$report['existing_user']['role']}\n\n";
        echo "ℹ️  User tidak dibuat karena username sudah ada dalam database.\n\n";
    } else {
        echo "❌ Admin user creation failed!\n";
        echo "   Error: {$report['message']}\n\n";
    }
    
    // Exit with appropriate code
    exit($report['overall_status'] === 'success' ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

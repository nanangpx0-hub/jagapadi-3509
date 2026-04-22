<?php
/**
 * Create Operator and Viewer Users Script
 * 
 * Skrip untuk membuat user operator dan viewer dengan validasi lengkap dan keamanan tinggi.
 * 
 * Fitur:
 * - Multi-user creation dalam satu transaksi
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
require_once ROOT_PATH . '/config/database.php';

/**
 * Multi-User Creator Class
 */
class MultiUserCreator {
    private $db;
    private $logFile;
    private $startTime;
    private $bcryptCost = 12;
    private $results = [];
    
    // Users data to create
    private $usersData = [
        [
            'username' => 'operator@gmail.com',
            'email' => 'operator@gmail.com',
            'password' => 'operator3509!',
            'role' => 'operator',
            'nama_lengkap' => 'Operator JAGAPADI',
            'phone' => null,
            'aktif' => 1
        ],
        [
            'username' => 'viewer@gmail.com',
            'email' => 'viewer@gmail.com',
            'password' => 'viewer3509!',
            'role' => 'viewer',
            'nama_lengkap' => 'Viewer JAGAPADI',
            'phone' => null,
            'aktif' => 1
        ]
    ];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $timestamp = date('Y-m-d_H-i-s');
        $this->logFile = ROOT_PATH . '/logs/create_multi_users_' . $timestamp . '.log';
        $this->ensureLogDirectory();
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
                    sleep(2);
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
            return [
                'exists' => true,
                'user' => $existingUser
            ];
        }
        
        return ['exists' => false];
    }
    
    /**
     * Hash password using bcrypt
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
     * Create new user
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
            $checkTable = $this->db->query("SHOW TABLES LIKE 'activity_log'");
            if ($checkTable->rowCount() === 0) {
                $this->log('WARNING', $username, 'SKIPPED', 'activity_log table does not exist');
                return true;
            }
            
            $sql = "INSERT INTO activity_log (user_id, action, description, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $userId,
                'USER_CREATED',
                'User dibuat via script create_multi_users.php',
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
     * Process single user creation
     */
    private function processUser($userData) {
        $username = $userData['username'];
        
        try {
            // Validate email
            $this->log('INFO', $username, 'VALIDATE', "Validating email: {$userData['email']}");
            $emailValidation = $this->validateEmail($userData['email']);
            
            if (!$emailValidation['valid']) {
                throw new Exception("Email validation failed: {$emailValidation['message']}");
            }
            
            $this->log('SUCCESS', $username, 'VALID', "Email format valid");
            
            // Validate role
            $this->log('INFO', $username, 'VALIDATE', "Validating role: {$userData['role']}");
            $roleValidation = $this->validateRole($userData['role']);
            
            if (!$roleValidation['valid']) {
                throw new Exception("Role validation failed: {$roleValidation['message']}");
            }
            
            $this->log('SUCCESS', $username, 'VALID', "Role valid: {$userData['role']}");
            
            // Check username uniqueness
            $this->log('INFO', $username, 'CHECK', "Checking username uniqueness");
            $usernameCheck = $this->checkUsernameExists($userData['username']);
            
            if ($usernameCheck['exists']) {
                $existingUser = $usernameCheck['user'];
                $this->log('WARNING', $username, 'DUPLICATE', "Username already exists (ID: {$existingUser['id']})");
                
                return [
                    'status' => 'duplicate',
                    'username' => $username,
                    'message' => 'Username already exists',
                    'existing_user' => $existingUser
                ];
            }
            
            $this->log('SUCCESS', $username, 'UNIQUE', "Username is unique");
            
            // Hash password
            $this->log('INFO', $username, 'HASH', 'Generating bcrypt password hash...');
            $hashedPassword = $this->hashPassword($userData['password']);
            $this->log('SUCCESS', $username, 'HASH', "Password hash generated");
            
            // Create user
            $this->log('INFO', $username, 'INSERT', "Creating user...");
            $createResult = $this->createUser($userData, $hashedPassword);
            
            if (!$createResult['success']) {
                throw new Exception($createResult['message'] ?? 'Failed to create user');
            }
            
            $userId = $createResult['user_id'];
            $this->log('SUCCESS', $username, 'CREATED', "User created with ID: $userId");
            
            // Verify user
            $this->log('INFO', $username, 'VERIFY', 'Verifying user creation...');
            $verifyResult = $this->verifyUser($userId, $userData['password']);
            
            if (!$verifyResult['verified']) {
                throw new Exception($verifyResult['message'] ?? 'User verification failed');
            }
            
            $this->log('SUCCESS', $username, 'VERIFIED', 'User and password verified');
            
            // Log activity
            $this->logActivity($userId, $username);
            
            return [
                'status' => 'success',
                'user_id' => $userId,
                'username' => $username,
                'email' => $userData['email'],
                'role' => $userData['role'],
                'nama_lengkap' => $userData['nama_lengkap']
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', $username, 'FAILED', $e->getMessage());
            
            return [
                'status' => 'failed',
                'username' => $username,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Main execution method
     */
    public function run() {
        $this->log('INFO', 'SYSTEM', 'START', '========================================');
        $this->log('INFO', 'SYSTEM', 'START', 'Creating multiple users');
        $this->log('INFO', 'SYSTEM', 'INFO', "bcrypt cost: {$this->bcryptCost}");
        $this->log('INFO', 'SYSTEM', 'INFO', "Total users to create: " . count($this->usersData));
        $this->log('INFO', 'SYSTEM', 'START', '========================================');
        
        // Connect to database
        if (!$this->connectDatabase()) {
            return $this->generateReport('failed', 'Database connection failed');
        }
        
        try {
            // Begin transaction
            $this->db->beginTransaction();
            $this->log('INFO', 'SYSTEM', 'TRANSACTION', 'Transaction started');
            
            // Process each user
            foreach ($this->usersData as $userData) {
                $result = $this->processUser($userData);
                $this->results[] = $result;
                
                // If any user fails (not duplicate), rollback
                if ($result['status'] === 'failed') {
                    throw new Exception("Failed to create user: {$result['username']} - {$result['message']}");
                }
            }
            
            // Commit transaction
            $this->db->commit();
            $this->log('INFO', 'SYSTEM', 'TRANSACTION', 'Transaction committed successfully');
            
            return $this->generateReport('success', 'Users created successfully');
            
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
    private function generateReport($overallStatus, $message) {
        $executionTime = microtime(true) - $this->startTime;
        
        $successCount = count(array_filter($this->results, fn($r) => $r['status'] === 'success'));
        $failedCount = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        $duplicateCount = count(array_filter($this->results, fn($r) => $r['status'] === 'duplicate'));
        
        $report = [
            'overall_status' => $overallStatus,
            'message' => $message,
            'execution_time' => round($executionTime, 3) . ' seconds',
            'bcrypt_cost' => $this->bcryptCost,
            'log_file' => $this->logFile,
            'summary' => [
                'total_users' => count($this->usersData),
                'success' => $successCount,
                'failed' => $failedCount,
                'duplicate' => $duplicateCount
            ],
            'details' => $this->results,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->log('INFO', 'SYSTEM', 'COMPLETE', '========================================');
        $this->log('INFO', 'SYSTEM', 'COMPLETE', "Status: $overallStatus");
        $this->log('INFO', 'SYSTEM', 'COMPLETE', "Success: $successCount, Failed: $failedCount, Duplicate: $duplicateCount");
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
    echo "  JAGAPADI - Create Multiple Users\n";
    echo "===========================================\n\n";
    
    // Display users to create
    echo "📋 Users to create:\n\n";
    echo "1. Operator Account:\n";
    echo "   Username: operator@gmail.com\n";
    echo "   Email: operator@gmail.com\n";
    echo "   Role: operator\n";
    echo "   Password: ******** (akan di-hash dengan bcrypt)\n\n";
    
    echo "2. Viewer Account:\n";
    echo "   Username: viewer@gmail.com\n";
    echo "   Email: viewer@gmail.com\n";
    echo "   Role: viewer\n";
    echo "   Password: ******** (akan di-hash dengan bcrypt)\n\n";
    
    // Confirmation prompt
    echo "⚠️  PERINGATAN: Script ini akan membuat 2 user baru\n";
    echo "   Hash: bcrypt dengan cost 12 (keamanan tinggi)\n";
    echo "   Transaction: Atomic (all-or-nothing)\n\n";
    
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
    
    $creator = new MultiUserCreator();
    $report = $creator->run();
    
    // Output JSON report
    echo "\n===========================================\n";
    echo "  Execution Report\n";
    echo "===========================================\n\n";
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
    
    // Save report to file
    $reportFile = ROOT_PATH . '/logs/create_multi_users_report_' . date('Y-m-d_His') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "📄 Report saved to: $reportFile\n\n";
    
    // Final summary
    if ($report['overall_status'] === 'success') {
        echo "✅ Users created successfully!\n";
        echo "   Total users created: {$report['summary']['success']}\n\n";
        
        foreach ($report['details'] as $detail) {
            if ($detail['status'] === 'success') {
                echo "   ✓ {$detail['username']} ({$detail['role']}) - ID: {$detail['user_id']}\n";
            } elseif ($detail['status'] === 'duplicate') {
                echo "   ⚠ {$detail['username']} - Already exists\n";
            }
        }
        echo "\n";
    } else {
        echo "❌ User creation failed!\n";
        echo "   Error: {$report['message']}\n\n";
    }
    
    // Exit with appropriate code
    exit($report['overall_status'] === 'success' ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

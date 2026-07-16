<?php
/**
 * Update Password Script for 'petugas' Role Users
 * 
 * Skrip one-time untuk memperbarui password semua user dengan role 'petugas'
 * menjadi 'petugas123' dengan bcrypt hashing.
 * 
 * Fitur:
 * - Backup data sebelum perubahan
 * - Atomic transaction
 * - bcrypt hashing dengan salt round 10
 * - Comprehensive logging
 * - Verification setelah update
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
 * Password Update Class for Petugas Users
 */
class PetugasPasswordUpdater {
    private $db;
    private $logFile;
    private $backupFile;
    private $startTime;
    private $newPassword = 'petugas123';
    private $bcryptCost = 10; // Salt round
    private $results = [];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $timestamp = date('Y-m-d_H-i-s');
        $this->logFile = ROOT_PATH . '/logs/update_petugas_password_' . $timestamp . '.log';
        $this->backupFile = ROOT_PATH . '/logs/backup_petugas_passwords_' . $timestamp . '.json';
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
    private function log($level, $username, $status, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] [$username] [$status] $message" . PHP_EOL;
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
     * Get all users with role 'petugas'
     */
    private function getPetugasUsers() {
        $stmt = $this->db->prepare("SELECT id, username, nama_lengkap, email, password, role FROM users WHERE role = ?");
        $stmt->execute(['petugas']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create backup of user data before update
     */
    private function createBackup($users) {
        if (empty($users)) {
            $this->log('WARNING', 'SYSTEM', 'SKIPPED', 'No users to backup');
            return true;
        }
        
        $backupData = [
            'created_at' => date('Y-m-d H:i:s'),
            'description' => 'Backup data password petugas sebelum update',
            'total_users' => count($users),
            'users' => array_map(function($user) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'email' => $user['email'],
                    'password_hash_old' => $user['password'],
                    'role' => $user['role']
                ];
            }, $users)
        ];
        
        $result = file_put_contents(
            $this->backupFile, 
            json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        if ($result === false) {
            $this->log('ERROR', 'SYSTEM', 'FAILED', 'Failed to create backup file');
            return false;
        }
        
        $this->log('INFO', 'SYSTEM', 'SUCCESS', "Backup created: {$this->backupFile}");
        return true;
    }
    
    /**
     * Hash password using bcrypt with salt round 10
     */
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->bcryptCost]);
    }
    
    /**
     * Update password for a single user
     */
    private function updateUserPassword($userId, $hashedPassword) {
        $sql = "UPDATE users SET 
                password = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$hashedPassword, $userId]);
    }
    
    /**
     * Verify password after update
     */
    private function verifyPassword($userId, $plainPassword) {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($plainPassword, $user['password']);
    }
    
    /**
     * Main execution method
     */
    public function run() {
        $this->log('INFO', 'SYSTEM', 'START', '========================================');
        $this->log('INFO', 'SYSTEM', 'START', 'Starting password update for petugas users');
        $this->log('INFO', 'SYSTEM', 'INFO', "New password: {$this->newPassword}");
        $this->log('INFO', 'SYSTEM', 'INFO', "bcrypt cost: {$this->bcryptCost}");
        $this->log('INFO', 'SYSTEM', 'START', '========================================');
        
        // Connect to database
        if (!$this->connectDatabase()) {
            return $this->generateReport('failed', 'Database connection failed');
        }
        
        try {
            // Step 1: Get all petugas users
            $petugasUsers = $this->getPetugasUsers();
            $this->log('INFO', 'SYSTEM', 'FOUND', "Found " . count($petugasUsers) . " users with role 'petugas'");
            
            if (empty($petugasUsers)) {
                $this->log('WARNING', 'SYSTEM', 'SKIPPED', 'No users with role petugas found');
                return $this->generateReport('success', 'No users to update');
            }
            
            // Step 2: Create backup
            $this->log('INFO', 'SYSTEM', 'BACKUP', 'Creating backup of current passwords...');
            if (!$this->createBackup($petugasUsers)) {
                return $this->generateReport('failed', 'Failed to create backup');
            }
            
            // Step 3: Generate new hashed password
            $hashedPassword = $this->hashPassword($this->newPassword);
            $this->log('INFO', 'SYSTEM', 'HASH', 'New password hash generated');
            
            // Step 4: Begin transaction
            $this->db->beginTransaction();
            $this->log('INFO', 'SYSTEM', 'TRANSACTION', 'Transaction started');
            
            $successCount = 0;
            $failedCount = 0;
            
            // Step 5: Update each user's password
            foreach ($petugasUsers as $user) {
                $username = $user['username'];
                
                try {
                    if ($this->updateUserPassword($user['id'], $hashedPassword)) {
                        $successCount++;
                        $this->log('SUCCESS', $username, 'UPDATED', "Password updated successfully");
                        $this->results[] = [
                            'id' => $user['id'],
                            'username' => $username,
                            'nama_lengkap' => $user['nama_lengkap'],
                            'status' => 'success',
                            'message' => 'Password updated'
                        ];
                    } else {
                        throw new Exception('Update query returned false');
                    }
                } catch (Exception $e) {
                    $failedCount++;
                    $errorMsg = $e->getMessage();
                    $this->log('ERROR', $username, 'FAILED', "Failed to update password: $errorMsg");
                    $this->results[] = [
                        'id' => $user['id'],
                        'username' => $username,
                        'nama_lengkap' => $user['nama_lengkap'],
                        'status' => 'failed',
                        'message' => $errorMsg
                    ];
                    
                    // Rollback on any error
                    throw $e;
                }
            }
            
            // Step 6: Verify all passwords
            $this->log('INFO', 'SYSTEM', 'VERIFY', 'Verifying updated passwords...');
            $verifyFailed = false;
            
            foreach ($petugasUsers as $user) {
                if (!$this->verifyPassword($user['id'], $this->newPassword)) {
                    $this->log('ERROR', $user['username'], 'VERIFY_FAILED', 'Password verification failed');
                    $verifyFailed = true;
                } else {
                    $this->log('SUCCESS', $user['username'], 'VERIFIED', 'Password verified successfully');
                }
            }
            
            if ($verifyFailed) {
                throw new Exception('Password verification failed for one or more users');
            }
            
            // Step 7: Commit transaction
            $this->db->commit();
            $this->log('INFO', 'SYSTEM', 'TRANSACTION', 'Transaction committed successfully');
            
            return $this->generateReport('success', "Successfully updated $successCount passwords");
            
        } catch (Exception $e) {
            // Rollback transaction
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                $this->log('ERROR', 'SYSTEM', 'ROLLBACK', 'Transaction rolled back: ' . $e->getMessage());
            }
            
            return $this->generateReport('failed', 'Transaction failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate execution report
     */
    private function generateReport($overallStatus, $message) {
        $executionTime = microtime(true) - $this->startTime;
        
        $successCount = count(array_filter($this->results, fn($r) => $r['status'] === 'success'));
        $failedCount = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        
        $report = [
            'overall_status' => $overallStatus,
            'message' => $message,
            'execution_time' => round($executionTime, 3) . ' seconds',
            'new_password' => $this->newPassword,
            'bcrypt_cost' => $this->bcryptCost,
            'backup_file' => $this->backupFile,
            'log_file' => $this->logFile,
            'summary' => [
                'total_users' => count($this->results),
                'success' => $successCount,
                'failed' => $failedCount
            ],
            'details' => $this->results,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->log('INFO', 'SYSTEM', 'COMPLETE', '========================================');
        $this->log('INFO', 'SYSTEM', 'COMPLETE', "Update completed: $successCount success, $failedCount failed");
        $this->log('INFO', 'SYSTEM', 'COMPLETE', 'Backup file: ' . $this->backupFile);
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
    echo "  JAGAPADI - Update Password Petugas\n";
    echo "===========================================\n\n";
    
    // Confirmation prompt
    echo "⚠️  PERINGATAN: Script ini akan mengubah password SEMUA user dengan role 'petugas'\n";
    echo "   Password baru: petugas123\n";
    echo "   bcrypt cost: 10\n\n";
    
    // Check if running in non-interactive mode (e.g., from cron)
    // Use stream_isatty for cross-platform compatibility (PHP 7.2+)
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
    
    $updater = new PetugasPasswordUpdater();
    $report = $updater->run();
    
    // Output JSON report
    echo "\n===========================================\n";
    echo "  Execution Report\n";
    echo "===========================================\n\n";
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
    
    // Save report to file
    $reportFile = ROOT_PATH . '/logs/update_petugas_password_report_' . date('Y-m-d_His') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "📄 Report saved to: $reportFile\n\n";
    
    // Final summary
    if ($report['overall_status'] === 'success') {
        echo "✅ Password update completed successfully!\n";
        echo "   Users updated: {$report['summary']['success']}\n";
        echo "   New password: {$report['new_password']}\n\n";
    } else {
        echo "❌ Password update failed!\n";
        echo "   Error: {$report['message']}\n\n";
    }
    
    // Exit with appropriate code
    exit($report['overall_status'] === 'success' ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

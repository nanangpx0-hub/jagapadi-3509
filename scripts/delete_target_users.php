<?php
/**
 * Delete Specific Users Script
 * 
 * Menghapus akun: admin_jagapadi, operator1, viewer1, petugas
 * Beserta semua data terkait (activity logs, laporan, dll).
 * 
 * @author Antigravity AI Assistant
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/database.php';

class UserDeleter {
    private $db;
    private $logFile;
    private $targets = ['admin_jagapadi', 'operator1', 'viewer1', 'petugas'];
    
    public function __construct() {
        $this->logFile = ROOT_PATH . '/logs/delete_users_' . date('Y-m-d_H-i-s') . '.log';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        if (!is_dir(dirname($this->logFile))) mkdir(dirname($this->logFile), 0777, true);
    }
    
    private function log($msg) {
        $entry = "[" . date('Y-m-d H:i:s') . "] $msg\n";
        echo $entry;
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
    
    public function run() {
        $this->log("STARTING USER DELETION PROCESS");
        
        try {
            $this->db = Database::getInstance()->getConnection();
            
            // 1. Identify Target IDs
            $placeholders = str_repeat('?,', count($this->targets) - 1) . '?';
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
            $stmt->execute($this->targets);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($users)) {
                $this->log("No target users found. Nothing to delete.");
                return;
            }
            
            $ids = array_column($users, 'id');
            $usernames = array_column($users, 'username');
            $this->log("Found users to delete: " . implode(', ', $usernames) . " (IDs: " . implode(', ', $ids) . ")");
            
            // 2. Begin Transaction
            $this->db->beginTransaction();
            
            $idList = implode(',', $ids); // Safe since IDs are integers from DB
            
            // 3. Delete Related Data
            // Table: password_resets
            $count = $this->db->exec("DELETE FROM password_resets WHERE user_id IN ($idList)");
            $this->log("Deleted $count rows from password_resets");
            
            // Table: activity_log
            $count = $this->db->exec("DELETE FROM activity_log WHERE user_id IN ($idList)");
            $this->log("Deleted $count rows from activity_log");
            
            // Table: audit_log_wilayah
            $count = $this->db->exec("DELETE FROM audit_log_wilayah WHERE user_id IN ($idList)");
            $this->log("Deleted $count rows from audit_log_wilayah");
            
            // Table: laporan_hama (as verified_by)
            // Note: If we strictly want to delete data related to account, we delete reports verified by them? 
            // Or maybe just set verified_by to NULL? 
            // Request said "Menghapus semua data terkait akun tersebut". 
            // Reports verified by them might still be valid reports from others.
            // But reports CREATED by them (user_id) should be deleted.
            // Let's set verified_by to NULL for others' reports, and delete reports owned by them.
            
            // Set verified_by to NULL where verifier is being deleted
            $count = $this->db->exec("UPDATE laporan_hama SET verified_by = NULL WHERE verified_by IN ($idList)");
            $this->log("Updated $count rows in laporan_hama (set verified_by = NULL)");

            // Delete reports owned by these users
            $count = $this->db->exec("DELETE FROM laporan_hama WHERE user_id IN ($idList)");
            $this->log("Deleted $count rows from laporan_hama (owned by users)");

            // 4. Delete Users
            $count = $this->db->exec("DELETE FROM users WHERE id IN ($idList)");
            $this->log("Deleted $count rows from users table");
            
            // 5. Commit
            $this->db->commit();
            $this->log("TRANSACTION COMMITTED SUCCESSFULLY");
            
            // 6. Verify
            $this->verifyDeletion($usernames);
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->log("ERROR: " . $e->getMessage());
            exit(1);
        }
    }
    
    private function verifyDeletion($usernames) {
        $this->log("Verifying deletion...");
        $placeholders = str_repeat('?,', count($usernames) - 1) . '?';
        $stmt = $this->db->prepare("SELECT username FROM users WHERE username IN ($placeholders)");
        $stmt->execute($usernames);
        $remaining = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($remaining)) {
            $this->log("✅ SUCCESS: All target users have been deleted.");
        } else {
            $this->log("❌ FAILED: Some users still exist: " . implode(', ', $remaining));
        }
    }
}

$deleter = new UserDeleter();
$deleter->run();

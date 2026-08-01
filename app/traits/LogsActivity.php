<?php
/**
 * LogsActivity Trait
 * Provides consistent activity logging across all controllers
 * 
 * Usage:
 * class MyController extends Controller {
 *     use LogsActivity;
 *     
 *     public function store() {
 *         // ... your code
 *         $this->logActivity('Create', 'table_name', $recordId, 'Description');
 *     }
 * }
 */

trait LogsActivity {
    /**
     * Log user activity
     * 
     * @param string $action Action performed (e.g., 'Create', 'Update', 'Delete')
     * @param string $tableName Table name affected
     * @param int|null $recordId Record ID affected
     * @param string $description Description of the action
     * @return bool Success status
     */
    protected function logActivity($action, $tableName, $recordId = null, $description = '') {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $tableName,
                $recordId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Don't fail the main operation if logging fails
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param string $description Event description
     * @return bool Success status
     */
    protected function logSecurityEvent($event, $description) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at)
                VALUES (?, 'SECURITY_EVENT', 'security', NULL, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $event . ': ' . $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
            return false;
        }
    }
}

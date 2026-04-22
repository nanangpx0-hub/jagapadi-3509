<?php
/**
 * Generate User Documentation Script
 * Membuat dokumentasi user otomatis dari database aktif
 * 
 * @author Kiro AI Assistant
 * @version 1.0.0
 * @date 2025-01-01
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define ROOT_PATH
define('ROOT_PATH', dirname(__DIR__));

// Load configuration and database class
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/core/Database.php';

/**
 * User Documentation Generator Class
 */
class UserDocumentationGenerator {
    private $db;
    private $users = [];
    private $outputFile;
    private $startTime;
    
    // Common default passwords to check
    private $commonPasswords = [
        '123456',
        'password',
        'admin123',
        'admin',
        'petugas3509',
        'op1test',
        'vw1test'
    ];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->outputFile = ROOT_PATH . '/README-USERS-JAGAPADI.md';
    }
    
    /**
     * Connect to database
     */
    private function connectDatabase() {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->log('SUCCESS', 'Database connection established');
            return true;
        } catch (Exception $e) {
            $this->log('ERROR', 'Database connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fetch all users from database
     */
    private function fetchUsers() {
        try {
            $sql = "SELECT id, username, password, role, nama_lengkap, email, phone, aktif, created_at, updated_at 
                    FROM users 
                    ORDER BY role, username";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $this->users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->log('SUCCESS', 'Fetched ' . count($this->users) . ' users from database');
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to fetch users: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Analyze password hash to determine if it's a common default
     */
    private function analyzePassword($hash) {
        // Check common default passwords
        foreach ($this->commonPasswords as $commonPassword) {
            if (password_verify($commonPassword, $hash)) {
                return $commonPassword . ' (Default)';
            }
        }
        
        // If no common password matches, show it's encrypted
        $hashPreview = substr($hash, 0, 10) . '...';
        return "Terenkripsi/Telah Diubah ($hashPreview)";
    }
    
    /**
     * Group users by role
     */
    private function groupUsersByRole() {
        $groupedUsers = [];
        
        foreach ($this->users as $user) {
            $role = ucfirst($user['role']);
            if (!isset($groupedUsers[$role])) {
                $groupedUsers[$role] = [];
            }
            $groupedUsers[$role][] = $user;
        }
        
        return $groupedUsers;
    }
    
    /**
     * Generate role description
     */
    private function getRoleDescription($role) {
        $descriptions = [
            'Admin' => 'Administrator dengan akses penuh ke semua fitur sistem',
            'Operator' => 'Operator yang dapat memverifikasi dan mengelola laporan',
            'Viewer' => 'Pengguna dengan akses read-only untuk melihat data',
            'Petugas' => 'Petugas lapangan yang dapat membuat dan mengelola laporan hama'
        ];
        
        return $descriptions[$role] ?? 'Pengguna dengan role ' . strtolower($role);
    }
    
    /**
     * Generate markdown content
     */
    private function generateMarkdown() {
        $groupedUsers = $this->groupUsersByRole();
        $totalUsers = count($this->users);
        $activeUsers = count(array_filter($this->users, function($user) {
            return $user['aktif'] == 1;
        }));
        
        $markdown = $this->getMarkdownHeader($totalUsers, $activeUsers);
        
        $roleCounter = 1;
        foreach ($groupedUsers as $role => $users) {
            $markdown .= $this->generateRoleSection($role, $users, $roleCounter);
            $roleCounter++;
        }
        
        $markdown .= $this->getMarkdownFooter();
        
        return $markdown;
    }
    
    /**
     * Generate markdown header
     */
    private function getMarkdownHeader($totalUsers, $activeUsers) {
        $timestamp = date('Y-m-d H:i:s');
        
        return "# Daftar User Aplikasi JAGAPADI

**Dokumen Otomatis - Generated dari Database Aktif**

---

**Versi Dokumen**: V.2.0.0 (Auto-Generated)  
**Tanggal Generate**: $timestamp  
**Sumber Data**: Database Live Connection  
**Total User**: $totalUsers akun  
**User Aktif**: $activeUsers akun  

---

## Ringkasan

Dokumentasi ini dibuat secara otomatis dengan mengambil data langsung dari database aktif menggunakan script `scripts/generate_user_docs.php`.

**Analisis Password:**
- ✅ Password default terdeteksi akan ditampilkan
- 🔒 Password yang telah diubah akan ditampilkan sebagai \"Terenkripsi\"
- 📊 Hash preview ditampilkan untuk identifikasi

---

";
    }
    
    /**
     * Generate role section
     */
    private function generateRoleSection($role, $users, $sectionNumber) {
        $roleDescription = $this->getRoleDescription($role);
        $userCount = count($users);
        
        $markdown = "## $sectionNumber. $role ($userCount user)\n\n";
        $markdown .= "*$roleDescription*\n\n";
        
        // Table header
        $markdown .= "| No | Username | Password Status | Nama Lengkap | Email | Status |\n";
        $markdown .= "|----|----------|-----------------|--------------|-------|--------|\n";
        
        // Table rows
        $counter = 1;
        foreach ($users as $user) {
            $passwordStatus = $this->analyzePassword($user['password']);
            $namaLengkap = $user['nama_lengkap'] ?: '-';
            $email = $user['email'] ?: '-';
            $status = $user['aktif'] ? '🟢 Aktif' : '🔴 Nonaktif';
            
            $markdown .= "| $counter | {$user['username']} | $passwordStatus | $namaLengkap | $email | $status |\n";
            $counter++;
        }
        
        $markdown .= "\n";
        
        // Add role permissions info
        $markdown .= $this->getRolePermissions($role);
        $markdown .= "\n---\n\n";
        
        return $markdown;
    }
    
    /**
     * Get role permissions description
     */
    private function getRolePermissions($role) {
        $permissions = [
            'Admin' => [
                'Manajemen user (CRUD)',
                'Konfigurasi sistem',
                'Verifikasi laporan',
                'Export/Import data',
                'Akses ke semua modul'
            ],
            'Operator' => [
                'Verifikasi laporan hama',
                'Approve/Reject laporan',
                'Dashboard monitoring',
                'Export data laporan'
            ],
            'Viewer' => [
                'Read-only access',
                'Dashboard view',
                'Laporan statistik',
                'Export data (terbatas)'
            ],
            'Petugas' => [
                'Input laporan hama',
                'Upload foto serangan',
                'GPS coordinate input',
                'Edit laporan sendiri'
            ]
        ];
        
        if (!isset($permissions[$role])) {
            return "**Hak Akses**: Sesuai konfigurasi role $role\n";
        }
        
        $markdown = "**Hak Akses $role**:\n";
        foreach ($permissions[$role] as $permission) {
            $markdown .= "- $permission\n";
        }
        
        return $markdown;
    }
    
    /**
     * Generate markdown footer
     */
    private function getMarkdownFooter() {
        $executionTime = round(microtime(true) - $this->startTime, 3);
        $timestamp = date('Y-m-d H:i:s');
        
        return "## Informasi Teknis

### Script Execution:
- **Execution Time**: {$executionTime} seconds
- **Generated At**: $timestamp
- **Script Location**: `scripts/generate_user_docs.php`
- **Database Connection**: Live database

### Password Analysis Results:
- Script memeriksa password hash terhadap daftar password default umum
- Password yang cocok ditampilkan dengan label \"(Default)\"
- Password yang tidak cocok ditampilkan sebagai \"Terenkripsi/Telah Diubah\"

### Cara Update Dokumentasi:
```bash
# Jalankan script untuk regenerate dokumentasi
php scripts/generate_user_docs.php
```

---

## Catatan Keamanan

⚠️ **PERINGATAN**:
- Dokumentasi ini berisi informasi sensitif tentang akun pengguna
- Jangan commit file ini ke repository publik
- Gunakan hanya untuk keperluan development dan testing
- Selalu ganti password default di production environment

### Troubleshooting:
1. **Database Connection Error**: Periksa konfigurasi di `config/config.php`
2. **Permission Denied**: Pastikan script memiliki akses write ke root folder
3. **Memory Limit**: Untuk database besar, tingkatkan `memory_limit` PHP

---

**© 2026 JAGAPADI Development Team. Auto-Generated Documentation.**

*Dokumen ini dibuat secara otomatis dari database aktif. Untuk update terbaru, jalankan kembali script generator.*
";
    }
    
    /**
     * Write markdown to file
     */
    private function writeToFile($content) {
        try {
            $result = file_put_contents($this->outputFile, $content);
            if ($result === false) {
                throw new Exception('Failed to write file');
            }
            
            $this->log('SUCCESS', 'Documentation written to: ' . $this->outputFile);
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to write file: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log message with timestamp
     */
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message";
        echo $logMessage . PHP_EOL;
    }
    
    /**
     * Main execution method
     */
    public function generate() {
        $this->log('INFO', 'Starting user documentation generation...');
        
        // Connect to database
        if (!$this->connectDatabase()) {
            return false;
        }
        
        // Fetch users
        if (!$this->fetchUsers()) {
            return false;
        }
        
        if (empty($this->users)) {
            $this->log('WARNING', 'No users found in database');
            return false;
        }
        
        // Generate markdown
        $this->log('INFO', 'Generating markdown content...');
        $markdown = $this->generateMarkdown();
        
        // Write to file
        if (!$this->writeToFile($markdown)) {
            return false;
        }
        
        // Success message
        $userCount = count($this->users);
        $executionTime = round(microtime(true) - $this->startTime, 3);
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "✅ SUCCESS: File README-USERS-JAGAPADI.md berhasil dibuat dengan $userCount user.\n";
        echo "📁 Location: " . $this->outputFile . "\n";
        echo "⏱️  Execution Time: {$executionTime} seconds\n";
        echo str_repeat('=', 60) . "\n\n";
        
        return true;
    }
}

// ============================================
// Main Execution
// ============================================

try {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  JAGAPADI User Documentation Generator\n";
    echo "  Auto-Generate from Live Database\n";
    echo str_repeat('=', 60) . "\n\n";
    
    $generator = new UserDocumentationGenerator();
    $success = $generator->generate();
    
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
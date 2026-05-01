<?php
/**
 * Database Indexes Migration Script
 * Adds missing indexes to improve query performance
 * 
 * Run: php scripts/add_database_indexes.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

function quoteIdentifierForIndex(string $identifier): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        throw new InvalidArgumentException("Invalid database identifier: {$identifier}");
    }

    return "`{$identifier}`";
}

function quoteColumnListForIndex(string $columns): string {
    $quotedColumns = [];

    foreach (array_map('trim', explode(',', $columns)) as $column) {
        $quotedColumns[] = quoteIdentifierForIndex($column);
    }

    return implode(', ', $quotedColumns);
}

echo "=== Database Indexes Migration ===\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n\n";

$indexes = [
    // Activity Log indexes
    [
        'table' => 'activity_log',
        'index' => 'idx_activity_log_created_at',
        'columns' => 'created_at',
        'type' => 'BTREE'
    ],
    [
        'table' => 'activity_log',
        'index' => 'idx_activity_log_action',
        'columns' => 'action',
        'type' => 'BTREE'
    ],
    [
        'table' => 'activity_log',
        'index' => 'idx_activity_log_table_name',
        'columns' => 'table_name',
        'type' => 'BTREE'
    ],
    
    // Users indexes
    [
        'table' => 'users',
        'index' => 'idx_users_role',
        'columns' => 'role',
        'type' => 'BTREE'
    ],
    [
        'table' => 'users',
        'index' => 'idx_users_aktif',
        'columns' => 'aktif',
        'type' => 'BTREE'
    ],
    [
        'table' => 'users',
        'index' => 'idx_users_email',
        'columns' => 'email',
        'type' => 'BTREE'
    ],
    [
        'table' => 'users',
        'index' => 'idx_users_created_at',
        'columns' => 'created_at',
        'type' => 'BTREE'
    ],
    
    // Laporan Hama indexes
    [
        'table' => 'laporan_hama',
        'index' => 'idx_laporan_hama_status',
        'columns' => 'status',
        'type' => 'BTREE'
    ],
    [
        'table' => 'laporan_hama',
        'index' => 'idx_laporan_hama_desa',
        'columns' => 'desa_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'laporan_hama',
        'index' => 'idx_laporan_hama_kecamatan',
        'columns' => 'kecamatan_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'laporan_hama',
        'index' => 'idx_laporan_hama_user',
        'columns' => 'user_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'laporan_hama',
        'index' => 'idx_laporan_hama_created_at',
        'columns' => 'created_at',
        'type' => 'BTREE'
    ],
    
    // Data Irigasi indexes
    [
        'table' => 'data_irigasi',
        'index' => 'idx_irigasi_status',
        'columns' => 'status_kondisi',
        'type' => 'BTREE'
    ],
    [
        'table' => 'data_irigasi',
        'index' => 'idx_irigasi_kabupaten',
        'columns' => 'kabupaten_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'data_irigasi',
        'index' => 'idx_irigasi_kecamatan',
        'columns' => 'kecamatan_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'data_irigasi',
        'index' => 'idx_irigasi_desa',
        'columns' => 'desa_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'data_irigasi',
        'index' => 'idx_irigasi_user',
        'columns' => 'user_id',
        'type' => 'BTREE'
    ],
    [
        'table' => 'data_irigasi',
        'index' => 'idx_irigasi_created_at',
        'columns' => 'created_at',
        'type' => 'BTREE'
    ],
    
    // Curah Hujan indexes
    [
        'table' => 'curah_hujan',
        'index' => 'idx_curah_hujan_date',
        'columns' => 'tanggal',
        'type' => 'BTREE'
    ],
    [
        'table' => 'curah_hujan',
        'index' => 'idx_curah_hujan_station',
        'columns' => 'station_id',
        'type' => 'BTREE'
    ],
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($indexes as $indexInfo) {
    $table = $indexInfo['table'];
    $indexName = $indexInfo['index'];
    $columns = $indexInfo['columns'];
    
    try {
        $quotedTable = quoteIdentifierForIndex($table);
        $quotedIndex = quoteIdentifierForIndex($indexName);
        $quotedColumns = quoteColumnListForIndex($columns);

        // Check if index already exists
        $checkSql = "
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = ?
            AND index_name = ?
        ";
        
        $stmt = $db->prepare($checkSql);
        $stmt->execute([$table, $indexName]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            echo "✓ Index {$indexName} already exists on {$table}.{$columns}\n";
            $skipped++;
            continue;
        }
        
        // Create index
        $sql = "CREATE INDEX {$quotedIndex} ON {$quotedTable}({$quotedColumns})";
        $db->exec($sql);
        
        echo "✓ Created index {$indexName} on {$table}.{$columns}\n";
        $success++;
        
    } catch (PDOException $e) {
        echo "✗ Error creating index {$indexName} on {$table}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Migration Summary ===\n";
echo "Created: {$success}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "Total: " . count($indexes) . "\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";

if ($errors > 0) {
    exit(1);
}

echo "\n✓ Database indexes migration completed successfully!\n";
exit(0);

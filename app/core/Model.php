<?php
class Model {
    protected $db;
    protected $table;
    protected $fillable = [];
    protected array $relations = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function all() {
        // Sanitize table name to prevent SQL injection
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        if (empty($table)) {
            throw new RuntimeException('Invalid table name');
        }
        $stmt = $this->db->prepare("SELECT * FROM `{$table}`");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function find($id) {
        // Sanitize table name
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        if (empty($table)) {
            throw new RuntimeException('Invalid table name');
        }
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function where($conditions = []) {
        // Sanitize table name
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        if (empty($table)) {
            throw new RuntimeException('Invalid table name');
        }

        $sql = "SELECT * FROM `{$table}`";
        $params = [];
        
        if (!empty($conditions)) {
            $sql .= " WHERE ";
            $whereClause = [];
            foreach ($conditions as $key => $value) {
                $whereClause[] = $this->quoteIdentifier((string)$key) . " = ?";
                $params[] = $value;
            }
            $sql .= implode(" AND ", $whereClause);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create($data) {
        // Sanitize table name to prevent SQL injection
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        if (empty($table)) {
            throw new RuntimeException('Invalid table name');
        }

        $quotedColumns = [];
        foreach (array_keys($data) as $column) {
            if (!in_array($column, $this->fillable, true) && !empty($this->fillable)) {
                throw new InvalidArgumentException("Mass assignment protection: column '{$column}' is not fillable");
            }

            $quotedColumns[] = $this->quoteIdentifier((string)$column);
        }

        if (empty($quotedColumns)) {
            throw new RuntimeException('No valid columns to insert');
        }

        $columns = implode(', ', $quotedColumns);
        $placeholders = implode(', ', array_fill(0, count($quotedColumns), '?'));
        
        $sql = "INSERT INTO `{$table}` ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $setClause = [];
        $params = [];
        
        // Validate that only fillable columns are being updated
        foreach ($data as $key => $value) {
            // Check if column is in fillable array
            if (!in_array($key, $this->fillable) && !empty($this->fillable)) {
                throw new InvalidArgumentException("Mass assignment protection: column '{$key}' is not fillable");
            }

            $setClause[] = $this->quoteIdentifier((string)$key) . " = ?";
            $params[] = $value;
        }

        $params[] = $id;

        // Sanitize table name
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        if (empty($table)) {
            throw new RuntimeException('Invalid table name');
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setClause) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        // Sanitize table name
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        if (empty($table)) {
            throw new RuntimeException('Invalid table name');
        }
        $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Fetch all records and eager load configured relations.
     */
    public function with(array|string $relations): array {
        return $this->eagerLoad($this->all(), $relations);
    }

    /**
     * Attach relations to an existing result set using one query per relation.
     *
     * Supported relation types:
     * - belongsTo: current rows hold local_key, related table holds foreign_key
     * - hasMany: current rows hold local_key, related table rows hold foreign_key
     */
    public function eagerLoad(array $rows, array|string $relations): array {
        if (empty($rows)) {
            return $rows;
        }

        foreach ($this->normalizeRelationNames($relations) as $relationName) {
            if (!isset($this->relations[$relationName])) {
                throw new InvalidArgumentException("Relation '{$relationName}' is not defined on " . static::class);
            }

            $config = $this->relations[$relationName];
            $type = $config['type'] ?? 'belongsTo';
            $localKey = $config['local_key'] ?? null;
            $foreignKey = $config['foreign_key'] ?? 'id';
            $resultKey = $config['result_key'] ?? $relationName;

            if (!$localKey || empty($config['table'])) {
                throw new InvalidArgumentException("Relation '{$relationName}' has incomplete configuration");
            }

            foreach ($rows as &$row) {
                $row[$resultKey] = $type === 'hasMany' ? [] : null;
            }
            unset($row);

            $ids = $this->extractUniqueRelationIds($rows, $localKey);
            if (empty($ids)) {
                continue;
            }

            $relatedRows = $this->fetchRelationRows($config, $ids, $foreignKey);

            if ($type === 'hasMany') {
                $grouped = [];
                foreach ($relatedRows as $relatedRow) {
                    $grouped[(string)$relatedRow[$foreignKey]][] = $relatedRow;
                }

                foreach ($rows as &$row) {
                    $key = (string)($row[$localKey] ?? '');
                    $row[$resultKey] = $grouped[$key] ?? [];
                }
                unset($row);

                continue;
            }

            $indexed = [];
            foreach ($relatedRows as $relatedRow) {
                $indexed[(string)$relatedRow[$foreignKey]] = $relatedRow;
            }

            foreach ($rows as &$row) {
                $key = (string)($row[$localKey] ?? '');
                $row[$resultKey] = $indexed[$key] ?? null;
            }
            unset($row);
        }

        return $rows;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    private function normalizeRelationNames(array|string $relations): array {
        if (is_string($relations)) {
            $relations = array_filter(array_map('trim', explode(',', $relations)));
        }

        return array_values($relations);
    }

    private function extractUniqueRelationIds(array $rows, string $localKey): array {
        $ids = [];

        foreach ($rows as $row) {
            if (!array_key_exists($localKey, $row)) {
                continue;
            }

            $value = $row[$localKey];
            if ($value === null || $value === '') {
                continue;
            }

            $ids[(string)$value] = $value;
        }

        return array_values($ids);
    }

    private function fetchRelationRows(array $config, array $ids, string $foreignKey): array {
        $table = $this->quoteIdentifier($config['table']);
        $foreignColumn = $this->quoteIdentifier($foreignKey);
        $columns = $this->buildRelationColumns($config['columns'] ?? ['*'], $foreignKey);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT {$columns} FROM {$table} WHERE {$foreignColumn} IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildRelationColumns(array $columns, string $foreignKey): string {
        if ($columns === ['*']) {
            return '*';
        }

        if (!in_array($foreignKey, $columns, true)) {
            $columns[] = $foreignKey;
        }

        return implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
    }

    private function quoteIdentifier(string $identifier): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            throw new InvalidArgumentException("Invalid database identifier: {$identifier}");
        }

        $parts = explode('.', $identifier);
        return implode('.', array_map(static fn(string $part): string => "`{$part}`", $parts));
    }
}

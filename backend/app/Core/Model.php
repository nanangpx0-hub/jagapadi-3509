<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected static function db(): PDO
    {
        return Database::connect();
    }

    protected static function table(): string
    {
        return '';
    }

    /**
     * Quote identifier (table/column) — allow only [A-Za-z_][A-Za-z0-9_]* optionally dotted.
     * Mencegah injection via backtick closure.
     */
    private static function quoteIdentifier(string $identifier): string
    {
        // Support table.column
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new \InvalidArgumentException("Invalid identifier: {$identifier}");
            }
        }
        return implode('.', array_map(fn(string $p): string => "`{$p}`", $parts));
    }

    /**
     * Per-model allowlist untuk kolom. Child dapat mendefinisikan const ALLOWED_COLUMNS = [...].
     * Jika kosong, hanya validasi sintaks via quoteIdentifier.
     * Jika terisi, nilai harus ada di allowlist.
     */
    private static function allowedColumns(): array
    {
        $const = static::class . '::ALLOWED_COLUMNS';
        if (defined($const)) {
            $val = constant($const);
            return is_array($val) ? $val : [];
        }
        return [];
    }

    private static function assertValidColumn(string $column): void
    {
        // Validasi sintaks
        self::quoteIdentifier($column);
        $allowed = self::allowedColumns();
        if ($allowed !== []) {
            // Untuk dotted identifier, cek bagian kolom saja
            $bare = explode('.', $column);
            $bare = end($bare);
            if (!in_array($bare, $allowed, true) && !in_array($column, $allowed, true)) {
                throw new \InvalidArgumentException("Column not allowed: {$column}");
            }
        }
    }

    private static function sanitizeOrderBy(string $orderBy): string
    {
        // Cek allowlist per-model ALLOWED_ORDER_COLUMNS jika ada
        $const = static::class . '::ALLOWED_ORDER_COLUMNS';
        if (defined($const)) {
            $allowed = constant($const);
            if (is_array($allowed) && $allowed !== [] && !in_array($orderBy, $allowed, true)) {
                throw new \InvalidArgumentException("ORDER BY not allowed: {$orderBy}");
            }
        }
        // Validasi sintaks dotted
        return self::quoteIdentifier($orderBy);
    }

    private static function sanitizeColumns(array $data): array
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Insert/update data cannot be empty');
        }
        $allowed = self::allowedColumns();
        $sanitized = [];
        foreach (array_keys($data) as $col) {
            if (!is_string($col) || $col === '') {
                throw new \InvalidArgumentException('Invalid column name');
            }
            self::quoteIdentifier($col);
            if ($allowed !== [] && !in_array($col, $allowed, true)) {
                throw new \InvalidArgumentException("Column not allowed: {$col}");
            }
            $sanitized[] = $col;
        }
        return $sanitized;
    }

    public static function find(int|string $id): ?array
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        $stmt = $pdo->prepare("SELECT * FROM `" . $table . "` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        self::assertValidColumn($column);
        $col = self::quoteIdentifier($column);
        $stmt = $pdo->prepare("SELECT * FROM `" . $table . "` WHERE {$col} = ? LIMIT 1");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $order = self::sanitizeOrderBy($orderBy);
        $stmt = $pdo->query("SELECT * FROM `" . $table . "` ORDER BY {$order} {$direction}");
        return $stmt->fetchAll();
    }

    /**
     * Count tanpa WHERE arbitrer. Jika butuh filter, gunakan method spesifik di Model child
     * dengan prepared statements & allowlist. Menerima string kosong saja untuk comptat.
     */
    public static function count(string $where = ''): int
    {
        if ($where !== '') {
            throw new \InvalidArgumentException('Raw WHERE fragment not allowed in Model::count(); gunakan method filtered di child model dengan prepared statements.');
        }
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        return (int) $pdo->query("SELECT COUNT(*) FROM `" . $table . "`")->fetchColumn();
    }

    /**
     * Count dengan kondisi terstruktur (allowlist). Alternatif aman untuk where arbitrer.
     * @param array<string,mixed> $conditions col=>value (AND)
     */
    public static function countBy(array $conditions = []): int
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        if ($conditions === []) {
            return (int) $pdo->query("SELECT COUNT(*) FROM `" . $table . "`")->fetchColumn();
        }
        $clauses = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            if (!is_string($col)) {
                throw new \InvalidArgumentException('Invalid condition column');
            }
            self::assertValidColumn($col);
            $clauses[] = self::quoteIdentifier($col) . ' = ?';
            $params[] = $val;
        }
        $sql = "SELECT COUNT(*) FROM `" . $table . "` WHERE " . implode(' AND ', $clauses);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function insert(array $data): int|string
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        $cols = self::sanitizeColumns($data);
        $quoted = array_map(fn(string $c): string => self::quoteIdentifier($c), $cols);
        $columns = implode('`, `', $cols); // already validated, but quote again via quoted
        $columns = implode(', ', $quoted);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO `" . $table . "` ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        $cols = self::sanitizeColumns($data);
        $sets = implode(', ', array_map(fn(string $col): string => self::quoteIdentifier($col) . ' = ?', $cols));
        $stmt = $pdo->prepare("UPDATE `" . $table . "` SET {$sets} WHERE `id` = ?");
        $values = array_values($data);
        $values[] = $id;
        return $stmt->execute($values);
    }

    public static function delete(int $id): bool
    {
        $pdo = self::db();
        $table = static::table();
        if ($table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table');
        }
        $stmt = $pdo->prepare("DELETE FROM `" . $table . "` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }
}

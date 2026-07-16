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

    public static function find(int|string $id): ?array
    {
        $pdo = self::db();
        $table = static::table();
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $pdo = self::db();
        $table = static::table();
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$column` = ? LIMIT 1");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $pdo = self::db();
        $table = static::table();
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY `$orderBy` $direction");
        return $stmt->fetchAll();
    }

    public static function count(string $where = ''): int
    {
        $pdo = self::db();
        $table = static::table();
        $sql = "SELECT COUNT(*) FROM `$table`";
        if ($where !== '') {
            $sql .= " WHERE $where";
        }
        return (int) $pdo->query($sql)->fetchColumn();
    }

    public static function insert(array $data): int|string
    {
        $pdo = self::db();
        $table = static::table();
        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $pdo->prepare("INSERT INTO `$table` (`$columns`) VALUES ($placeholders)");
        $stmt->execute(array_values($data));

        return $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = self::db();
        $table = static::table();
        $sets = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));

        $stmt = $pdo->prepare("UPDATE `$table` SET $sets WHERE `id` = ?");
        $values = array_values($data);
        $values[] = $id;
        return $stmt->execute($values);
    }

    public static function delete(int $id): bool
    {
        $pdo = self::db();
        $table = static::table();
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }
}

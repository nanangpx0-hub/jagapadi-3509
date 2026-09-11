<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Gerbang konektivitas database MariaDB/MySQL.
 * Skip (bukan error) bila daemon lokal sedang offline.
 */
final class DatabaseConnectivityTest extends TestCase
{
    public function testDatabaseReachableWhenOnline(): void
    {
        try {
            $pdo = Database::connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database tidak tersedia: ' . $e->getMessage());
        }

        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        self::assertNotFalse($version);
        self::assertNotSame('', (string) $version);
    }
}

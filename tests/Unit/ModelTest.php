<?php

use PHPUnit\Framework\TestCase;

final class CrudTestModel extends Model {
    protected $table;
    protected $fillable = ['name', 'status', 'quantity'];

    public function __construct(PDO $pdo, string $table) {
        $this->db = $pdo;
        $this->table = $table;
    }
}

final class ModelTest extends TestCase {
    private PDO $pdo;
    private CrudTestModel $model;
    private string $table;

    protected function setUp(): void {
        $this->table = 'phpunit_model_' . bin2hex(random_bytes(4));
        $this->pdo = $this->createTestConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec("
                CREATE TABLE {$this->quoteTable()} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    status TEXT NOT NULL,
                    quantity INTEGER NOT NULL DEFAULT 0
                )
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE {$this->quoteTable()} (
                    id INT NOT NULL AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    quantity INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $this->model = new CrudTestModel($this->pdo, $this->table);
    }

    protected function tearDown(): void {
        if (isset($this->pdo, $this->table)) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$this->quoteTable()}");
        }
    }

    private function createTestConnection(): PDO {
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return new PDO('sqlite::memory:');
        }

        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite or MySQL driver is required for ModelTest.');
        }

        if (!function_exists('loadEnv') && is_file(ROOT_PATH . '/config/env.php')) {
            require_once ROOT_PATH . '/config/env.php';
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $database = getenv('DB_NAME') ?: 'bpsjembe_jagapadi';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';

        try {
            return new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $user, $password);
        } catch (PDOException $e) {
            self::markTestSkipped('MySQL test database is unavailable: ' . $e->getMessage());
        }
    }

    private function quoteTable(): string {
        return '`' . str_replace('`', '``', $this->table) . '`';
    }

    public function testCreateFindUpdateAndDelete(): void {
        $id = (int)$this->model->create([
            'name' => 'Laporan A',
            'status' => 'draft',
            'quantity' => 3,
        ]);

        self::assertGreaterThan(0, $id);
        self::assertSame('Laporan A', $this->model->find($id)['name']);

        self::assertTrue($this->model->update($id, [
            'status' => 'published',
            'quantity' => 9,
        ]));

        $updated = $this->model->find($id);
        self::assertSame('published', $updated['status']);
        self::assertSame(9, (int)$updated['quantity']);

        self::assertTrue($this->model->delete($id));
        self::assertFalse($this->model->find($id));
    }

    public function testWhereFiltersByColumnSafely(): void {
        $this->model->create(['name' => 'A', 'status' => 'active', 'quantity' => 1]);
        $this->model->create(['name' => 'B', 'status' => 'inactive', 'quantity' => 2]);

        $rows = $this->model->where(['status' => 'active']);

        self::assertCount(1, $rows);
        self::assertSame('A', $rows[0]['name']);
    }

    public function testWhereRejectsUnsafeColumnName(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->model->where(['status; DROP TABLE test_records; --' => 'active']);
    }

    public function testCreateRejectsNonFillableColumn(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->model->create([
            'name' => 'A',
            'status' => 'active',
            'quantity' => 1,
            'is_admin' => 1,
        ]);
    }

    public function testUpdateRejectsUnsafeColumnName(): void {
        $id = (int)$this->model->create(['name' => 'A', 'status' => 'active', 'quantity' => 1]);

        $this->expectException(InvalidArgumentException::class);
        $this->model->update($id, ['status; DROP TABLE test_records; --' => 'active']);
    }
}

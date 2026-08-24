<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * FeedbackAttachmentUploadTest
 *
 * Regresi bug lampiran feedback (runtime root):
 *  - UPLOAD_ERR_OK wajib masuk jalur handleFileUpload, bukan pesan error default.
 *  - Mapping uploadErrorMessage() lengkap untuk seluruh kode UPLOAD_ERR_*.
 *  - Penolakan file kosong, >5 MB, MIME palsu, dan spoofing ekstensi.
 *  - Kegagalan mkdir/move menghasilkan pesan ramah tanpa detail internal.
 *  - Gagal simpan DB setelah upload memicu pembersihan file orphan.
 *  - deleteAttachmentFile() menolak path di luar direktori feedback.
 */
final class FeedbackAttachmentUploadTest extends TestCase
{
    private FeedbackController $controller;
    private Feedback $model;
    private string $marker;
    private int $realUserId = 0;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->controller = new FeedbackController();
        $this->model = new Feedback();
        $this->marker = 'CODEX-FBUP-' . bin2hex(random_bytes(4));
        $_SESSION['user_id'] = 999999;
        $_SESSION['nama_lengkap'] = 'Tester';

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'petugas' ORDER BY id LIMIT 1");
        $stmt->execute();
        $this->realUserId = (int) $stmt->fetchColumn();
        if ($this->realUserId === 0) {
            self::markTestSkipped('Akun petugas diperlukan untuk uji FK feedback.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $relative) {
            $full = ROOT_PATH . '/' . $relative;
            if (is_file($full)) {
                @unlink($full);
            }
        }
        $this->createdFiles = [];

        if (isset($this->model)) {
            $db = Database::getInstance()->getConnection();
            $db->prepare('DELETE FROM feedback WHERE judul LIKE ?')
                ->execute(['%' . $this->marker . '%']);
        }
    }

    public function testUploadErrOkEntersUploadPipelineInsteadOfDefaultError(): void
    {
        $tmp = $this->makeTempFile('png', $this->pngBytes());

        $result = $this->invokeUpload([
            'name' => 'foto.png',
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ]);

        // is_uploaded_file() wajib gagal di CLI; yang penting pesan BUKAN
        // pesan default uploadErrorMessage() — bukti jalur upload tercapai.
        self::assertFalse($result['success']);
        self::assertNotSame('Gagal mengunggah file lampiran.', $result['error']);
        self::assertSame('File lampiran tidak valid.', $result['error']);
    }

    public function testEveryUploadErrCodeHasExplicitMessage(): void
    {
        $expected = [
            UPLOAD_ERR_OK => 'Tidak ada galat pada unggahan berkas lampiran.',
            UPLOAD_ERR_INI_SIZE => 'Ukuran file lampiran melebihi batas maksimum.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file lampiran melebihi batas maksimum.',
            UPLOAD_ERR_PARTIAL => 'Upload file lampiran tidak lengkap, silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file lampiran yang diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori upload tidak tersedia di server.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file lampiran di server.',
            UPLOAD_ERR_EXTENSION => 'Upload file lampiran diblokir oleh ekstensi server.',
            999 => 'Gagal mengunggah file lampiran.',
        ];

        foreach ($expected as $code => $message) {
            self::assertSame(
                $message,
                $this->invokePrivate('uploadErrorMessage', [$code]),
                "Mapping kode {$code} harus eksplisit"
            );
        }
    }

    public function testStoreRejectsEmptyFile(): void
    {
        $tmp = $this->makeTempFile('png', '');

        $result = $this->invokeStore($tmp, 0);

        self::assertFalse($result['success']);
        self::assertSame('File lampiran kosong (0 byte).', $result['error']);
    }

    public function testStoreRejectsOversizedFile(): void
    {
        $tmp = $this->makeBigFile(5 * 1024 * 1024 + 1);

        $result = $this->invokeStore($tmp, 5 * 1024 * 1024 + 1);

        self::assertFalse($result['success']);
        self::assertSame('Ukuran file maksimal 5 MB.', $result['error']);
    }

    public function testStoreRejectsSpoofedMime(): void
    {
        $tmp = $this->makeTempFile('jpg', 'plain text bukan gambar sama sekali');

        $result = $this->invokeStore($tmp, filesize($tmp));

        self::assertFalse($result['success']);
        self::assertSame('Tipe file tidak diizinkan (JPG, PNG, GIF, WEBP, PDF).', $result['error']);
    }

    public function testStoreDerivesExtensionFromMimeNotClientName(): void
    {
        $tmp = $this->makeTempFile('php', $this->pngBytes());

        $result = $this->invokeStore($tmp, filesize($tmp));

        self::assertTrue($result['success'], 'Konten PNG sah harus diterima');
        self::assertStringEndsWith('.png', $result['path']);
        self::assertStringNotContainsString('.php', $result['path']);
        self::assertFileExists(ROOT_PATH . '/' . $result['path']);
        $this->createdFiles[] = $result['path'];
    }

    public function testStoreHandlesUnwritableTargetDirectorySafely(): void
    {
        $targetDir = ROOT_PATH . '/public/uploads/feedback/' . date('Y/m');

        $existing = is_dir($targetDir);
        $this->assertTrue(
            $existing || @mkdir($targetDir, 0755, true),
            'Persiapan direktori target gagal'
        );
        $canDeny = @chmod($targetDir, 0444);
        if (!$canDeny || is_writable($targetDir)) {
            @chmod($targetDir, 0755);
            self::markTestSkipped('Filesystem tidak mendukung simulasi direktori read-only.');
        }

        try {
            $tmp = $this->makeTempFile('png', $this->pngBytes());
            $result = $this->invokeStore($tmp, filesize($tmp));

            self::assertFalse($result['success']);
            self::assertSame(
                'Penyimpanan lampiran sedang tidak tersedia. Silakan coba lagi nanti.',
                $result['error'],
                'Pesan pengguna tidak boleh membocorkan path server'
            );
            self::assertStringNotContainsString(ROOT_PATH, $result['error']);
        } finally {
            @chmod($targetDir, 0755);
        }
    }

    public function testDbFailureAfterUploadDeletesOrphanFile(): void
    {
        $tmp = $this->makeTempFile('png', $this->pngBytes());
        $stored = $this->invokeStore($tmp, filesize($tmp));
        self::assertTrue($stored['success']);
        $this->createdFiles[] = $stored['path'];
        self::assertFileExists(ROOT_PATH . '/' . $stored['path']);

        $feedbackId = $this->model->create([
            'user_id' => $this->realUserId,
            'jenis_feedback' => 'bug',
            'judul' => str_repeat('x', 300),
            'deskripsi' => 'Deskripsi cukup panjang untuk validasi minimal.',
            'prioritas' => 'medium',
            'attachment_url' => $stored['path'],
        ]);
        self::assertFalse($feedbackId, 'judul >255 harus gagal di database');

        $this->invokePrivate('deleteAttachmentFile', [$stored['path']]);
        self::assertFileDoesNotExist(ROOT_PATH . '/' . $stored['path'],
            'File orphan wajib terhapus setelah gagal simpan DB');
    }

    public function testDeleteAttachmentRefusesPathOutsideFeedbackDirectory(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'fbout_');
        file_put_contents($outside, 'jangan dihapus');
        self::assertFileExists($outside);

        $this->invokePrivate('deleteAttachmentFile', [$outside]);
        self::assertFileExists($outside, 'Path absolut di luar uploads/feedback tidak boleh dihapus');

        $this->invokePrivate('deleteAttachmentFile', ['../.env']);
        self::assertFileExists(ROOT_PATH . '/.env', 'Traversal keluar direktori feedback tidak boleh dihapus');

        @unlink($outside);
    }

    public function testCreateWithoutAttachmentSucceeds(): void
    {
        $judul = $this->marker . ' tanpa lampiran';
        $feedbackId = $this->model->create([
            'user_id' => $this->realUserId,
            'jenis_feedback' => 'peningkatan',
            'judul' => $judul,
            'deskripsi' => 'Submit tanpa lampiran tetap harus berhasil tersimpan.',
            'prioritas' => 'medium',
            'attachment_url' => null,
        ]);

        self::assertNotFalse($feedbackId);
        $row = $this->fetchFeedback((int) $feedbackId);
        self::assertNotNull($row);
        self::assertNull($row['attachment_url']);
    }

    public function testControllerBranchOrderPrefersUploadForErrOk(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/controllers/FeedbackController.php');

        $okFirst = strpos($source, '$hasAttachment && $attachmentError === UPLOAD_ERR_OK');
        $errorBranch = strpos($source, '$this->uploadErrorMessage($attachmentError)');
        self::assertNotFalse($okFirst, 'Cabang UPLOAD_ERR_OK wajib ada');
        self::assertNotFalse($errorBranch);
        self::assertGreaterThan($okFirst, $errorBranch,
            'Cabang upload OK harus dicek SEBELUM cabang uploadErrorMessage');

        self::assertStringContainsString('deleteAttachmentFile($uploadedPath)', $source,
            'Gagal simpan DB wajib memanggil pembersihan orphan');
        self::assertStringContainsString('UPLOAD_ERR_OK =>', $source,
            'Mapping uploadErrorMessage wajib memuat UPLOAD_ERR_OK');
    }

    // ==================== helpers ====================

    private function invokeUpload(array $file): array
    {
        return $this->invokePrivate('handleFileUpload', [$file]);
    }

    private function invokeStore(string $tmpPath, int $size): array
    {
        return $this->invokePrivate('storeUploadedFile', [$tmpPath, $size]);
    }

    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($this->controller, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->controller, ...$args);
    }

    private function makeTempFile(string $suffix, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fbup_');
        rename($path, $path .= '.' . $suffix);
        file_put_contents($path, $content);

        return $path;
    }

    private function makeBigFile(int $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fbbig_');
        $handle = fopen($path, 'wb');
        $chunk = str_repeat("\0", 1024 * 1024);
        $written = 0;
        while ($written < $bytes) {
            $len = min(1024 * 1024, $bytes - $written);
            fwrite($handle, substr($chunk, 0, $len));
            $written += $len;
        }
        fclose($handle);

        return $path;
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    private function fetchFeedback(int $id): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT * FROM feedback WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function loadEnvironment(): void
    {
        foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LaporanHamaEnhancementContractTest extends TestCase
{
    public function testFormExposesObservationProposalVideoAndSecureGpsContracts(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/laporan/create.php');
        self::assertStringContainsString('name="metode_pengukuran"', $view);
        self::assertStringContainsString('name="persentase_serangan"', $view);
        self::assertStringContainsString('name="nama_hama_baru"', $view);
        self::assertStringContainsString('name="video"', $view);
        self::assertStringContainsString('window.isSecureContext', $view);
        self::assertStringContainsString('Tanggal Kejadian/Pengamatan', $view);
    }

    public function testServerValidatesPercentageAndPastDatesRemainAllowed(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/controllers/LaporanController.php');
        self::assertStringContainsString("['absolut', 'persentase']", $controller);
        self::assertStringContainsString('$date > new DateTime()', $controller);
        self::assertStringNotContainsString('$date < new DateTime()', $controller);
    }

    public function testMigrationKeepsPercentageSeparateFromAbsoluteArea(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/migrations/2026_08_21_add_hama_observation_media_and_opt_proposals.sql');
        self::assertStringContainsString('`persentase_serangan` DECIMAL(5,2)', $sql);
        self::assertStringContainsString('CREATE TABLE `usulan_opt`', $sql);
        self::assertStringContainsString('`video_url` VARCHAR(300)', $sql);
    }

    public function testPhotoInputProvidesStableAccessiblePreview(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/laporan/create.php');
        self::assertStringContainsString('id="fotoInput" accept="image/*"', $view);
        self::assertStringContainsString('id="fotoPreview"', $view);
        self::assertStringContainsString('alt="Preview foto laporan"', $view);
        self::assertStringContainsString('new FileReader()', $view);
        self::assertStringContainsString('reader.readAsDataURL(file)', $view);
        self::assertStringContainsString('previewImg.onload', $view);
        self::assertStringContainsString('previewImg.onerror', $view);
        self::assertStringContainsString("clearFotoButton", $view);
        self::assertStringContainsString("fotoInput.setCustomValidity('');", $view);
        self::assertStringContainsString('Foto tetap akan divalidasi saat dikirim.', $view);
    }

    public function testPhotoValidationPreservesTheRealErrorForASelectedFile(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/laporan/create.php');

        self::assertStringContainsString('const hasSelectedFile = Boolean(this.files', $view);
        self::assertStringContainsString('hasSelectedFile && this.validationMessage', $view);
        self::assertStringContainsString('fotoInput.checkValidity()', $view);
    }

    public function testBackendRequiresPhotoAndTreatsMissingVideoAsOptional(): void
    {
        $controllerSource = file_get_contents(__DIR__ . '/../../app/controllers/LaporanController.php');
        self::assertStringContainsString("\$_FILES['foto'] ?? null", $controllerSource);
        self::assertStringContainsString('laporanPhotoUploadErrorMessage', $controllerSource);
        self::assertStringContainsString("empty(\$postData['foto_url'])", $controllerSource);

        require_once __DIR__ . '/../../app/helpers/VideoUploader.php';
        $result = (new VideoUploader())->upload([
            'name' => '',
            'tmp_name' => '',
            'size' => 0,
            'error' => UPLOAD_ERR_NO_FILE,
        ]);

        self::assertTrue($result['success']);
        self::assertNull($result['path']);
    }

    public function testReportListsExposePhotoAndVideoPreviews(): void
    {
        $model = file_get_contents(__DIR__ . '/../../app/models/LaporanHama.php');
        $index = file_get_contents(__DIR__ . '/../../app/views/laporan/index.php');
        $petugas = file_get_contents(__DIR__ . '/../../app/views/reports/petugas_list.php');

        self::assertStringContainsString('lh.video_url', $model);
        self::assertStringContainsString('Preview Foto &amp; Video', $index);
        self::assertStringContainsString('class="video-thumbnail" controls', $index);
        self::assertStringContainsString('r.video_url', $index);
        self::assertStringContainsString("\$item['video_url']", $petugas);
        self::assertStringContainsString('<video controls preload="metadata" playsinline', $petugas);
    }

    public function testPhotoAndVideoInputsAreIndependentAndPersistToPublicPaths(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/laporan/create.php');
        $controller = file_get_contents(__DIR__ . '/../../app/controllers/LaporanController.php');
        $model = file_get_contents(__DIR__ . '/../../app/models/LaporanHama.php');

        self::assertSame(1, substr_count($view, 'name="foto"'));
        self::assertSame(1, substr_count($view, 'name="video"'));
        self::assertStringContainsString('id="fotoUploadPanel"', $view);
        self::assertStringContainsString('id="videoUploadPanel"', $view);
        self::assertStringContainsString("ROOT_PATH . '/public/uploads/laporan/'", $controller);
        self::assertStringContainsString("'foto_url'", $model);
        self::assertStringContainsString("'video_url'", $model);
    }

    public function testCreatePageDisablesFloatingAnimationLocally(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/laporan/create.php');

        self::assertStringContainsString('class="row laporan-create-page"', $view);
        self::assertStringContainsString('.laporan-create-page *::before', $view);
        self::assertStringContainsString('animation: none !important;', $view);
        self::assertStringContainsString('transition: none !important;', $view);
        self::assertStringContainsString('transform: none !important;', $view);
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DraftAutosaveIsolationTest extends TestCase
{
    public function testDraftStorageIsScopedByUserAndModule(): void
    {
        $script = file_get_contents(ROOT_PATH . '/public/js/draft-autosave.js');
        self::assertIsString($script);
        self::assertStringContainsString('this.form.dataset.draftUser', $script);
        self::assertStringContainsString('this.form.dataset.draftModule', $script);
        self::assertStringContainsString(
            '`${STORAGE_KEY_PREFIX}:${userId}:${moduleName}`',
            $script
        );
        self::assertStringContainsString('localStorage.removeItem(LEGACY_STORAGE_KEY)', $script);
    }

    public function testBothReportFormsDeclareDistinctDraftModules(): void
    {
        $hama = file_get_contents(ROOT_PATH . '/app/views/laporan/create.php');
        $other = file_get_contents(ROOT_PATH . '/app/views/laporan-lainnya/create.php');

        self::assertStringContainsString('data-draft-module="laporan-hama"', $hama);
        self::assertStringContainsString('data-draft-module="laporan-lainnya"', $other);
        self::assertStringContainsString('data-draft-user=', $hama);
        self::assertStringContainsString('data-draft-user=', $other);
    }

    public function testUntouchedDefaultFieldsDoNotCreateDraft(): void
    {
        $script = file_get_contents(ROOT_PATH . '/public/js/draft-autosave.js');
        self::assertStringContainsString("'tanggal_kejadian'", $script);
        self::assertStringContainsString("'kabupaten_id'", $script);
        self::assertStringContainsString('if (hasMeaningfulData)', $script);
        self::assertStringContainsString('this.clearDraft();', $script);
    }

    public function testSubmitSavesDraftInsteadOfDeletingItBeforeServerValidation(): void
    {
        $script = file_get_contents(ROOT_PATH . '/public/js/draft-autosave.js');
        self::assertStringContainsString("this.form.addEventListener('submit', () => {\n                this.saveDraft();", $script);
        self::assertStringNotContainsString("this.form.addEventListener('submit', () => {\n                this.clearDraft();", $script);
    }

    public function testReportFormsValidateAddressBeforeNavigation(): void
    {
        foreach (['laporan/create.php', 'laporan-lainnya/create.php'] as $viewPath) {
            $view = file_get_contents(ROOT_PATH . '/app/views/' . $viewPath);
            self::assertStringContainsString('minlength="<?= $alamatMinLength ?>"', $view);
            self::assertStringContainsString('alamatInput.reportValidity()', $view);
            self::assertStringContainsString("addEventListener('input'", $view);
        }
    }

    public function testOtherReportValidationTargetsTheReportFormExactly(): void
    {
        $view = file_get_contents(ROOT_PATH . '/app/views/laporan-lainnya/create.php');
        self::assertStringContainsString(
            "document.getElementById('formCreateLaporan').addEventListener('submit'",
            $view
        );
        self::assertStringNotContainsString(
            "document.querySelector('form').addEventListener('submit'",
            $view
        );
    }
}

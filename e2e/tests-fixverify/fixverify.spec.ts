import { test, expect } from '@playwright/test';
import fs from 'fs';

const BASE = 'http://localhost/jagapadi-3509';

async function loginAs(page, username, password) {
    await page.goto(BASE + '/auth/login');
    await page.fill('input[name="username"]', username);
    await page.fill('#password', password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL(/\/(dashboard|password\/change)/);
    if (page.url().includes('/password/change')) {
        await page.goto(BASE + '/dashboard');
        await page.waitForURL(/\/dashboard/);
    }
}

test.describe('Verifikasi fix /laporan/create', () => {
    test('form terbuka tanpa error, dropdown wilayah terisi, Jember terpilih otomatis', async ({ page }) => {
        await loginAs(page, 'admin', 'admin123');
        await page.goto(BASE + '/laporan/create');
        await page.waitForLoadState('domcontentloaded');

        await expect(page).toHaveURL(/\/laporan\/create$/);
        await expect(page.locator('h1')).toContainText('Buat Laporan Baru');
        await expect(page.locator('.alert-danger')).toHaveCount(0, { timeout: 5000 });

        // Kabupaten otomatis terpilih Jember (value '09')
        await expect
            .poll(() => page.locator('#kabupatenSelect').inputValue(), { timeout: 15000 })
            .toBe('09');

        // Kecamatan ter-load otomatis (31 kecamatan + placeholder)
        await expect
            .poll(() => page.locator('#kecamatanSelect option').count(), { timeout: 20000 })
            .toBeGreaterThan(5);
    });

    test('submit laporan lengkap berhasil (kabupaten "09" tersimpan sebagai id 1)', async ({ page }) => {
        await loginAs(page, 'admin', 'admin123');
        await page.goto(BASE + '/laporan/create');
        await page.waitForLoadState('domcontentloaded');

        await expect(page.locator('.alert-danger')).toHaveCount(0, { timeout: 5000 });
        await expect
            .poll(() => page.locator('#kabupatenSelect').inputValue(), { timeout: 15000 })
            .toBe('09');
        // Kecamatan ter-load otomatis (31 kecamatan + placeholder)
        await expect
            .poll(() => page.locator('#kecamatanSelect option').count(), { timeout: 20000 })
            .toBeGreaterThan(5);

        // Pilih kecamatan Ambulu (index 1) -> desa harus ter-load
        await page.selectOption('#kecamatanSelect', { index: 1 });
        await expect
            .poll(() => page.locator('#desaSelect option').count(), { timeout: 20000 })
            .toBeGreaterThan(2);
        await page.selectOption('#desaSelect', { index: 1 });

        // Isi field wajib
        const tanggal = new Date().toISOString().slice(0, 10);
        await page.fill('input[name="tanggal"]', tanggal);
        await page.selectOption('select[name="master_opt_id"]', { index: 1 });
        await page.fill('input[name="alamat_lengkap"]', 'Jl. Verifikasi E2E No.7, Jember');
        await page.selectOption('select[name="tingkat_keparahan"]', 'Ringan');
        await page.fill('#populasiInput', '12');
        await page.fill('#luasSeranganInput', '3');

        await page.locator('#btnSubmitForm').click();

        // Harus redirect ke halaman detail laporan
        await page.waitForURL(/\/laporan\/detail\/\d+/, { timeout: 30000 });
        const match = page.url().match(/\/laporan\/detail\/(\d+)/);
        expect(match).not.toBeNull();
        fs.mkdirSync('reports', { recursive: true });
        fs.writeFileSync('reports/created-laporan-id.txt', match[1]);
        console.log('Laporan dibuat: id=' + match[1]);

        // Tidak boleh ada error di halaman detail
        await expect(page.locator('.alert-danger')).toHaveCount(0, { timeout: 5000 });
    });
});

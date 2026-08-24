import { expect, test, type Page } from '@playwright/test';
import path from 'node:path';

const BASE = process.env.BASE_URL || 'http://localhost/jagapadi-3509';
const PETUGAS_USER = process.env.E2E_PETUGAS_USER || 'petugas01';
const PETUGAS_PASS = process.env.E2E_PETUGAS_PASS || 'Jember3509';

async function loginAsPetugas(page: Page): Promise<void> {
  await page.goto(`${BASE}/auth/login`);
  await page.getByRole('textbox', { name: 'Username' }).fill(PETUGAS_USER);
  await page.locator('#password').fill(PETUGAS_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function fillRequiredProposal(page: Page, uniqueName: string): Promise<void> {
  await page.getByLabel('Nama lokal/daerah').fill(uniqueName);
  await page.getByLabel('Komoditas yang diserang').fill('Padi');
  await page.getByLabel('Tanggal ditemukan').fill('2026-08-20');
  await page.getByLabel('Ciri-ciri/gejala').fill('Daun berlubang dan menguning pada bagian tepi.');

  await expect(page.getByLabel('Kabupaten')).toContainText('Jember');
  await page.getByLabel('Kabupaten').selectOption({ label: 'Jember' });
  await expect(page.getByLabel('Kecamatan')).toBeEnabled();
  await expect.poll(() => page.getByLabel('Kecamatan').locator('option').count()).toBeGreaterThan(1);
  await page.getByLabel('Kecamatan').selectOption({ index: 1 });
  await expect(page.getByLabel('Desa')).toBeEnabled();
  await expect.poll(() => page.getByLabel('Desa').locator('option').count()).toBeGreaterThan(1);
  await page.getByLabel('Desa').selectOption({ index: 1 });
}

test.describe('Form buat Usulan OPT', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsPetugas(page);
    await page.goto(`${BASE}/usulan-opt/create`);
    await expect(page.getByRole('heading', { name: /Buat Usulan OPT Baru/ })).toBeVisible();
  });

  test('validasi native, cascade wilayah, preview foto, error submit, dan alur draf', async ({ page }) => {
    const uniqueName = `E2E Draf OPT ${Date.now()}`;

    await page.getByRole('button', { name: /Kirim untuk Review/ }).click();
    await expect(page).toHaveURL(/\/usulan-opt\/create$/);
    expect(await page.getByLabel('Nama lokal/daerah').evaluate((el: HTMLInputElement) => el.validity.valueMissing)).toBe(true);

    await fillRequiredProposal(page, uniqueName);
    // Submit tanpa foto diverifikasi server dan nilai input harus tetap tersedia.
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Kirim untuk Review/ }).click();
    await expect(page).toHaveURL(/\/usulan-opt\/create$/);
    await expect(page.getByText(/Minimal satu foto bukti wajib dilampirkan/)).toBeVisible();
    await expect(page.getByLabel('Nama lokal/daerah')).toHaveValue(uniqueName);

    await page.getByLabel(/Foto bukti/).setInputFiles(
      path.resolve(__dirname, '../../backend/tests/fixtures/images/1x1.png'),
    );
    await expect(page.locator('#usulan_photo_preview img')).toHaveCount(1);

    await page.getByRole('button', { name: /Simpan Draf/ }).click();
    await expect(page).toHaveURL(/\/usulan-opt\/detail\/\d+$/);
    await expect(page.getByText(uniqueName, { exact: false })).toBeVisible();
    await expect(page.getByText(/Draf usulan OPT tersimpan/)).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Hapus Draf/ }).click();
    await expect(page).toHaveURL(/\/usulan-opt$/);
    await expect(page.getByText(/Draf usulan OPT berhasil dihapus/)).toBeVisible();
  });

  test('layout tetap berada di dalam viewport ponsel', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await expect(page.getByRole('button', { name: /Kirim untuk Review/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Simpan Draf/ })).toBeVisible();
  });

  test('layout tablet tanpa horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 820, height: 1180 });
    await page.reload();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await expect(page.getByLabel('Nama lokal/daerah')).toBeVisible();
  });

  test('guest dialihkan ke halaman login', async ({ browser }) => {
    const guestContext = await browser.newContext();
    const guestPage = await guestContext.newPage();
    await guestPage.goto(`${BASE}/usulan-opt/create`);
    await expect(guestPage).toHaveURL(/\/auth\/login$/);
    await guestContext.close();
  });

  test('kirim review sukses dengan foto menghasilkan status Menunggu Review', async ({ page }) => {
    const uniqueName = `E2E SUBMIT OPT ${Date.now()}`;
    await fillRequiredProposal(page, uniqueName);
    await page.getByLabel(/Foto bukti/).setInputFiles(
      path.resolve(__dirname, '../../backend/tests/fixtures/images/1x1.png'),
    );
    await expect(page.locator('#usulan_photo_preview img')).toHaveCount(1);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Kirim untuk Review/ }).click();

    await expect(page).toHaveURL(/\/usulan-opt\/detail\/\d+$/);
    await expect(page.getByText(/Usulan OPT terkirim dan menunggu review/)).toBeVisible();
    await expect(page.locator('.badge', { hasText: 'Menunggu Review' }).first()).toBeVisible();
    await expect(page.getByText(uniqueName)).toBeVisible();
  });

  test('payload XSS pada old input dirender aman tanpa eksekusi skrip', async ({ page }) => {
    const alertDialogs: string[] = [];
    page.on('dialog', (dialog) => {
      if (dialog.type() === 'alert') {
        alertDialogs.push(dialog.message());
      }
      return dialog.accept();
    });

    const xssName = `<script>window.__xss__=1</script>E2E XSS ${Date.now()}`;
    await fillRequiredProposal(page, xssName);
    await page.getByLabel('Ciri-ciri/gejala').fill('<img src=x onerror=window.__xss__=1> uji');

    await page.getByRole('button', { name: /Kirim untuk Review/ }).click();
    await expect(page).toHaveURL(/\/usulan-opt\/create$/);
    await expect(page.getByText(/Minimal satu foto bukti wajib dilampirkan/)).toBeVisible();
    await expect(page.getByLabel('Nama lokal/daerah')).toHaveValue(xssName);

    await page.waitForTimeout(500);
    expect(alertDialogs, 'Tidak boleh ada dialog alert dari payload XSS').toEqual([]);
    const injected = await page.evaluate(() => (window as unknown as { __xss__?: boolean }).__xss__ ?? false);
    expect(injected).toBe(false);
  });

  test('koordinat di luar rentang ditolak server dengan pesan aman', async ({ page }) => {
    await fillRequiredProposal(page, `E2E KOORDINAT ${Date.now()}`);
    await page.getByLabel('Latitude').fill('999');
    await page.getByLabel('Longitude').fill('-9999');

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Kirim untuk Review/ }).click();

    await expect(page).toHaveURL(/\/usulan-opt\/create$/);
    await expect(page.getByText(/Latitude harus di antara -90 dan 90/)).toBeVisible();
    await expect(page.getByText(/Longitude harus di antara -180 dan 180/)).toBeVisible();
  });

  test('POST langsung tanpa CSRF ditolak 403', async ({ page }) => {
    const response = await page.request.post(`${BASE}/usulan-opt/store`, {
      form: {
        intent: 'draft',
        nama_lokal: `E2E NOCSRF ${Date.now()}`,
        jenis: 'hama',
        komoditas: 'Padi',
        ciri_ciri: 'Uji CSRF',
        tanggal_ditemukan: '2026-08-20',
      },
    });
    expect(response.status()).toBe(403);
  });

  test('hierarki wilayah yang dipaksa tidak konsisten ditolak server', async ({ page }, testInfo) => {
    testInfo.setTimeout(90000);
    const uniqueName = `E2E HIERARKI ${Date.now()}`;
    await fillRequiredProposal(page, uniqueName);

    const kecamatanValue = await page.getByLabel('Kecamatan').inputValue();
    const desaValue = await page.getByLabel('Desa').inputValue();
    expect(Number(kecamatanValue)).toBeGreaterThan(0);
    expect(Number(desaValue)).toBeGreaterThan(0);

    // Cari desa milik kecamatan LAIN (kabupaten sama) melalui endpoint wilayah.
    const kecListResponse = await page.request.get(`${BASE}/wilayah/kecamatan/1`);
    const kecList = (await kecListResponse.json())?.data ?? [];
    const otherKec = kecList.find((k: { id: string }) => String(k.id) !== String(kecamatanValue));
    if (!otherKec) {
      testInfo.skip(true, 'Tidak ada kecamatan alternatif untuk uji hierarki.');
      return;
    }
    const desaOtherResponse = await page.request.get(`${BASE}/wilayah/desa/${otherKec.id}`);
    const desaOther = (await desaOtherResponse.json())?.data ?? [];
    const foreignDesaId = desaOther[0]?.id;
    if (!foreignDesaId) {
      testInfo.skip(true, 'Tidak ada desa pada kecamatan alternatif.');
      return;
    }

    // Paksa kombinasi kecamatan sah + desa lintas kecamatan lewat DOM.
    await page.evaluate(({ foreign }) => {
      const select = document.getElementById('desa_id') as HTMLSelectElement;
      const option = document.createElement('option');
      option.value = String(foreign);
      option.textContent = 'Desa lintas kecamatan (injeksi uji)';
      select.appendChild(option);
      select.value = String(foreign);
    }, { foreign: Number(foreignDesaId) });

    await page.getByLabel(/Foto bukti/).setInputFiles(
      path.resolve(__dirname, '../../backend/tests/fixtures/images/1x1.png'),
    );

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Kirim untuk Review/ }).click();

    await expect(page).toHaveURL(/\/usulan-opt\/create$/);
    await expect(
      page.getByText(/Desa tidak ditemukan atau bukan bagian dari kecamatan yang dipilih/),
    ).toBeVisible();
    void desaValue;
  });
});

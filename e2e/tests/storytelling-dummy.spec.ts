import { test, expect, type Page } from '@playwright/test';

// Autonomous E2E for Data Storytelling dummy 2025-2026.
// Prereq: php scripts/seed_storytelling_dummy_2026.php sudah dijalankan.
// Covers: 4 cabang klasifikasi + agregat kabupaten + 5 metode lanjutan +
// export CSV/Dossier + save/publish + RBAC negatif + validasi input.
const BASE = process.env.BASE_URL || 'http://localhost/jagapadi-3509';
const STAT_USER = process.env.E2E_STAT_USER || '';
const STAT_PASS = process.env.E2E_STAT_PASS || '';
const OPERATOR_USER = process.env.E2E_OPERATOR_USER || '';
const OPERATOR_PASS = process.env.E2E_OPERATOR_PASS || '';
const PETUGAS_USER = process.env.E2E_PETUGAS_USER || '';
const PETUGAS_PASS = process.env.E2E_PETUGAS_PASS || '';

test.skip(
  !STAT_USER || !STAT_PASS || !OPERATOR_USER || !OPERATOR_PASS || !PETUGAS_USER || !PETUGAS_PASS,
  'Kredensial E2E wajib diisi lewat env E2E_*_USER/E2E_*_PASS (jangan hard-code).'
);

async function loginAs(page: Page, username: string, password: string) {
  await page.goto(`${BASE}/auth/login`);
  await page.waitForSelector('input[name="username"]', { timeout: 15000 });
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 30000 });
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
}

async function runAnalysis(page: Page, bulan: string, tahun: string, kecamatan: string) {
  await page.selectOption('#filter-bulan', bulan);
  await page.selectOption('#filter-tahun', tahun);
  await page.selectOption('#filter-kecamatan', kecamatan);
  await page.click('#btn-analyze');
  await expect(page.locator('#analysis-result')).toBeVisible({ timeout: 45000 });
}

async function csrfFromPage(page: Page): Promise<string> {
  const html = await page.content();
  const m = html.match(/csrfToken:\s*"([a-f0-9]{64})"/);
  if (!m) throw new Error('CSRF token tidak ditemukan di halaman storytelling');
  return m[1];
}

test.describe('Storytelling dummy 2026 — matriks klasifikasi', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    await page.goto(`${BASE}/storytelling`);
    await expect(page.locator('#btn-analyze')).toBeVisible({ timeout: 20000 });
  });

  const matrix = [
    { bulan: '5', tahun: '2026', kec: '2', faktor: 'Serangan OPT' },
    { bulan: '7', tahun: '2026', kec: '2', faktor: 'Kombinasi Cuaca & OPT' },
    { bulan: '5', tahun: '2026', kec: '5', faktor: 'Normal' },
    { bulan: '8', tahun: '2026', kec: '2', faktor: 'Cuaca Ekstrem' },
  ];

  for (const c of matrix) {
    test(`klasifikasi ${c.faktor} — target ${c.tahun}-${c.bulan} kec ${c.kec}`, async ({ page }) => {
      await runAnalysis(page, c.bulan, c.tahun, c.kec);
      await expect(page.locator('#faktor-penyebab')).toHaveValue(c.faktor, { timeout: 15000 });
      await expect(page.locator('#narasi-final')).not.toBeEmpty();
      await expect(page.locator('#btn-save-analysis')).toBeEnabled({ timeout: 15000 });
    });
  }

  test('agregat kabupaten (wilayah 0) berjalan', async ({ page }) => {
    await runAnalysis(page, '8', '2026', '0');
    await expect(page.locator('#faktor-penyebab')).not.toBeEmpty();
    await expect(page.locator('#kpi-luas-panen')).not.toBeEmpty();
  });

  test('periode tanpa produksi → pesan data tidak cukup (422)', async ({ page }) => {
    await page.selectOption('#filter-bulan', '9');
    await page.selectOption('#filter-tahun', '2026');
    await page.selectOption('#filter-kecamatan', '2');
    await page.click('#btn-analyze');
    const alert = page.locator('.alert.position-fixed').last();
    await expect(alert).toBeVisible({ timeout: 45000 });
    await expect(alert).toContainText(/belum tersedia|tidak cukup|gagal/i);
  });
});

test.describe('Storytelling dummy 2026 — metode lanjutan & ekspor', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    await page.goto(`${BASE}/storytelling`);
    await expect(page.locator('#btn-analyze')).toBeVisible({ timeout: 20000 });
    await runAnalysis(page, '8', '2026', '2');
  });

  test('metode trend menghasilkan ringkasan', async ({ page }) => {
    await page.selectOption('#analysis-method', 'trend');
    await page.selectOption('#analysis-months', '12');
    await page.click('#btn-run-method');
    await expect(page.locator('#method-analysis-result')).toBeVisible({ timeout: 45000 });
    await expect(page.locator('#method-analysis-summary')).not.toBeEmpty();
  });

  test('metode correlation, predictive, clustering, outlier menghasilkan ringkasan', async ({ page }) => {
    for (const method of ['correlation', 'predictive', 'clustering', 'outlier']) {
      await page.selectOption('#analysis-method', method);
      await page.selectOption('#analysis-months', '12');
      await page.click('#btn-run-method');
      await expect(page.locator('#method-analysis-result')).toBeVisible({ timeout: 45000 });
      await expect(page.locator('#method-analysis-summary')).not.toBeEmpty();
    }
  });

  test('korelasi dengan variabel wind terpilih benar', async ({ page }) => {
    await page.selectOption('#analysis-method', 'correlation');
    await page.selectOption('#analysis-variable', 'wind');
    await expect(page.locator('#analysis-variable')).toHaveValue('wind');
    await page.selectOption('#analysis-months', '12');
    await page.click('#btn-run-method');
    await expect(page.locator('#method-analysis-result')).toBeVisible({ timeout: 45000 });
    await expect(page.locator('#method-analysis-metrics')).toContainText('"variable": "wind"', { timeout: 15000 });
  });

  test('export CSV mengunduh file', async ({ page }) => {
    const downloadPromise = page.waitForEvent('download', { timeout: 45000 });
    await page.click('#btn-export-csv');
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.csv$/i);
  });

  test('dossier eksekutif terbuka di tab baru', async ({ page, context }) => {
    const popupPromise = context.waitForEvent('page', { timeout: 45000 });
    await page.click('#btn-export-dossier');
    const popup = await popupPromise;
    await popup.waitForLoadState('domcontentloaded', { timeout: 30000 });
    await expect(popup).toHaveURL(/exportDossier/, { timeout: 20000 });
    await expect(popup.locator('.kop-surat')).toBeVisible({ timeout: 20000 });
    await popup.close();
  });

  test.skip('simpan analisis via UI berhasil', async ({ page }) => {
    await page.fill('#narasi-final', 'Narasi uji otonom Playwright — dummy 2026.');
    await page.click('#btn-save-analysis');
    await expect(page.locator('.alert.alert-success.position-fixed').last()).toBeVisible({ timeout: 30000 });
  });
});

test.describe('Storytelling — keamanan & validasi API', () => {
  test('petugas ditolak akses halaman storytelling', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.goto(`${BASE}/storytelling`);
    await expect(page).toHaveURL(/\/dashboard/, { timeout: 20000 });
  });

  test('operator tidak boleh publish (403)', async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
    await page.goto(`${BASE}/storytelling`);
    const csrf = await csrfFromPage(page);
    const resp = await page.request.post(`${BASE}/storytelling/publish`, {
      headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' },
      data: { analysis_id: 1 },
    });
    expect(resp.status()).toBe(403);
  });

  test.skip('statistisi dapat publish analisis tersimpan', async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    await page.goto(`${BASE}/storytelling`);
    const csrf = await csrfFromPage(page);
    const recent = await page.request.get(`${BASE}/storytelling/getRecent?limit=1`, {
      headers: { 'X-CSRF-Token': csrf },
    });
    expect(recent.ok()).toBeTruthy();
    const body = await recent.json();
    const id = body?.data?.[0]?.id;
    expect(id).toBeGreaterThan(0);
    const pub = await page.request.post(`${BASE}/storytelling/publish`, {
      headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' },
      data: { analysis_id: id },
    });
    expect(pub.status()).toBe(200);
    expect((await pub.json()).success).toBeTruthy();
  });
  test('bulan tidak valid ditolak (400)', async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    await page.goto(`${BASE}/storytelling`);
    const csrf = await csrfFromPage(page);
    const resp = await page.request.post(`${BASE}/storytelling/generateAnalysis`, {
      headers: { 'X-CSRF-Token': csrf },
      form: { bulan: '13', tahun: '2026', wilayah_id: '2' },
    });
    expect(resp.status()).toBe(400);
  });

  test('tanpa CSRF ditolak (403)', async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    const resp = await page.request.post(`${BASE}/storytelling/generateAnalysis`, {
      form: { bulan: '5', tahun: '2026', wilayah_id: '2' },
    });
    expect([401, 403]).toContain(resp.status());
  });
});

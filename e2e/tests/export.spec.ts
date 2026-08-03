import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';

const BASE = 'http://localhost/jagapadi-3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAsAdmin(page) {
  await page.goto(`${BASE}/auth/login`);
  await page.fill('input[name="username"]', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
  }
}

test.describe('Export Laporan', () => {
  test('admin can download CSV export of laporan hama', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/export`);

    await page.locator('input[name="jenis"][value="hama"]').check();
    await page.locator('input[name="format"][value="csv"]').check();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Unduh' }).click(),
    ]);

    const suggested = download.suggestedFilename();
    expect(suggested).toMatch(/laporan-hama.*\.csv$/i);

    const path = await download.path();
    const content = readFileSync(path, 'utf-8');

    // Header row present (UTF-8 BOM + header)
    expect(content).toContain('Nomor Laporan');
    expect(content).toContain('Status');
    // Contains at least the header and one data row
    const lines = content.split('\n').filter((l) => l.trim() !== '');
    expect(lines.length).toBeGreaterThanOrEqual(2);
  });

  test('admin can download XLSX export of laporan irigasi', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/export`);

    await page.locator('input[name="jenis"][value="irigasi"]').check();
    await page.locator('input[name="format"][value="xlsx"]').check();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Unduh' }).click(),
    ]);

    const suggested = download.suggestedFilename();
    expect(suggested).toMatch(/laporan-irigasi.*\.xlsx$/i);

    const path = await download.path();
    const buf = readFileSync(path);
    // XLSX is a ZIP: starts with PK\x03\x04
    expect(buf.slice(0, 2).toString('latin1')).toBe('PK');
  });

  test('export rejects invalid date range via form', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/export`);

    await page.locator('input[name="jenis"][value="hama"]').check();
    await page.fill('input[name="tanggal_dari"]', '2024-01-01');
    await page.fill('input[name="tanggal_sampai"]', '2026-06-01');

    // Submit; backend re-renders the form with a flash error (no download).
    await page.getByRole('button', { name: 'Unduh' }).click();
    await page.waitForLoadState('networkidle');
    // Should remain on /export with an error message, not trigger a download.
    await expect(page).toHaveURL(/\/export/);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/rentang|tanggal|maksimal/i);
  });
});



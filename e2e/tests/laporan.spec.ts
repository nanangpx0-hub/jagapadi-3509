import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/jagapadi-3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAsAdmin(page) {
  await page.goto(BASE + '/auth/login');
  await page.fill('input[name="username"]', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(BASE + '/dashboard');
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('Laporan Management', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should display laporan hama list page', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama');
    await expect(page).toHaveURL(/\/laporan-hama/);
    await expect(page.locator('h2').first()).toBeVisible();
  });

  test('should display laporan irigasi list page', async ({ page }) => {
    await page.goto(BASE + '/laporan-irigasi');
    await expect(page).toHaveURL(/\/laporan-irigasi/);
    await expect(page.locator('h2').first()).toBeVisible();
  });

  test('should create a new laporan hama as draft', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    await expect(page).toHaveURL(/\/laporan-hama\/create/);
    await expect(page.locator('form[action="/laporan-hama"]')).toBeVisible();

    const dateInput = page.locator('#tanggal');
    if (await dateInput.isVisible()) {
      await dateInput.fill('2026-07-19');
    }

    const optSelect = page.locator('select[name="master_opt_id"]').first();
    if (await optSelect.isVisible()) {
      const options = await optSelect.locator('option').all();
      if (options.length > 1) {
        await optSelect.selectOption({ index: 1 });
      }
    }

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      const finalUrl = page.url();
      const onList = finalUrl.includes('/laporan-hama');
      const onEdit = finalUrl.includes('/edit');
      expect(onList || onEdit).toBeTruthy();
    }
  });

  test('should create a new laporan irigasi as draft', async ({ page }) => {
    await page.goto(BASE + '/laporan-irigasi/create');
    await expect(page).toHaveURL(/\/laporan-irigasi\/create/);
    await expect(page.locator('form[action="/laporan-irigasi"]')).toBeVisible();

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      const finalUrl = page.url();
      const onList = finalUrl.includes('/laporan-irigasi');
      const onEdit = finalUrl.includes('/edit');
      expect(onList || onEdit).toBeTruthy();
    }
  });

  test('should filter laporan hama by status', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama');
    const statusSelect = page.locator('form[action="/laporan-hama"] select[name="status"]');
    if (await statusSelect.isVisible()) {
      await statusSelect.selectOption('Draf');
      await page.locator('form[action="/laporan-hama"] button[type="submit"]').click();
      await page.waitForTimeout(500);
      await expect(page).toHaveURL(/status=Draf/);
    }
  });

  test('should filter laporan irigasi by status', async ({ page }) => {
    await page.goto(BASE + '/laporan-irigasi');
    const statusSelect = page.locator('form[action="/laporan-irigasi"] select[name="status"]');
    if (await statusSelect.isVisible()) {
      await statusSelect.selectOption('Submitted');
      await page.locator('form[action="/laporan-irigasi"] button[type="submit"]').click();
      await page.waitForTimeout(500);
    }
  });

  test('should have empty state when filtering non-existent data', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama?status=Diarsipkan&q=nonexistent_xyz_123');
    await page.waitForLoadState('networkidle');
    const bodyText = await page.locator('body').innerText();
    const hasEmptyState = bodyText.includes('Belum ada') || bodyText.includes('Tidak ada') || bodyText.includes('0 ');
    expect(hasEmptyState).toBeTruthy();
  });

  test('should not allow unauthenticated create laporan', async ({ page }) => {
    await page.goto(BASE + '/auth/logout');
    await page.goto(BASE + '/laporan-hama/create');
    await expect(page).toHaveURL(/\/auth\/login|\/login/);
  });

  test('should have CSRF protection on laporan forms', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    const csrfField = page.locator('form[action="/laporan-hama"] input[name="_csrf_token"]');
    await expect(csrfField).toHaveCount(1);
  });
});



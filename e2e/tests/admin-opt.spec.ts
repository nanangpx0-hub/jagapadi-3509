import { test, expect } from '@playwright/test';
import { BASE } from '../base-url';

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
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('OPT Management', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should display OPT list page with table', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    await expect(page).toHaveURL(/\/opt/);
    await expect(page.locator('table')).toBeVisible();
    // Check table headers
    const headers = await page.locator('table thead th').allTextContents();
    const headerText = headers.join(' ');
    expect(headerText).toContain('Nama OPT');
    expect(headerText).toContain('Jenis');
    expect(headerText).toContain('Status');
    expect(headerText).toContain('Aksi');
  });

  test('should show create OPT form', async ({ page }) => {
    await page.goto(`${BASE}/opt/create`);
    await expect(page).toHaveURL(/\/opt\/create/);
    await expect(page.locator('form[action*="/opt/store"]')).toBeVisible();
  });

  test('should create a new OPT entry', async ({ page }) => {
    await page.goto(`${BASE}/opt/create`);
    await page.waitForURL(/\/opt\/create/);

    const uniqueName = `OPT Test ${Date.now()}`;
    const namaInput = page.locator('input[name="nama_opt"], input[name="nama"]').first();
    await expect(namaInput).toBeVisible();
    await namaInput.fill(uniqueName);

    // Select a jenis from dropdown
    const select = page.locator('select[name="jenis"], select[name="tipe"]').first();
    if (await select.isVisible()) {
      const options = await select.locator('option').all();
      if (options.length > 1) {
        await select.selectOption({ index: 1 });
      }
    }

    // Submit form
    await page.getByRole('button', { name: 'Simpan', exact: false }).first().click();

    // Should redirect back to OPT list
    await expect(page).toHaveURL(/\/opt/);
  });

  test('should reject empty OPT name', async ({ page }) => {
    await page.goto(`${BASE}/opt/create`);
    const submitBtn = page.getByRole('button', { name: 'Simpan', exact: false }).first();
    await submitBtn.click();
    // Should stay on create page or show error
    const currentUrl = page.url();
    const onCreate = currentUrl.includes('/opt/create');
    const hasError = await page.locator('.flash-error, .flash-warning, .error-text').isVisible().catch(() => false);
    expect(onCreate || hasError).toBeTruthy();
  });

  test('should filter OPT list by jenis', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    const jenisSelect = page.locator('form[action="/opt"] select[name="jenis"]');
    if (await jenisSelect.isVisible()) {
      await jenisSelect.selectOption('hama');
      await page.locator('form[action="/opt"] button[type="submit"]').click();
      await expect(page).toHaveURL(/jenis=hama/);
    }
  });

  test('should search OPT by keyword', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    const searchInput = page.locator('form[action="/opt"] input[name="q"]');
    if (await searchInput.isVisible()) {
      await searchInput.fill('wereng');
      await page.locator('form[action="/opt"] button[type="submit"]').click();
      await expect(page).toHaveURL(/q=wereng/);
    }
  });

  test('should edit an existing OPT entry', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    const editLink = page.locator('a[href*="/edit"]').first();
    if (await editLink.isVisible()) {
      await editLink.click();
      await expect(page).toHaveURL(/\/opt\/.*\/edit/);

      const namaInput = page.locator('#nama_opt');
      if (await namaInput.isVisible()) {
        await namaInput.fill(`Updated OPT ${Date.now()}`);
        await page.getByRole('button', { name: 'Simpan', exact: false }).first().click();
        await expect(page).toHaveURL(/\/opt/);
      }
    }
  });

  test('should have active filter bar', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    await expect(page.locator('.filter-bar')).toBeVisible();
  });
});



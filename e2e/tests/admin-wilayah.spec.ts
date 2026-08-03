import { test, expect } from '@playwright/test';

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
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('Wilayah Management', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should display Wilayah page with tab navigation', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    await expect(page).toHaveURL(/\/wilayah/);
    await expect(page.locator('.wilayah-nav')).toBeVisible();
    const tabs = await page.locator('.wilayah-nav a').allTextContents();
    const tabText = tabs.join(' ');
    expect(tabText).toContain('Kabupaten');
    expect(tabText).toContain('Kecamatan');
    expect(tabText).toContain('Desa');
  });

  test('should show kabupaten tab by default with table', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    const kabTable = page.locator('#tab-kabupaten table');
    await expect(kabTable).toBeVisible();
    const headers = await kabTable.locator('thead th').allTextContents();
    expect(headers.join(' ')).toContain('Kode');
    expect(headers.join(' ')).toContain('Nama Kabupaten');
  });

  test('should have create kabupaten button', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    const addBtn = page.locator('a[href="/wilayah/kabupaten/create"]');
    await expect(addBtn).toBeVisible();
    await expect(addBtn).toContainText('Tambah');
  });

  test('should navigate to kabupaten create form', async ({ page }) => {
    await page.goto(`${BASE}/wilayah/kabupaten/create`);
    await expect(page).toHaveURL(/\/wilayah\/kabupaten\/create/);
    await expect(page.locator('form[action*="/wilayah/kabupaten/store"]')).toBeVisible();
  });

  test('should create a new kabupaten', async ({ page }) => {
    await page.goto(`${BASE}/wilayah/kabupaten/create`);
    const nameInput = page.locator('input[name="nama_kabupaten"], input[name="nama"]').first();
    await expect(nameInput).toBeVisible();
    const uniqueName = `Kab Test ${Date.now()}`;
    await nameInput.fill(uniqueName);

    const submitBtn = page.getByRole('button', { name: 'Simpan', exact: false }).first();
    await submitBtn.click();

    // Should redirect to wilayah index
    await expect(page).toHaveURL(/\/wilayah/);
  });

  test('should reject empty kabupaten name', async ({ page }) => {
    await page.goto(`${BASE}/wilayah/kabupaten/create`);
    await page.getByRole('button', { name: 'Simpan', exact: false }).first().click();
    const currentUrl = page.url();
    const onForm = currentUrl.includes('/kabupaten/create');
    const hasError = await page.locator('.flash-error, .flash-warning').isVisible().catch(() => false);
    expect(onForm || hasError).toBeTruthy();
  });

  test('should navigate to kecamatan create form', async ({ page }) => {
    await page.goto(`${BASE}/wilayah/kecamatan/create`);
    await expect(page).toHaveURL(/\/wilayah\/kecamatan\/create/);
    await expect(page.locator('form[action*="/wilayah/kecamatan/store"]')).toBeVisible();
  });

  test('should navigate to desa create form', async ({ page }) => {
    await page.goto(`${BASE}/wilayah/desa/create`);
    await expect(page).toHaveURL(/\/wilayah\/desa\/create/);
    await expect(page.locator('form[action*="/wilayah/desa/store"]')).toBeVisible();
  });

  test('should switch between wilayah tabs', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    // Click Kecamatan tab
    await page.locator('.wilayah-nav a:has-text("Kecamatan")').click();
    await expect(page.locator('#tab-kecamatan')).toHaveClass(/active/);
    await expect(page.locator('#tab-kabupaten')).not.toHaveClass(/active/);
    // Click Desa tab
    await page.locator('.wilayah-nav a:has-text("Desa")').click();
    await expect(page.locator('#tab-desa')).toHaveClass(/active/);
  });

  test('should edit existing kabupaten', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    const editLink = page.locator('a[href*="/kabupaten/edit/"]').first();
    if (await editLink.isVisible()) {
      await editLink.click();
      await expect(page).toHaveURL(/\/kabupaten\/edit\//);
      const nameInput = page.locator('#nama_kabupaten');
      if (await nameInput.isVisible()) {
        await nameInput.fill(`Updated ${Date.now()}`);
        await page.locator('form[action*="/kabupaten/update/"] button[type="submit"]').click();
        await expect(page).toHaveURL(/\/wilayah/);
      }
    }
  });

  test('should have CSRF token in delete forms', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    const deleteForm = page.locator('form[action*="/delete"]').first();
    const deleteFormCount = await deleteForm.count();
    if (deleteFormCount > 0 && await deleteForm.isVisible()) {
      const csrfInput = deleteForm.locator('input[name="_csrf_token"], input[name="csrf_token"]');
      await expect(csrfInput).toHaveCount(1);
    }
  });
});



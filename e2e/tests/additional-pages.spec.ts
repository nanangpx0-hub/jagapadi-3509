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

test.describe('Additional Pages', () => {
  test('password change page should have required form', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(BASE + '/password/change');
    await expect(page).toHaveURL(/\/password\/change/);
    await expect(page.locator('h2')).toContainText('Ganti Password');
    await expect(page.locator('#current_password')).toBeVisible();
    await expect(page.locator('#new_password')).toBeVisible();
    await expect(page.locator('#new_password_confirmation')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Simpan Password' })).toBeVisible();
  });

  test('password change validates required fields', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(BASE + '/password/change');
    await page.getByRole('button', { name: 'Simpan Password' }).click();
    // HTML5 validation should prevent submission — check we stay on page
    await expect(page).toHaveURL(/\/password\/change/);
  });

  test('notifications page should load', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(BASE + '/notifications');
    await expect(page).toHaveURL(/\/notifications/);
    await expect(page.locator('h2').first()).toContainText('Notifikasi');
  });

  test('export page should have form with filters', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(BASE + '/export');
    await expect(page).toHaveURL(/\/export/);
    await expect(page.locator('h2')).toContainText('Ekspor Data Laporan');
    // Check form elements
    await expect(page.locator('input[name="jenis"][value="hama"]')).toBeChecked();
    await expect(page.locator('input[name="format"][value="csv"]')).toBeChecked();
    await expect(page.locator('#kabupaten')).toBeVisible();
    await expect(page.locator('input[name="tanggal_dari"]')).toBeVisible();
    await expect(page.locator('input[name="tanggal_sampai"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Unduh' })).toBeVisible();
  });

  test('unauthenticated users cannot access protected pages', async ({ page }) => {
    const protectedRoutes = ['/password/change', '/notifications', '/export'];
    for (const route of protectedRoutes) {
      await page.goto(BASE + route);
      await expect(page, `Route ${route} should redirect to login`).toHaveURL(/\/login/);
    }
  });
});



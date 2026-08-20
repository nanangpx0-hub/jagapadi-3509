import { test, expect } from '@playwright/test';
import { BASE } from '../base-url';

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'Jember3509';

async function loginAs(page, username, password) {
  await page.goto(`${BASE}/auth/login`);
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(dashboard|change_password)/, { timeout: 15000 });
  if (page.url().includes('change_password')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
}

test.describe('Authentication', () => {
  test('should display login page correctly', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await expect(page.locator('.login-logo b')).toBeVisible();
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('should show error on invalid credentials', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', 'invalid_user');
    await page.fill('#password', 'wrong_password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.alert-danger, .alert-warning, .alert')).toBeVisible();
  });

  test('should login successfully with admin credentials', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test.describe('Authenticated as admin', () => {
    test.use({ storageState: 'auth/admin.json' });

    test('should show username in navbar after login', async ({ page }) => {
      await page.goto(`${BASE}/dashboard`);
      await expect(page.locator('.user-panel .info a')).toBeVisible();
      await expect(page.locator('.user-panel')).toContainText(/admin/i);
    });

    test('should logout successfully and handle session timeout', async ({ page }) => {
      await page.goto(`${BASE}/dashboard`);
      await page.locator('form[action*="auth/logout"] button[type="submit"]').click();
      await expect(page).toHaveURL(/\/auth\/login/);
      await page.goto(`${BASE}/dashboard`);
      await expect(page).toHaveURL(/\/auth\/login/);
    });
  });

  test('should redirect to login when accessing protected page', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/auth\/login/);
  });

  test('should require CSRF token for login', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.evaluate(() => {
      const tokenInput = document.querySelector('input[name="csrf_token"]');
      if (tokenInput) tokenInput.remove();
    });
    await page.fill('input[name="username"]', ADMIN_USER);
    await page.fill('#password', ADMIN_PASS);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/auth\/login/);
  });

  test('should not allow access to admin page as unauthenticated', async ({ page }) => {
    const routes = ['/opt', '/laporan', '/irigasi'];
    for (const route of routes) {
      await page.goto(`${BASE}${route}`);
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveURL(/\/auth\/login/);
    }
  });

  test('should login successfully with petugas credentials', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });
});

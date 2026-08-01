import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'Jember3509';

async function loginAs(page, username, password) {
  await page.goto(`${BASE}/login`);
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  // Skip password change if redirected
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('Authentication', () => {
  test('should display login page correctly', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await expect(page.locator('h1, .auth-logo h1, .navbar-brand')).toBeVisible();
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Login' })).toBeVisible();
  });

  test('should show error on invalid credentials', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('#username', 'invalid_user');
    await page.fill('#password', 'wrong_password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.flash-error, .flash-warning, .flash-message')).toBeVisible();
  });

  test('should login successfully with admin credentials', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1')).toContainText('Dashboard');
  });

  test('should show username in navbar after login', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await expect(page.locator('.navbar-user')).toContainText('admin');
  });

  test('should logout successfully', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await page.locator('form[action="/logout"] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('should redirect to login when accessing protected page', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/login/);
  });

  test('should require CSRF token for login', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      const tokenInput = document.querySelector('input[name="_csrf_token"], input[name="csrf_token"]');
      if (tokenInput) tokenInput.remove();
    });
    await page.fill('#username', ADMIN_USER);
    await page.fill('#password', ADMIN_PASS);
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('should not allow access to admin page as unauthenticated', async ({ page }) => {
    const routes = ['/opt', '/wilayah', '/laporan-hama', '/laporan-irigasi'];
    for (const route of routes) {
      await page.goto(`${BASE}${route}`);
      await expect(page, `Route ${route} should redirect to login`).toHaveURL(/\/login/);
    }
  });

  test('should handle session timeout gracefully', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    // Clear session by navigating to logout
    await page.goto(`${BASE}/logout`);
    await expect(page).toHaveURL(/\/login/);
    // Try accessing protected page
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/login/);
  });

  test('should login successfully with petugas credentials', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });
});

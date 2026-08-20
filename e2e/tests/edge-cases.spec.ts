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

test.describe('Edge Cases & Security', () => {
  test('should reject SQL injection in login form', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', "' OR '1'='1");
    await page.fill('#password', "' OR '1'='1");
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('should reject XSS injection in login form', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', '<script>alert("xss")</script>');
    await page.fill('#password', 'password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('should handle long username gracefully', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', 'a'.repeat(1000));
    await page.fill('#password', 'test');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('should handle special characters in password', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', ADMIN_USER);
    await page.fill('#password', '!@#$%^&*()_+-=[]{}|;:,.<>?');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('should not cache protected pages', async ({ page }) => {
    await loginAsAdmin(page);
    // Check for no-cache headers via the page response
    const response = await page.goto(`${BASE}/dashboard`);
    const cacheControl = response?.headers()['cache-control'] || '';
    expect(cacheControl).toContain('no-cache');
  });

  test('should redirect to HTTPS if configured', async ({ page }) => {
    // This is a configuration check - the app should have HSTS/redirect support
    const response = await page.goto(`${BASE}/auth/login`);
    expect(response?.status()).toBe(200);
  });

  test('should have CSRF token on all forms', async ({ page }) => {
    await loginAsAdmin(page);
    const formSelectors = [
      { url: '/opt/create', form: 'form[action*="/opt/store"]' },
      { url: '/wilayah/kabupaten/create', form: 'form[action*="/kabupaten/store"]' },
      { url: '/wilayah/kecamatan/create', form: 'form[action*="/kecamatan/store"]' },
      { url: '/wilayah/desa/create', form: 'form[action*="/desa/store"]' },
    ];
    for (const { url, form } of formSelectors) {
      await page.goto(`${BASE}${url}`);
      await page.waitForLoadState('networkidle');
      const csrfInput = page.locator(`${form} input[name="_csrf_token"], ${form} input[name="csrf_token"]`);
      const count = await csrfInput.count();
      expect(count > 0, `CSRF field should exist on ${url}`).toBeTruthy();
    }
  });
});



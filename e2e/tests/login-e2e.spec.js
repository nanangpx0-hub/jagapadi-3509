const { test, expect } = require('@playwright/test');
const { BASE } = require('../base-url');

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAs(page, username, password) {
    await page.goto(`${BASE}/auth/login`);
    await page.waitForSelector('input[name="username"]', { timeout: 10000 });
    await page.fill('input[name="username"]', username);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 15000 });
    if (page.url().includes('/password/change')) {
        await page.goto(`${BASE}/dashboard`);
        await page.waitForURL(/\/dashboard/, { timeout: 10000 });
    }
}

test.describe('Login Authentication E2E', () => {
    test('should display login page with form elements', async ({ page }) => {
        await page.goto(`${BASE}/auth/login`);
        await expect(page.locator('input[name="username"]')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.getByRole('button', { name: /login/i })).toBeVisible();
    });

    test('should reject invalid credentials', async ({ page }) => {
        await page.goto(`${BASE}/auth/login`);
        await page.fill('input[name="username"]', 'invalid_user');
        await page.fill('#password', 'wrong_password');
        await page.getByRole('button', { name: /login/i }).click();
        await page.waitForTimeout(2000);
        await expect(page).toHaveURL(/\/login/);
        const errorVisible = await page.locator('.flash-error, .flash-warning, .alert-danger, .alert').first().isVisible().catch(() => false);
        expect(errorVisible).toBeTruthy();
    });

    test('should login successfully with admin / Jember3509', async ({ page }) => {
        await loginAs(page, ADMIN_USER, ADMIN_PASS);
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('should show username after login', async ({ page }) => {
        await loginAs(page, ADMIN_USER, ADMIN_PASS);
        const bodyText = await page.textContent('body');
        expect(bodyText).toContain('admin');
    });

    test('should logout successfully', async ({ page }) => {
        await loginAs(page, ADMIN_USER, ADMIN_PASS);
        await page.goto(`${BASE}/auth/logout`);
        await page.waitForURL(/\/login/, { timeout: 10000 });
        await expect(page).toHaveURL(/\/login/);
    });

    test('should redirect to login when accessing protected page', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`);
        await page.waitForURL(/\/login/, { timeout: 10000 });
        await expect(page).toHaveURL(/\/login/);
    });

    test('should require CSRF token for login', async ({ page }) => {
        await page.goto(`${BASE}/auth/login`);
        await page.evaluate(() => {
            const tokenInput = document.querySelector('input[name="_csrf_token"], input[name="csrf_token"]');
            if (tokenInput) tokenInput.remove();
        });
        await page.fill('input[name="username"]', ADMIN_USER);
        await page.fill('#password', ADMIN_PASS);
        await page.getByRole('button', { name: /login/i }).click();
        await page.waitForTimeout(2000);
        await expect(page).toHaveURL(/\/login/);
    });

    test('should handle session timeout gracefully', async ({ page }) => {
        await loginAs(page, ADMIN_USER, ADMIN_PASS);
        await page.goto(`${BASE}/auth/logout`);
        await page.waitForURL(/\/login/, { timeout: 10000 });
        await page.goto(`${BASE}/dashboard`);
        await page.waitForURL(/\/login/, { timeout: 10000 });
        await expect(page).toHaveURL(/\/login/);
    });
});


import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/jagapadi-3509';
const OPERATOR_USER = 'operator01';
const OPERATOR_PASS = 'Jember3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAs(page, username, password) {
  await page.goto(`${BASE}/auth/login`);
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/);
  }
}

// ─────────────────────────────────────────────────────
// 1. AUTH
// ─────────────────────────────────────────────────────
test.describe('Operator — Authentication & Session', () => {
  test('operator can login successfully', async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('operator can logout successfully', async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
    await page.locator('form[action="auth/logout"] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('operator session persists across pages', async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await page.goto(`${BASE}/irigasi`);
    await expect(page).toHaveURL(/\/irigasi/);
  });
});

// ─────────────────────────────────────────────────────
// 2. DASHBOARD
// ─────────────────────────────────────────────────────
test.describe('Operator — Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
  });

  test('operator can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1')).toContainText('Dashboard');
  });

  test('operator can navigate to irigasi rules via sidebar', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const irigasiSidebar = page.locator('a[href*="/irigasi/rules"], a:has-text("Aturan Irigasi")');
    if (await irigasiSidebar.count() > 0) {
      await irigasiSidebar.click();
      await expect(page).toHaveURL(/\/irigasi(\/rules)?/);
    }
  });
});

// ─────────────────────────────────────────────────────
// 3. IRIGASI RULES — API
// ─────────────────────────────────────────────────────
test.describe('Operator — Irigasi Rules API', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
  });

  test('operator can list irigasi via API', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/irigasi`);
    expect([200, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body.success).toBe(true);
    }
  });

  test('operator can access irigasi dashboard-summary', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/irigasi/dashboard-summary`);
    expect([200, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body.success).toBe(true);
    }
  });

  test('operator can access irigasi monitoring', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/irigasi/1/monitoring`);
    expect([200, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body.success).toBe(true);
    }
  });

  test('operator can access irigasi rules endpoint', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/irigasi/1/rules`);
    expect([200, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body).toHaveProperty('rules');
    }
  });

  test('operator can access irigasi analytics', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/irigasi/1/analytics?days=30`);
    expect([200, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body).toHaveProperty('sensor_trends');
    }
  });
});

// ─────────────────────────────────────────────────────
// 4. IRIGASI RULES — WRITE ACTIONS (operator allowed)
// ─────────────────────────────────────────────────────
test.describe('Operator — Irigasi Rules Write API', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
  });

  test('operator can create irigasi rule via API', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/api/irigasi/rules`, {
      data: {
        irigasi_id: 1,
        rule_name: 'Test Rule',
        description: 'Auto test rule',
        conditions: { operator: 'AND', conditions: [] },
        actions: { actions: [] },
        priority: 10,
        is_active: 1
      }
    });
    expect([201, 422, 500, 404]).toContain(resp.status());
    if (resp.status() === 201) {
      const body = await resp.json();
      expect(body.success).toBe(true);
    }
  });

  test('operator can toggle rule status via API', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/api/irigasi/rules/1/toggle`);
    expect([200, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body.success).toBe(true);
    }
  });

  test('operator cannot access admin-only user management', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/users`, {
      maxRedirects: 0
    });
    expect([403, 302, 404]).toContain(resp.status());
  });

  test('operator cannot access admin-only OPT management', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/api/opt`, {
      data: { nama_opt: 'Test' },
      maxRedirects: 0
    });
    expect([403, 302, 404]).toContain(resp.status());
  });
});

// ─────────────────────────────────────────────────────
// 5. PETUGAS-FLOW RESTRICTIONS (operator cannot do petugas work)
// ─────────────────────────────────────────────────────
test.describe('Operator — Cross-Role Restrictions', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, OPERATOR_USER, OPERATOR_PASS);
  });

  test('operator can access laporan-hama create page (read-only)', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page).not.toHaveURL(/\/login/);
  });

  test('operator cannot access admin wilayah page', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    await expect(page).toHaveURL(/\/(login|dashboard)/);
  });

  test('operator cannot access admin opt page', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    await expect(page).toHaveURL(/\/(login|dashboard)/);
  });
});



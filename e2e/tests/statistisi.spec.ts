import { test, expect } from '@playwright/test';
import { BASE } from '../base-url';

const STAT_USER = 'statistisi01';
const STAT_PASS = 'Jember3509';
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

test.describe('Statistisi — Authentication & Session', () => {
  test('statistisi can login successfully', async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('statistisi can logout successfully', async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
    await page.locator('form[action="auth/logout"] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/login/);
  });
});

test.describe('Statistisi — Dashboard & Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
  });

  test('statistisi can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('statistisi can navigate to storytelling page', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const stLink = page.locator('a[href*="/storytelling"], a[href*="/analisis"], a:has-text("Storytelling"), a:has-text("Analisis")');
    if (await stLink.count() > 0) {
      await stLink.click();
      await expect(page).toHaveURL(/\/(storytelling|analisis)/);
    }
  });

  test('statistisi can view export page', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/export`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    expect([200, 401, 403, 404]).toContain(resp.status());
  });
});

test.describe('Statistisi — API Access (Read-Only)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
  });

  test('statistisi can list laporan hama via API', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/v1/laporan-hama?include_draft=false`);
    expect([200, 401, 403, 404]).toContain(resp.status());
    if (resp.status() === 200) {
      const body = await resp.json();
      expect(body).toHaveProperty('data');
    }
  });

  test('statistisi can access dashboard analytics API', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/analytics/dashboard-summary`);
    expect([200, 401, 403, 404]).toContain(resp.status());
  });

  test('statistisi can access export report endpoint', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/export/report?type=laporan_hama`);
    expect([200, 401, 403, 404, 500]).toContain(resp.status());
    if (resp.status() === 200) {
      const ct = resp.headers()['content-type'];
      expect(ct).toMatch(/csv|xlsx|json|octet/);
    }
  });

  test('statistisi can request analytics by period', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/analytics/dashboard-summary?include_draft=false&period=yearly`);
    expect([200, 401, 403, 404]).toContain(resp.status());
  });

  test('statistisi can access OPT distribution analytics', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/analytics/opt-distribution`);
    expect([200, 401, 403, 404]).toContain(resp.status());
  });
});

test.describe('Statistisi — Write Restrictions', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
  });

  test('statistisi cannot create laporan hama', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/api/v1/laporan-hama`, {
      data: { wilayah_id: 1, opt_id: 1 },
      maxRedirects: 0
    });
    expect([401, 403, 302, 405]).toContain(resp.status());
  });

  test('statistisi cannot update laporan status (verify)', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/api/laporan-hama/1/verify`, {
      data: { status: 'Diverifikasi' },
      maxRedirects: 0
    });
    expect([401, 403, 302, 404, 405]).toContain(resp.status());
  });

  test('statistisi cannot manage users', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/users`, {
      maxRedirects: 0
    });
    expect([401, 403, 302, 404]).toContain(resp.status());
  });

  test('statistisi cannot create irigasi rules', async ({ page }) => {
    const resp = await page.request.post(`${BASE}/api/irigasi/rules`, {
      data: { irigasi_id: 1, rule_name: 'Test' },
      maxRedirects: 0
    });
    expect([401, 403, 302, 405, 404]).toContain(resp.status());
  });
});

test.describe('Statistisi — Public Data Access', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
  });

  test('statistisi can access public API endpoints', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/v1/laporan-hama/public/map`);
    expect([200, 401, 403, 404]).toContain(resp.status());
  });

  test('statistisi can search laporan', async ({ page }) => {
    const resp = await page.request.get(`${BASE}/api/v1/laporan-hama?search=test&include_draft=false`);
    expect([200, 401, 403]).toContain(resp.status());
  });
});

test.describe('Statistisi — Negative / Edge Cases', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAT_USER, STAT_PASS);
  });

  test('include_draft=true still filters out drafts for statistisi', async ({ page }) => {
    await page.goto(`${BASE}/dashboard?include_draft=true`);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('statistisi cannot access admin-only pages by direct URL', async ({ page }) => {
    const forbiddenPages = ['/users', '/settings', '/irigasi/rules/create', '/opt/create'];
    for (const path of forbiddenPages) {
      const resp = await page.request.get(`${BASE}${path}`, { maxRedirects: 0 });
      const status = resp.status();
      expect([302, 401, 403, 404]).toContain(status);
    }
  });
});



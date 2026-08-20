import { test, expect, type Page, type APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const VIEWER_USER = 'viewer01';
const VIEWER_PASS = 'Jember3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAs(page: Page, username: string, password: string) {
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 30000 });
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
}

async function loginAsApi(request: APIRequestContext, username: string, password: string): Promise<string> {
  const resp = await request.post(`${BASE}/api/v1/auth/login`, {
    data: { username, password },
  });
  const body = await resp.json();
  return body.data?.token || body.token || '';
}

// ─────────────────────────────────────────────────────
// 1. AUTHENTICATION
// ─────────────────────────────────────────────────────
test.describe('Viewer — Authentication', () => {
  test('viewer can login successfully', async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('viewer can logout successfully', async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
    await page.locator('form[action*="logout"] button[type="submit"], a[href*="logout"]').first().click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('viewer session persists across pages', async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('invalid credentials show error for viewer', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', VIEWER_USER);
    await page.fill('#password', 'wrong_password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });
});

// ─────────────────────────────────────────────────────
// 2. DASHBOARD
// ─────────────────────────────────────────────────────
test.describe('Viewer — Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
  });

  test('viewer can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1')).toContainText('Dashboard');
  });

  test('viewer dashboard does not show admin quick links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page.locator('a:has-text("Verifikasi Laporan Hama")')).toHaveCount(0);
    await expect(page.locator('a:has-text("Manajemen User")')).toHaveCount(0);
  });

  test('viewer dashboard does not show petugas create links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const createLinks = page.locator('a[href*="/laporan-hama/create"], a[href*="/laporan-irigasi/create"]');
    await expect(createLinks).toHaveCount(0);
  });
});

// ─────────────────────────────────────────────────────
// 3. LAPORAN HAMA — READ ONLY
// ─────────────────────────────────────────────────────
test.describe('Viewer — Laporan Hama Access', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
  });

  test('viewer can view laporan hama list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
  });

  test('viewer can view laporan hama detail', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-hama/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    }
  });

  test('viewer cannot create laporan hama via web', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const createBtn = page.locator('a[href*="/laporan-hama/create"], button:has-text("Buat Laporan")');
    if (await createBtn.count() > 0) {
      await createBtn.first().click();
      const url = page.url();
      expect(url).not.toMatch(/\/laporan-hama\/create/);
    }
  });
});

// ─────────────────────────────────────────────────────
// 4. LAPORAN IRIGASI — READ ONLY
// ─────────────────────────────────────────────────────
test.describe('Viewer — Laporan Irigasi Access', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
  });

  test('viewer can view laporan irigasi list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await expect(page).toHaveURL(/\/laporan-irigasi/);
  });

  test('viewer can view laporan irigasi detail', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-irigasi/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await expect(page).toHaveURL(/\/laporan-irigasi\/\d+/);
    }
  });
});

// ─────────────────────────────────────────────────────
// 5. ADMIN ROUTES — BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Viewer — Admin Routes Blocked', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
  });

  const adminRoutes = ['/admin', '/wilayah', '/opt', '/wilayah/kabupaten/create', '/opt/create'];

  for (const path of adminRoutes) {
    test(`viewer cannot access ${path}`, async ({ page }) => {
      await page.goto(`${BASE}${path}`);
      await expect(page).toHaveURL(/\/(dashboard|login)/);
    });
  }
});

// ─────────────────────────────────────────────────────
// 6. API — ACCESS CONTROL
// ─────────────────────────────────────────────────────
test.describe('Viewer — API Access Control', () => {
  let viewerToken = '';

  test.beforeAll(async ({ request }) => {
    viewerToken = await loginAsApi(request, VIEWER_USER, VIEWER_PASS);
    test.skip(!viewerToken, 'Could not obtain viewer JWT token');
  });

  test('viewer can call /api/v1/me', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: `Bearer ${viewerToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.role).toBe('viewer');
  });

  test('viewer can call /api/v1/dashboard/stats', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/stats`, {
      headers: { Authorization: `Bearer ${viewerToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('viewer can list laporan hama', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${viewerToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('viewer can list laporan irigasi', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-irigasi`, {
      headers: { Authorization: `Bearer ${viewerToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('viewer can access notifications API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/notifications`, {
      headers: { Authorization: `Bearer ${viewerToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('viewer cannot create laporan hama via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${viewerToken}`, 'Content-Type': 'application/json' },
      data: { action: 'draft', tanggal: '2026-08-15', master_opt_id: 1 },
    });
    expect(resp.status()).toBe(403);
  });

  test('viewer cannot verify laporan hama via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/laporan-hama/1/verifikasi`, {
      headers: { Authorization: `Bearer ${viewerToken}`, 'Content-Type': 'application/json' },
      data: {},
    });
    expect(resp.status()).toBe(403);
  });

  test('viewer cannot create wilayah via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/wilayah/kabupaten`, {
      headers: { Authorization: `Bearer ${viewerToken}`, 'Content-Type': 'application/json' },
      data: { nama_kabupaten: 'Test' },
    });
    expect(resp.status()).toBe(403);
  });

  test('viewer cannot create OPT via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/opt`, {
      headers: { Authorization: `Bearer ${viewerToken}`, 'Content-Type': 'application/json' },
      data: { nama_opt: 'Test', jenis: 'hama', etl_acuan: 0 },
    });
    expect(resp.status()).toBe(403);
  });
});

// ─────────────────────────────────────────────────────
// 7. NOTIFICATIONS
// ─────────────────────────────────────────────────────
test.describe('Viewer — Notifications', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
  });

  test('viewer can view notifications page', async ({ page }) => {
    await page.goto(`${BASE}/notifications`);
    await expect(page).toHaveURL(/\/notifications/);
  });
});

// ─────────────────────────────────────────────────────
// 8. EXPORT
// ─────────────────────────────────────────────────────
test.describe('Viewer — Export', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, VIEWER_USER, VIEWER_PASS);
  });

  test('viewer can access export page', async ({ page }) => {
    await page.goto(`${BASE}/export`);
    await expect(page).toHaveURL(/\/export/);
  });

  test('viewer can access export API', async ({ request }) => {
    const token = await loginAsApi(request, VIEWER_USER, VIEWER_PASS);
    const resp = await request.get(`${BASE}/api/v1/export/hama?format=csv`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 422]).toContain(resp.status());
  });
});

// ─────────────────────────────────────────────────────
// 9. JWT LIFECYCLE
// ─────────────────────────────────────────────────────
test.describe('Viewer — API JWT Lifecycle', () => {
  test('viewer can login via API and get token', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: VIEWER_USER, password: VIEWER_PASS },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.token || body.token).toBeTruthy();
  });

  test('viewer can refresh JWT token', async ({ request }) => {
    const token = await loginAsApi(request, VIEWER_USER, VIEWER_PASS);
    test.skip(!token, 'No token');
    const resp = await request.post(`${BASE}/api/v1/auth/refresh`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('viewer invalid token → 401', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: 'Bearer invalid.token.here' },
    });
    expect(resp.status()).toBe(401);
  });
});

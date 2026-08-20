import { test, expect, type Page, type APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const STATISTISI_USER = 'statistisi01';
const STATISTISI_PASS = 'Jember3509';
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
test.describe('Statistisi — Authentication', () => {
  test('statistisi can login successfully', async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('statistisi can logout successfully', async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
    await page.locator('form[action*="logout"] button[type="submit"], a[href*="logout"]').first().click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('statistisi session persists across pages', async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('invalid credentials show error for statistisi', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', STATISTISI_USER);
    await page.fill('#password', 'wrong_password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });
});

// ─────────────────────────────────────────────────────
// 2. DASHBOARD
// ─────────────────────────────────────────────────────
test.describe('Statistisi — Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
  });

  test('statistisi can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1')).toContainText('Dashboard');
  });

  test('statistisi dashboard does not show admin quick links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page.locator('a:has-text("Verifikasi Laporan Hama")')).toHaveCount(0);
    await expect(page.locator('a:has-text("Manajemen User")')).toHaveCount(0);
    await expect(page.locator('a[href="/wilayah"]')).toHaveCount(0);
    await expect(page.locator('a[href="/opt"]')).toHaveCount(0);
  });
});

// ─────────────────────────────────────────────────────
// 3. LAPORAN HAMA — READ ONLY
// ─────────────────────────────────────────────────────
test.describe('Statistisi — Laporan Hama Access', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
  });

  test('statistisi can view laporan hama list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
  });

  test('statistisi can view laporan hama detail', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-hama/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    }
  });

  test('statistisi cannot create laporan hama via web (no create button or form)', async ({ page }) => {
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
test.describe('Statistisi — Laporan Irigasi Access', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
  });

  test('statistisi can view laporan irigasi list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await expect(page).toHaveURL(/\/laporan-irigasi/);
  });

  test('statistisi can view laporan irigasi detail', async ({ page }) => {
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
test.describe('Statistisi — Admin Routes Blocked', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
  });

  const adminRoutes = ['/admin', '/wilayah', '/opt', '/wilayah/kabupaten/create', '/opt/create'];

  for (const path of adminRoutes) {
    test(`statistisi cannot access ${path}`, async ({ page }) => {
      await page.goto(`${BASE}${path}`);
      await expect(page).toHaveURL(/\/(dashboard|login)/);
    });
  }
});

// ─────────────────────────────────────────────────────
// 6. API — ACCESS CONTROL
// ─────────────────────────────────────────────────────
test.describe('Statistisi — API Access Control', () => {
  let statistisiToken = '';

  test.beforeAll(async ({ request }) => {
    statistisiToken = await loginAsApi(request, STATISTISI_USER, STATISTISI_PASS);
    test.skip(!statistisiToken, 'Could not obtain statistisi JWT token');
  });

  test('statistisi can call /api/v1/me', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: `Bearer ${statistisiToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.role).toBe('statistisi');
  });

  test('statistisi can call /api/v1/dashboard/stats', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/stats`, {
      headers: { Authorization: `Bearer ${statistisiToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('statistisi can list laporan hama', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${statistisiToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('statistisi can list laporan irigasi', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-irigasi`, {
      headers: { Authorization: `Bearer ${statistisiToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('statistisi can access notifications API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/notifications`, {
      headers: { Authorization: `Bearer ${statistisiToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('statistisi cannot create laporan hama via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${statistisiToken}`, 'Content-Type': 'application/json' },
      data: { action: 'draft', tanggal: '2026-08-15', master_opt_id: 1 },
    });
    expect(resp.status()).toBe(403);
  });

  test('statistisi cannot verify laporan hama via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/laporan-hama/1/verifikasi`, {
      headers: { Authorization: `Bearer ${statistisiToken}`, 'Content-Type': 'application/json' },
      data: {},
    });
    expect(resp.status()).toBe(403);
  });

  test('statistisi cannot create wilayah via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/wilayah/kabupaten`, {
      headers: { Authorization: `Bearer ${statistisiToken}`, 'Content-Type': 'application/json' },
      data: { nama_kabupaten: 'Test' },
    });
    expect(resp.status()).toBe(403);
  });

  test('statistisi cannot create OPT via API → 403', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/opt`, {
      headers: { Authorization: `Bearer ${statistisiToken}`, 'Content-Type': 'application/json' },
      data: { nama_opt: 'Test', jenis: 'hama', etl_acuan: 0 },
    });
    expect(resp.status()).toBe(403);
  });
});

// ─────────────────────────────────────────────────────
// 7. NOTIFICATIONS
// ─────────────────────────────────────────────────────
test.describe('Statistisi — Notifications', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
  });

  test('statistisi can view notifications page', async ({ page }) => {
    await page.goto(`${BASE}/notifications`);
    await expect(page).toHaveURL(/\/notifications/);
  });
});

// ─────────────────────────────────────────────────────
// 8. EXPORT
// ─────────────────────────────────────────────────────
test.describe('Statistisi — Export', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STATISTISI_USER, STATISTISI_PASS);
  });

  test('statistisi can access export page', async ({ page }) => {
    await page.goto(`${BASE}/export`);
    await expect(page).toHaveURL(/\/export/);
  });

  test('statistisi can access export API', async ({ request }) => {
    const token = await loginAsApi(request, STATISTISI_USER, STATISTISI_PASS);
    const resp = await request.get(`${BASE}/api/v1/export/hama?format=csv`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 422]).toContain(resp.status());
  });
});

// ─────────────────────────────────────────────────────
// 9. JWT LIFECYCLE
// ─────────────────────────────────────────────────────
test.describe('Statistisi — API JWT Lifecycle', () => {
  test('statistisi can login via API and get token', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: STATISTISI_USER, password: STATISTISI_PASS },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.token || body.token).toBeTruthy();
  });

  test('statistisi can refresh JWT token', async ({ request }) => {
    const token = await loginAsApi(request, STATISTISI_USER, STATISTISI_PASS);
    test.skip(!token, 'No token');
    const resp = await request.post(`${BASE}/api/v1/auth/refresh`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('statistisi invalid token → 401', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: 'Bearer invalid.token.here' },
    });
    expect(resp.status()).toBe(401);
  });
});

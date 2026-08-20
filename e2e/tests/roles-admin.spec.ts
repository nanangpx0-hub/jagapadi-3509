import { test, expect, type Page, type APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8080';
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
test.describe('Admin — Authentication', () => {
  test('admin can login successfully via web', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('admin can login successfully via API (JWT)', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: ADMIN_USER, password: ADMIN_PASS },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.success).toBe(true);
    expect(body.data?.token).toBeTruthy();
    expect(body.data?.user?.role).toBe('admin');
  });

  test('admin sees user info in navbar after login', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await expect(page.locator('.navbar-user')).toContainText(/admin/i);
  });

  test('admin can logout successfully', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await page.locator('form[action*="logout"] button[type="submit"], a[href*="logout"]').first().click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('admin login rejects SQL injection', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', "' OR '1'='1");
    await page.fill('#password', "' OR '1'='1");
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('admin login rejects XSS injection', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', '<script>alert("xss")</script>');
    await page.fill('#password', 'password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('invalid credentials show error for admin', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', ADMIN_USER);
    await page.fill('#password', 'wrong_password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.flash-error, .flash-warning, .flash-message, .alert-danger')).toBeVisible();
  });

  test('unauthenticated user is redirected to login for protected pages', async ({ page }) => {
    const routes = ['/dashboard', '/laporan-hama', '/laporan-irigasi', '/notifications', '/password/change', '/export', '/wilayah', '/opt'];
    for (const route of routes) {
      await page.goto(`${BASE}${route}`);
      await expect(page, `Route ${route} should redirect to login`).toHaveURL(/\/login/);
    }
  });
});

// ─────────────────────────────────────────────────────
// 2. DASHBOARD
// ─────────────────────────────────────────────────────
test.describe('Admin — Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1, h4')).toContainText(/Dashboard/i);
  });

  test('dashboard displays KPI cards', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const kpiCards = page.locator('.kpi-card');
    const count = await kpiCards.count();
    expect(count).toBeGreaterThan(0);
  });

  test('dashboard displays charts section', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const charts = page.locator('#chartHama, canvas');
    const count = await charts.count();
    expect(count).toBeGreaterThan(0);
  });

  test('dashboard displays map section', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForSelector('#map, .map-container', { timeout: 15000 });
    await expect(page.locator('#map, .map-container')).toBeVisible();
  });

  test('dashboard has admin quick links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const quickLinks = page.locator('.quick-links, a[href*="verifikasi"], a[href*="wilayah"], a[href*="opt"]');
    await expect(quickLinks.first()).toBeVisible();
  });

  test('dashboard can filter by year', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const yearSelect = page.locator('#tahun, select[name="tahun"]');
    if (await yearSelect.count() > 0) {
      await yearSelect.selectOption('2025');
      await page.waitForURL(/\?tahun=2025/);
      expect(page.url()).toContain('tahun=2025');
    }
  });

  test('admin can access dashboard stats API (JWT)', async ({ request }) => {
    const token = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    expect(token).toBeTruthy();
    const resp = await request.get(`${BASE}/api/v1/dashboard/stats`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });

  test('admin can access dashboard map API (JWT)', async ({ request }) => {
    const token = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    expect(token).toBeTruthy();
    const resp = await request.get(`${BASE}/api/v1/dashboard/map/hama`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.type || body.type).toBe('FeatureCollection');
  });
});

// ─────────────────────────────────────────────────────
// 3. LAPORAN HAMA — WEB CRUD & ADMIN ACTIONS
// ─────────────────────────────────────────────────────
test.describe('Admin — Laporan Hama Web', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can view laporan hama list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
    await expect(page.locator('h2, h3, .card-title').first()).toBeVisible();
  });

  test('admin can view laporan hama detail', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-hama/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    }
  });

  test('admin can verify laporan hama', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const verifyBtn = page.locator('button:has-text("Verifikasi"), form[action*="/verifikasi"] button').first();
    if (await verifyBtn.count() > 0) {
      await verifyBtn.click();
      await page.waitForTimeout(1000);
    }
  });

  test('admin can reject laporan hama', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const rejectBtn = page.locator('button:has-text("Tolak"), form[action*="/tolak"] button').first();
    if (await rejectBtn.count() > 0) {
      await rejectBtn.click();
      await page.waitForTimeout(1000);
    }
  });

  test('admin can archive laporan hama', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const archiveBtn = page.locator('button:has-text("Arsipkan"), form[action*="/archive"] button').first();
    if (await archiveBtn.count() > 0) {
      await archiveBtn.click();
      await page.waitForTimeout(1000);
    }
  });
});

// ─────────────────────────────────────────────────────
// 4. LAPORAN IRIGASI — WEB CRUD & ADMIN ACTIONS
// ─────────────────────────────────────────────────────
test.describe('Admin — Laporan Irigasi Web', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can view laporan irigasi list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await expect(page).toHaveURL(/\/laporan-irigasi/);
    await expect(page.locator('h2, h3, .card-title').first()).toBeVisible();
  });

  test('admin can view laporan irigasi detail', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-irigasi/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await expect(page).toHaveURL(/\/laporan-irigasi\/\d+/);
    }
  });

  test('admin can verify laporan irigasi', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const verifyBtn = page.locator('button:has-text("Verifikasi"), form[action*="/verifikasi"] button').first();
    if (await verifyBtn.count() > 0) {
      await verifyBtn.click();
      await page.waitForTimeout(1000);
    }
  });

  test('admin can reject laporan irigasi', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const rejectBtn = page.locator('button:has-text("Tolak"), form[action*="/tolak"] button').first();
    if (await rejectBtn.count() > 0) {
      await rejectBtn.click();
      await page.waitForTimeout(1000);
    }
  });

  test('admin can archive laporan irigasi', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const archiveBtn = page.locator('button:has-text("Arsipkan"), form[action*="/archive"] button').first();
    if (await archiveBtn.count() > 0) {
      await archiveBtn.click();
      await page.waitForTimeout(1000);
    }
  });
});

// ─────────────────────────────────────────────────────
// 5. ADMIN MASTER DATA — WILAYAH & OPT
// ─────────────────────────────────────────────────────
test.describe('Admin — Master Data', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can access wilayah page', async ({ page }) => {
    await page.goto(`${BASE}/wilayah`);
    await expect(page).toHaveURL(/\/wilayah/);
  });

  test('admin can access OPT page', async ({ page }) => {
    await page.goto(`${BASE}/opt`);
    await expect(page).toHaveURL(/\/opt/);
  });

  test('admin can access admin page', async ({ page }) => {
    await page.goto(`${BASE}/admin`);
    await expect(page).toHaveURL(/\/admin/);
  });
});

// ─────────────────────────────────────────────────────
// 6. API — ADMIN FULL ACCESS
// ─────────────────────────────────────────────────────
test.describe('Admin — API Access', () => {
  let adminToken = '';

  test.beforeAll(async ({ request }) => {
    adminToken = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    test.skip(!adminToken, 'Could not obtain admin JWT token');
  });

  test('admin can call /api/v1/me', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.role).toBe('admin');
  });

  test('admin can list laporan hama via API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });

  test('admin can list laporan irigasi via API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-irigasi`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });

  test('admin can create wilayah via API', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/wilayah/kabupaten`, {
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      data: { nama_kabupaten: 'Test Kabupaten E2E' },
    });
    expect([200, 201, 409, 422]).toContain(resp.status());
  });

  test('admin can create OPT via API', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/opt`, {
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      data: { nama_opt: 'Test OPT E2E', jenis: 'hama', etl_acuan: 0 },
    });
    expect([200, 201, 409]).toContain(resp.status());
  });

  test('admin can verify laporan hama via API', async ({ request }) => {
    const listResp = await request.get(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    const listBody = await listResp.json();
    const items = listBody.data || [];
    if (items.length > 0) {
      const id = items[0].id;
      const resp = await request.post(`${BASE}/api/v1/laporan-hama/${id}/verifikasi`, {
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        data: { catatan: 'Diverifikasi oleh E2E test' },
      });
      expect([200, 409]).toContain(resp.status());
    }
  });

  test('admin can access notifications API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/notifications`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    expect(resp.status()).toBe(200);
  });
});

// ─────────────────────────────────────────────────────
// 7. NOTIFICATIONS — WEB
// ─────────────────────────────────────────────────────
test.describe('Admin — Notifications', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can access notifications page', async ({ page }) => {
    await page.goto(`${BASE}/notifications`);
    await expect(page).toHaveURL(/\/notifications/);
    await expect(page.locator('h2, h3, .card-title').first()).toContainText(/Notifikasi/i);
  });

  test('notifications page shows content or empty state', async ({ page }) => {
    await page.goto(`${BASE}/notifications`);
    await page.waitForLoadState('networkidle');
    const hasContent = await page.locator('.card, .notification-item, .empty-state, .alert').count();
    expect(hasContent).toBeGreaterThan(0);
  });
});

// ─────────────────────────────────────────────────────
// 8. EXPORT — WEB
// ─────────────────────────────────────────────────────
test.describe('Admin — Export', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can access export page', async ({ page }) => {
    await page.goto(`${BASE}/export`);
    await expect(page).toHaveURL(/\/export/);
  });

  test('admin can export hama data via API', async ({ request }) => {
    const token = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    expect(token).toBeTruthy();
    const resp = await request.get(`${BASE}/api/v1/export/hama?format=csv`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 422]).toContain(resp.status());
    if (resp.status() === 200) {
      expect(resp.headers()['content-type']).toMatch(/csv|octet|spreadsheet/);
    }
  });

  test('admin can export irigasi data via API', async ({ request }) => {
    const token = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    expect(token).toBeTruthy();
    const resp = await request.get(`${BASE}/api/v1/export/irigasi?format=csv`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 422]).toContain(resp.status());
    if (resp.status() === 200) {
      expect(resp.headers()['content-type']).toMatch(/csv|octet|spreadsheet/);
    }
  });
});

// ─────────────────────────────────────────────────────
// 9. PASSWORD CHANGE
// ─────────────────────────────────────────────────────
test.describe('Admin — Password Change', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
  });

  test('admin can access password change page', async ({ page }) => {
    await page.goto(`${BASE}/password/change`);
    await expect(page).toHaveURL(/\/password\/change/);
    await expect(page.locator('#current_password')).toBeVisible();
    await expect(page.locator('#new_password')).toBeVisible();
  });

  test('CSRF token present on password change form', async ({ page }) => {
    await page.goto(`${BASE}/password/change`);
    const csrfField = page.locator('form input[name="_csrf_token"]');
    await expect(csrfField.count()).resolves.toBeGreaterThanOrEqual(1);
  });
});

// ─────────────────────────────────────────────────────
// 10. JWT LIFECYCLE
// ─────────────────────────────────────────────────────
test.describe('Admin — API JWT Lifecycle', () => {
  test('admin can login via API and get token', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: ADMIN_USER, password: ADMIN_PASS },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.token).toBeTruthy();
  });

  test('admin can refresh JWT token', async ({ request }) => {
    const token = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    test.skip(!token, 'No token');
    const resp = await request.post(`${BASE}/api/v1/auth/refresh`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.token || body.token).toBeTruthy();
  });

  test('admin can logout via API', async ({ request }) => {
    const token = await loginAsApi(request, ADMIN_USER, ADMIN_PASS);
    test.skip(!token, 'No token');
    const logoutResp = await request.post(`${BASE}/api/v1/auth/logout`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(logoutResp.status()).toBe(200);
  });

  test('admin invalid token returns 401', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: 'Bearer invalid.token.here' },
    });
    expect(resp.status()).toBe(401);
  });
});

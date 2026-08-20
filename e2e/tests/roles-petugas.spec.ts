import { test, expect, type Page, type APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'Jember3509';
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

async function waitForTableLoad(page: Page) {
  await page.waitForFunction(
    () => {
      const tbody = document.querySelector('#tableBody');
      if (!tbody) return false;
      const rows = tbody.querySelectorAll('tr');
      if (rows.length === 0) return false;
      for (const r of Array.from(rows)) {
        const text = r.textContent || '';
        if (text.includes('Memuat') || text.includes('Loading')) return false;
        if (text.includes('Gagal memuat')) return false;
      }
      return true;
    },
    { timeout: 25000 }
  );
}

// ─────────────────────────────────────────────────────
// 1. AUTHENTICATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — Authentication', () => {
  test('petugas can login successfully', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('petugas sees user info in navbar after login', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await expect(page.locator('.navbar-user')).toContainText(/petugas/i);
  });

  test('petugas can logout successfully', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.locator('form[action*="logout"] button[type="submit"], a[href*="logout"]').first().click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('invalid credentials show error for petugas', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', PETUGAS_USER);
    await page.fill('#password', 'wrong_password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.flash-error, .flash-warning, .flash-message, .alert-danger')).toBeVisible();
  });

  test('unauthenticated user is redirected to login for protected pages', async ({ page }) => {
    const routes = ['/dashboard', '/laporan-hama', '/laporan-irigasi', '/notifications', '/password/change', '/export'];
    for (const route of routes) {
      await page.goto(`${BASE}${route}`);
      await expect(page, `Route ${route} should redirect to login`).toHaveURL(/\/login/);
    }
  });

  test('petugas login rejects SQL injection', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', "' OR '1'='1");
    await page.fill('#password', "' OR '1'='1");
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('petugas login rejects XSS injection', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', '<script>alert("xss")</script>');
    await page.fill('#password', 'password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });
});

// ─────────────────────────────────────────────────────
// 2. SESSION PERSISTENCE
// ─────────────────────────────────────────────────────
test.describe('Petugas — Session Persistence', () => {
  test('petugas session persists across page navigations', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
    await page.goto(`${BASE}/laporan-irigasi`);
    await expect(page).toHaveURL(/\/laporan-irigasi/);
    await page.goto(`${BASE}/notifications`);
    await expect(page).toHaveURL(/\/notifications/);
  });

  test('petugas session is invalidated after logout', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.locator('form[action*="logout"] button[type="submit"], a[href*="logout"]').first().click();
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/login/);
  });
});

// ─────────────────────────────────────────────────────
// 3. ADMIN-ONLY WEB ROUTES — BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — Admin-Only Web Routes (Blocked)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  const adminRoutes = [
    { path: '/admin', desc: '/admin' },
    { path: '/wilayah', desc: '/wilayah index' },
    { path: '/wilayah/kabupaten/create', desc: '/wilayah/kabupaten/create' },
    { path: '/wilayah/kecamatan/create', desc: '/wilayah/kecamatan/create' },
    { path: '/wilayah/desa/create', desc: '/wilayah/desa/create' },
    { path: '/opt', desc: '/opt index' },
    { path: '/opt/create', desc: '/opt create' },
  ];

  for (const { path, desc } of adminRoutes) {
    test(`petugas is redirected from ${desc} to dashboard`, async ({ page }) => {
      await page.goto(`${BASE}${path}`);
      await expect(page).toHaveURL(/\/dashboard/);
    });
  }
});

// ─────────────────────────────────────────────────────
// 4. ADMIN-ONLY WEB ROUTES — POST BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — Admin-Only Web POST Routes (Blocked)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  const adminPostRoutes = [
    { path: '/wilayah/kabupaten/store', desc: 'kabupaten store' },
    { path: '/wilayah/kecamatan/store', desc: 'kecamatan store' },
    { path: '/wilayah/desa/store', desc: 'desa store' },
    { path: '/opt/store', desc: 'opt store' },
  ];

  for (const { path, desc } of adminPostRoutes) {
    test(`petugas POST ${desc} is blocked`, async ({ page }) => {
      const response = await page.request.post(`${BASE}${path}`, {
        form: { nama: 'test' },
        maxRedirects: 0,
      });
      const status = response.status();
      const location = response.headers()['location'] || '';
      const isBlocked = status === 403
        || status === 422
        || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
      expect(isBlocked).toBeTruthy();
    });
  }
});

// ─────────────────────────────────────────────────────
// 5. DASHBOARD — SCOPED DATA
// ─────────────────────────────────────────────────────
test.describe('Petugas — Dashboard (Scoped)', () => {
  let dashToken = '';

  test.beforeAll(async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: PETUGAS_USER, password: PETUGAS_PASS },
    });
    const body = await resp.json();
    dashToken = body.data?.token || body.token || '';
  });

  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can access dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1')).toContainText('Dashboard');
  });

  test('petugas dashboard shows KPI cards', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const kpiCards = page.locator('.kpi-card');
    const count = await kpiCards.count();
    expect(count).toBeGreaterThan(0);
  });

  test('petugas dashboard does not show admin quick links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page.locator('a:has-text("Verifikasi Laporan Hama")')).toHaveCount(0);
    await expect(page.locator('a:has-text("Manajemen User")')).toHaveCount(0);
    await expect(page.locator('a:has-text("Wilayah")')).toHaveCount(0);
    await expect(page.locator('a:has-text("OPT")')).toHaveCount(0);
  });

  test('petugas can access dashboard stats via API (JWT)', async ({ request }) => {
    test.skip(!dashToken, 'No token');
    const resp = await request.get(`${BASE}/api/v1/dashboard/stats`, {
      headers: { Authorization: `Bearer ${dashToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });

  test('petugas can access dashboard/map/hama', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/map/hama`, {
      headers: { Authorization: `Bearer ${dashToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.type || body.type).toBe('FeatureCollection');
  });

  test('petugas can access dashboard/map/irigasi', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/map/irigasi`, {
      headers: { Authorization: `Bearer ${dashToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.type || body.type).toBe('FeatureCollection');
  });
});

// ─────────────────────────────────────────────────────
// 6. LAPORAN HAMA — CRUD FLOWS
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Hama CRUD', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can view laporan hama list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
    await expect(page.locator('h2, h3, .card-title').first()).toBeVisible();
  });

  test('petugas can open laporan hama create page', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page).toHaveURL(/\/laporan-hama\/create/);
    await expect(page.locator('#laporanHamaCreateForm')).toBeVisible();
  });

  test('petugas can create laporan hama draft', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page).toHaveURL(/\/laporan-hama\/create/);

    const dateInput = page.locator('input[name="tanggal"], #tanggal');
    if (await dateInput.count() > 0) {
      await dateInput.fill('2026-08-15');
    }

    const optSelect = page.locator('select[name="master_opt_id"]').first();
    if (await optSelect.count() > 0) {
      const options = await optSelect.locator('option').all();
      if (options.length > 1) {
        await optSelect.selectOption({ index: 1 });
      }
    }

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      await page.waitForURL(/\/(laporan-hama|laporan-irigasi)/, { timeout: 15000 });
    }
  });

  test('CSRF token present on laporan hama create form', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    const csrfField = page.locator('form input[name="_csrf_token"]');
    await expect(csrfField.count()).resolves.toBeGreaterThanOrEqual(1);
  });

  test('petugas can view own laporan hama detail', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-hama/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    }
  });

  test('petugas own laporan detail does NOT show verify/reject/archive buttons', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const firstLink = page.locator('table tbody tr a[href*="/laporan-hama/"]').first();
    if (await firstLink.count() > 0) {
      await firstLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('button:has-text("Verifikasi"), form[action*="/verifikasi"]')).toHaveCount(0);
      await expect(page.locator('button:has-text("Tolak"), form[action*="/tolak"]')).toHaveCount(0);
      await expect(page.locator('button:has-text("Arsipkan"), form[action*="/archive"]')).toHaveCount(0);
    }
  });
});

// ─────────────────────────────────────────────────────
// 7. LAPORAN HAMA — DATA ISOLATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Hama Data Isolation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas cannot view another user laporan via web', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-hama/99999`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot edit another user laporan via web', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-hama/99999/edit`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot update another user laporan via web POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/99999`, {
      form: { tanggal: '2026-08-15' },
      maxRedirects: 0,
    });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot delete another user laporan via web POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/99999/delete`, {
      maxRedirects: 0,
    });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot submit another user laporan via web POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/99999/submit`, {
      maxRedirects: 0,
    });
    expect([302, 404]).toContain(response.status());
  });
});

// ─────────────────────────────────────────────────────
// 8. LAPORAN HAMA — ADMIN ACTIONS BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Hama Admin Actions (Blocked)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas POST verify hama is blocked', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/1/verifikasi`, {
      form: { status: 'Diverifikasi' },
      maxRedirects: 0,
    });
    const status = response.status();
    const location = response.headers()['location'] || '';
    const isBlocked = status === 403
      || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
    expect(isBlocked).toBeTruthy();
  });

  test('petugas POST reject hama is blocked', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/1/tolak`, {
      form: { alasan: 'Tidak memenuhi kriteria verifikasi untuk testing.' },
      maxRedirects: 0,
    });
    const status = response.status();
    const location = response.headers()['location'] || '';
    const isBlocked = status === 403
      || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
    expect(isBlocked).toBeTruthy();
  });

  test('petugas POST archive hama is blocked', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/1/archive`, {
      maxRedirects: 0,
    });
    const status = response.status();
    const location = response.headers()['location'] || '';
    const isBlocked = status === 403
      || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
    expect(isBlocked).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────
// 9. LAPORAN IRIGASI — CRUD FLOWS
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Irigasi CRUD', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can view laporan irigasi list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await expect(page).toHaveURL(/\/laporan-irigasi/);
    await expect(page.locator('h2, h3, .card-title').first()).toBeVisible();
  });

  test('petugas can open laporan irigasi create page', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    await expect(page).toHaveURL(/\/laporan-irigasi\/create/);
  });

  test('petugas can create laporan irigasi draft', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    await expect(page).toHaveURL(/\/laporan-irigasi\/create/);

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      const finalUrl = page.url();
      expect(finalUrl.includes('/laporan-irigasi') || finalUrl.includes('/edit')).toBeTruthy();
    }
  });

  test('CSRF token present on laporan irigasi create form', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    const csrfField = page.locator('form input[name="_csrf_token"]');
    await expect(csrfField.count()).resolves.toBeGreaterThanOrEqual(1);
  });
});

// ─────────────────────────────────────────────────────
// 10. LAPORAN IRIGASI — DATA ISOLATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Irigasi Data Isolation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas cannot view another user irigasi laporan', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-irigasi/99999`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot edit another user irigasi laporan', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-irigasi/99999/edit`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot update another user irigasi via POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/99999`, {
      form: { tanggal: '2026-08-15' },
      maxRedirects: 0,
    });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot delete another user irigasi via POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/99999/delete`, {
      maxRedirects: 0,
    });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot submit another user irigasi via POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/99999/submit`, {
      maxRedirects: 0,
    });
    expect([302, 404]).toContain(response.status());
  });
});

// ─────────────────────────────────────────────────────
// 11. LAPORAN IRIGASI — ADMIN ACTIONS BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Irigasi Admin Actions (Blocked)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas POST verify irigasi is blocked', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/1/verifikasi`, {
      form: { status: 'Diverifikasi' },
      maxRedirects: 0,
    });
    const status = response.status();
    const location = response.headers()['location'] || '';
    const isBlocked = status === 403
      || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
    expect(isBlocked).toBeTruthy();
  });

  test('petugas POST reject irigasi is blocked', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/1/tolak`, {
      form: { alasan: 'Tidak memenuhi kriteria verifikasi untuk testing.' },
      maxRedirects: 0,
    });
    const status = response.status();
    const location = response.headers()['location'] || '';
    const isBlocked = status === 403
      || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
    expect(isBlocked).toBeTruthy();
  });

  test('petugas POST archive irigasi is blocked', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/1/archive`, {
      maxRedirects: 0,
    });
    const status = response.status();
    const location = response.headers()['location'] || '';
    const isBlocked = status === 403
      || (status === 302 && (location.includes('/login') || location.includes('/dashboard')));
    expect(isBlocked).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────
// 12. API — PETUGAS JWT vs ADMIN ENDPOINTS
// ─────────────────────────────────────────────────────
test.describe('Petugas — API Access Control', () => {
  let petugasToken = '';

  test.beforeAll(async ({ request }) => {
    petugasToken = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!petugasToken, 'Could not obtain petugas JWT token');
  });

  test('petugas can call /api/v1/me', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.username || body.username).toBe(PETUGAS_USER);
  });

  test('petugas can call /api/v1/dashboard/stats', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/stats`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can list laporan hama (own only)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });

  test('petugas can list laporan irigasi (own only)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-irigasi`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });

  test('petugas can access notifications API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/notifications`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can access unread-count API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/notifications/unread-count`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });
});

// ─────────────────────────────────────────────────────
// 13. API — ADMIN WRITE ENDPOINTS BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — API Admin Write Endpoints (403)', () => {
  let petugasToken = '';

  test.beforeAll(async ({ request }) => {
    petugasToken = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!petugasToken, 'Could not obtain petugas JWT token');
  });

  const adminWriteTests = [
    { method: 'POST', path: '/api/v1/wilayah/kabupaten', body: { nama_kabupaten: 'Test' }, desc: 'create kabupaten' },
    { method: 'PUT', path: '/api/v1/wilayah/kabupaten/1', body: { nama_kabupaten: 'Test' }, desc: 'update kabupaten' },
    { method: 'DELETE', path: '/api/v1/wilayah/kabupaten/1', body: {}, desc: 'delete kabupaten' },
    { method: 'POST', path: '/api/v1/opt', body: { nama_opt: 'Test', jenis: 'hama', etl_acuan: 0 }, desc: 'create OPT' },
    { method: 'PUT', path: '/api/v1/opt/1', body: { nama_opt: 'Test' }, desc: 'update OPT' },
    { method: 'DELETE', path: '/api/v1/opt/1', body: {}, desc: 'delete OPT' },
  ];

  for (const { method, path, body, desc } of adminWriteTests) {
    test(`petugas ${method} ${desc} → 403 Forbidden`, async ({ request }) => {
      const opts: any = {
        headers: { Authorization: `Bearer ${petugasToken}`, 'Content-Type': 'application/json' },
      };
      if (method !== 'DELETE') {
        opts.data = body;
      }
      const resp = await request.fetch(`${BASE}${path}`, { method, ...opts });
      expect(resp.status()).toBe(403);
      const respBody = await resp.json();
      expect(respBody.success).toBe(false);
      expect(respBody.error).toBe('Forbidden');
    });
  }
});

// ─────────────────────────────────────────────────────
// 14. API — ADMIN LAPORAN ACTIONS BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — API Admin Laporan Actions (403)', () => {
  let petugasToken = '';

  test.beforeAll(async ({ request }) => {
    petugasToken = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!petugasToken, 'Could not obtain petugas JWT token');
  });

  const adminLaporanTests = [
    { path: '/api/v1/laporan-hama/1/verifikasi', body: {}, desc: 'verify hama' },
    { path: '/api/v1/laporan-hama/1/tolak', body: { alasan: 'Tidak memenuhi kriteria verifikasi untuk testing.' }, desc: 'reject hama' },
    { path: '/api/v1/laporan-hama/1/archive', body: {}, desc: 'archive hama' },
    { path: '/api/v1/laporan-irigasi/1/verifikasi', body: {}, desc: 'verify irigasi' },
    { path: '/api/v1/laporan-irigasi/1/tolak', body: { alasan: 'Tidak memenuhi kriteria verifikasi untuk testing.' }, desc: 'reject irigasi' },
    { path: '/api/v1/laporan-irigasi/1/archive', body: {}, desc: 'archive irigasi' },
  ];

  for (const { path, body, desc } of adminLaporanTests) {
    test(`petugas POST ${desc} via API → 403`, async ({ request }) => {
      const resp = await request.post(`${BASE}${path}`, {
        headers: { Authorization: `Bearer ${petugasToken}`, 'Content-Type': 'application/json' },
        data: body,
      });
      expect(resp.status()).toBe(403);
      const respBody = await resp.json();
      expect(respBody.success).toBe(false);
      expect(respBody.error).toBe('Forbidden');
    });
  }
});

// ─────────────────────────────────────────────────────
// 15. API — DATA ISOLATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — API Data Isolation', () => {
  let petugasToken = '';

  test.beforeAll(async ({ request }) => {
    petugasToken = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!petugasToken, 'Could not obtain petugas JWT token');
  });

  test('petugas cannot view another user laporan hama by ID via API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-hama/99999`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(404);
  });

  test('petugas cannot view another user laporan irigasi by ID via API', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-irigasi/99999`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(404);
  });

  test('petugas cannot update another user laporan hama via API', async ({ request }) => {
    const resp = await request.put(`${BASE}/api/v1/laporan-hama/99999`, {
      headers: { Authorization: `Bearer ${petugasToken}`, 'Content-Type': 'application/json' },
      data: { tanggal: '2026-08-15' },
    });
    expect(resp.status()).toBe(404);
  });

  test('petugas cannot delete another user laporan hama via API', async ({ request }) => {
    const resp = await request.delete(`${BASE}/api/v1/laporan-hama/99999`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(404);
  });

  test('petugas cannot submit another user laporan hama via API', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/laporan-hama/99999/submit`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(404);
  });
});

// ─────────────────────────────────────────────────────
// 16. API — UNAUTHENTICATED ACCESS
// ─────────────────────────────────────────────────────
test.describe('Petugas — API Unauthenticated (401)', () => {
  const protectedEndpoints = [
    { method: 'GET', path: '/api/v1/me' },
    { method: 'GET', path: '/api/v1/dashboard/stats' },
    { method: 'GET', path: '/api/v1/dashboard/map/hama' },
    { method: 'GET', path: '/api/v1/laporan-hama' },
    { method: 'GET', path: '/api/v1/laporan-irigasi' },
    { method: 'GET', path: '/api/v1/wilayah/kabupaten' },
    { method: 'GET', path: '/api/v1/opt' },
    { method: 'GET', path: '/api/v1/notifications' },
    { method: 'POST', path: '/api/v1/auth/logout' },
  ];

  for (const { method, path } of protectedEndpoints) {
    test(`${method} ${path} without token → 401`, async ({ request }) => {
      const resp = await request.fetch(`${BASE}${path}`, { method });
      expect(resp.status()).toBe(401);
    });
  }
});

// ─────────────────────────────────────────────────────
// 17. SIDEBAR & NAVIGATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — Sidebar & Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas sidebar does not show admin-only links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const hasAdminLinks = await page.locator('a[href="/wilayah"], a[href="/opt"], a[href="/admin"]').count();
    expect(hasAdminLinks).toBe(0);
  });

  test('petugas sidebar shows accessible links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page.locator('a[href="/dashboard"]')).toBeVisible();
    await expect(page.locator('a[href="/laporan-hama"]')).toBeVisible();
    await expect(page.locator('a[href="/laporan-irigasi"]')).toBeVisible();
  });
});

// ─────────────────────────────────────────────────────
// 18. NOTIFICATIONS
// ─────────────────────────────────────────────────────
test.describe('Petugas — Notifications', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can view notifications page', async ({ page }) => {
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
// 19. EXPORT
// ─────────────────────────────────────────────────────
test.describe('Petugas — Export', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can access export page', async ({ page }) => {
    await page.goto(`${BASE}/export`);
    await expect(page).toHaveURL(/\/export/);
  });

  test('petugas can access export/hama API (scoped)', async ({ request }) => {
    const token = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    expect(token).toBeTruthy();
    const resp = await request.get(`${BASE}/api/v1/export/hama?format=csv`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 422]).toContain(resp.status());
  });
});

// ─────────────────────────────────────────────────────
// 20. PASSWORD CHANGE
// ─────────────────────────────────────────────────────
test.describe('Petugas — Password Change', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can access password change page', async ({ page }) => {
    await page.goto(`${BASE}/password/change`);
    await expect(page).toHaveURL(/\/password\/change/);
    await expect(page.locator('#current_password')).toBeVisible();
    await expect(page.locator('#new_password')).toBeVisible();
  });

  test('CSRF token present on password change form', async ({ page }) => {
    await page.goto(`${BASE}/password/change`);
    const csrfField = page.locator('form input[name="_csrf_token"]');
    await expect(csrfField.count()).resolves.toBeGreaterThanOrEqual(1);
  }
  );

  test('petugas can change password via API', async ({ request }) => {
    const token = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    expect(token).toBeTruthy();
    const resp = await request.post(`${BASE}/api/v1/auth/change-password`, {
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      data: {
        current_password: PETUGAS_PASS,
        new_password: 'NewPass!234',
        new_password_confirmation: 'NewPass!234',
      },
    });
    expect([200, 422]).toContain(resp.status());
  });
});

// ─────────────────────────────────────────────────────
// 21. API — JWT LIFECYCLE
// ─────────────────────────────────────────────────────
test.describe('Petugas — API JWT Lifecycle', () => {
  test('petugas can login via API and get token', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: PETUGAS_USER, password: PETUGAS_PASS },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.token || body.token).toBeTruthy();
  });

  test('petugas can refresh JWT token', async ({ request }) => {
    const token = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!token, 'No token');
    const resp = await request.post(`${BASE}/api/v1/auth/refresh`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data?.token || body.token).toBeTruthy();
  });

  test('petugas can logout via API', async ({ request }) => {
    const token = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!token, 'No token');
    const logoutResp = await request.post(`${BASE}/api/v1/auth/logout`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(logoutResp.status()).toBe(200);
  });

  test('petugas invalid token → 401', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: 'Bearer invalid.token.here' },
    });
    expect(resp.status()).toBe(401);
  });
});

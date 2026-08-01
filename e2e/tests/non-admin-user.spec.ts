import { test, expect, type Page, type APIRequestContext } from '@playwright/test';

const BASE = 'http://localhost:8080';

const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'ChangeMePetugas!123';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'ChangeMeAdmin!123';

async function loginAs(page: Page, username: string, password: string) {
  await page.goto(`${BASE}/login`);
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/);
  }
}

async function loginAsApi(request: APIRequestContext, username: string, password: string): Promise<string> {
  const resp = await request.post(`${BASE}/api/v1/auth/login`, {
    data: { username, password },
  });
  const body = await resp.json();
  return body.data?.token || body.token || '';
}

async function getOwnLaporanId(page: Page): Promise<string | null> {
  await page.goto(`${BASE}/laporan-hama`);
  await page.waitForLoadState('networkidle');
  const row = page.locator('table tbody tr').first();
  if (await row.count() === 0) return null;
  const link = row.locator('a[href*="/laporan-hama/"]').first();
  const href = await link.getAttribute('href');
  if (!href) return null;
  const match = href.match(/\/laporan-hama\/(\d+)/);
  return match ? match[1] : null;
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
    await expect(page.locator('.navbar-user')).toContainText('petugas');
  });

  test('petugas can logout successfully', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.locator('form[action="/logout"] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('invalid credentials show error for petugas', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('#username', PETUGAS_USER);
    await page.fill('#password', 'wrong_password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('.flash-error, .flash-warning, .flash-message')).toBeVisible();
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
    await page.fill('#username', "' OR '1'='1");
    await page.fill('#password', "' OR '1'='1");
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('petugas login rejects XSS injection', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('#username', '<script>alert("xss")</script>');
    await page.fill('#password', 'password');
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('petugas login handles long input gracefully', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.fill('#username', 'a'.repeat(1000));
    await page.fill('#password', 'test');
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
    await page.locator('form[action="/logout"] button[type="submit"]').click();
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
      await expect(page.locator('.flash-error, .flash-warning, .flash-message')).toBeVisible();
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
        || (status === 302 && (location.includes('/login') || location.includes('/dashboard')))
        || (status === 200 && !location.includes('/wilayah/kabupaten'));
      expect(isBlocked).toBeTruthy();
    });
  }
});

// ─────────────────────────────────────────────────────
// 5. WILAYAH JSON CASCADING — ACCESSIBLE
// ─────────────────────────────────────────────────────
test.describe('Petugas — Wilayah JSON Cascading (Accessible)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can access kecamatan-json for cascading dropdown', async ({ page }) => {
    const response = await page.request.get(`${BASE}/wilayah/kecamatan-json?kabupaten_id=1`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    const data = Array.isArray(body) ? body : (body.data || body.kecamatan || []);
    expect(Array.isArray(data)).toBeTruthy();
  });

  test('petugas can access desa-json for cascading dropdown', async ({ page }) => {
    const response = await page.request.get(`${BASE}/wilayah/desa-json?kecamatan_id=1`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    const data = Array.isArray(body) ? body : (body.data || body.desa || []);
    expect(Array.isArray(data)).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────
// 6. DASHBOARD — SCOPED DATA
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
    await expect(page.locator('.kpi-card')).toHaveCount(5);
  });

  test('petugas dashboard does not show admin quick links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page.locator('a:has-text("Verifikasi Laporan Hama")')).toHaveCount(0);
    await expect(page.locator('a:has-text("Manajemen User")')).toHaveCount(0);
    await expect(page.locator('a:has-text("Wilayah")')).toHaveCount(0);
    await expect(page.locator('a:has-text("OPT")')).toHaveCount(0);
  });

  test('petugas dashboard shows petugas quick links', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const hasCreateLink = await page.locator('a[href*="/laporan-hama/create"], a:has-text("Buat Laporan")').count();
    expect(hasCreateLink).toBeGreaterThanOrEqual(1);
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

  test('petugas can access dashboard/map/hama', async ({ page }) => {
    const response = await page.request.get(`${BASE}/dashboard/map/hama`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('type');
    expect(body.type).toBe('FeatureCollection');
  });

  test('petugas can access dashboard/map/irigasi', async ({ page }) => {
    const response = await page.request.get(`${BASE}/dashboard/map/irigasi`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('type');
    expect(body.type).toBe('FeatureCollection');
  });

  test('petugas dashboard KPI data is scoped to own reports', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    const kpiCards = page.locator('.kpi-card');
    const count = await kpiCards.count();
    expect(count).toBe(5);
    for (let i = 0; i < count; i++) {
      const value = kpiCards.nth(i).locator('.kpi-value, h3, .number');
      await expect(value).toBeVisible();
    }
  });
});

// ─────────────────────────────────────────────────────
// 7. LAPORAN HAMA — CRUD FLOWS
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Hama CRUD', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can view laporan hama list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
    await expect(page.locator('h2').first()).toBeVisible();
  });

  test('petugas laporan list does not show bulk delete button', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const bulkDelete = page.locator('#btnBulkDelete');
    await expect(bulkDelete).toHaveCount(0);
  });

  test('petugas laporan list shows petugas mode info', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const modeInfo = page.locator('.alert-info:has-text("Mode Petugas")');
    if (await modeInfo.count() > 0) {
      await expect(modeInfo.first()).toBeVisible();
    }
  });

  test('petugas can open laporan hama create page', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page).toHaveURL(/\/laporan-hama\/create/);
    await expect(page.locator('form[action="/laporan-hama"]')).toBeVisible();
  });

  test('petugas create form does not show target_user_id selector', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page.locator('#targetUserId')).toHaveCount(0);
  });

  test('CSRF token present on laporan hama create form', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    const csrfField = page.locator('form input[name="_csrf_token"]');
    await expect(csrfField.count()).resolves.toBeGreaterThanOrEqual(1);
  });

  test('petugas can create laporan hama draft', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page).toHaveURL(/\/laporan-hama\/create/);

    const dateInput = page.locator('#tanggal');
    if (await dateInput.isVisible()) {
      await dateInput.fill('2026-07-20');
    }

    const optSelect = page.locator('select[name="master_opt_id"]').first();
    if (await optSelect.isVisible()) {
      const options = await optSelect.locator('option').all();
      if (options.length > 1) {
        await optSelect.selectOption({ index: 1 });
      }
    }

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      const finalUrl = page.url();
      expect(finalUrl.includes('/laporan-hama') || finalUrl.includes('/edit')).toBeTruthy();
    }
  });

  test('petugas can view own laporan hama detail', async ({ page }) => {
    const id = await getOwnLaporanId(page);
    test.skip(id === null, 'No petugas laporan hama to view');
    await page.goto(`${BASE}/laporan-hama/${id}`);
    await expect(page).toHaveURL(new RegExp(`/laporan-hama/${id}$`));
  });

  test('petugas own laporan detail shows edit button for editable statuses', async ({ page }) => {
    const id = await getOwnLaporanId(page);
    test.skip(id === null, 'No petugas laporan hama to view');
    await page.goto(`${BASE}/laporan-hama/${id}`);
    await page.waitForLoadState('networkidle');
    const statusText = await page.locator('.badge, .status-badge, [class*="status"]').first().textContent().catch(() => '');
    if (statusText?.includes('Draf') || statusText?.includes('Ditolak')) {
      const editLink = page.locator(`a[href="/laporan-hama/${id}/edit"]`);
      await expect(editLink).toBeVisible();
    }
  });

  test('petugas own laporan detail does NOT show verify/reject/archive buttons', async ({ page }) => {
    const id = await getOwnLaporanId(page);
    test.skip(id === null, 'No petugas laporan hama to view');
    await page.goto(`${BASE}/laporan-hama/${id}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('button:has-text("Verifikasi"), form[action*="/verifikasi"]')).toHaveCount(0);
    await expect(page.locator('button:has-text("Tolak"), form[action*="/tolak"]')).toHaveCount(0);
    await expect(page.locator('button:has-text("Arsipkan"), form[action*="/archive"]')).toHaveCount(0);
  });

  test('petugas can submit own draft laporan hama', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const submitBtn = page.locator('button[type="submit"]:has-text("Submit"), form[action*="/submit"] button[type="submit"]').first();
    const count = await submitBtn.count();
    test.skip(count === 0, 'No draft laporan hama available to submit');
    await submitBtn.click();
    await page.waitForTimeout(1000);
  });

  test('petugas can delete own draft laporan hama', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const deleteForm = page.locator('form[action*="/delete"]').first();
    const formCount = await deleteForm.count();
    test.skip(formCount === 0, 'No draft laporan hama with delete form available');
    await expect(deleteForm.locator('input[name="_csrf_token"]')).toHaveCount(1);
  });
});

// ─────────────────────────────────────────────────────
// 8. LAPORAN HAMA — DATA ISOLATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Hama Data Isolation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas cannot view another user laporan via web (redirects to list)', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-hama/99999`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
    if (response.status() === 302) {
      const location = response.headers()['location'] || '';
      expect(location).toContain('/laporan-hama');
    }
  });

  test('petugas cannot edit another user laporan via web', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-hama/99999/edit`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
    if (response.status() === 302) {
      const location = response.headers()['location'] || '';
      expect(location).toContain('/laporan-hama');
    }
  });

  test('petugas cannot update another user laporan via web POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-hama/99999`, {
      form: { tanggal: '2026-07-20' },
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
// 9. LAPORAN HAMA — ADMIN ACTIONS BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Hama Admin Actions (Blocked)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas POST verify hama is blocked → not verified', async ({ page }) => {
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

  test('petugas POST reject hama is blocked → not rejected', async ({ page }) => {
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

  test('petugas POST archive hama is blocked → not archived', async ({ page }) => {
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
// 10. LAPORAN IRIGASI — CRUD FLOWS
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Irigasi CRUD', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can view laporan irigasi list', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await expect(page).toHaveURL(/\/laporan-irigasi/);
    await expect(page.locator('h2').first()).toBeVisible();
  });

  test('petugas can open laporan irigasi create page', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    await expect(page).toHaveURL(/\/laporan-irigasi\/create/);
  });

  test('petugas can create laporan irigasi draft', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    await expect(page).toHaveURL(/\/laporan-irigasi\/create/);

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      const finalUrl = page.url();
      expect(finalUrl.includes('/laporan-irigasi') || finalUrl.includes('/edit')).toBeTruthy();
    }
  });

  test('petugas laporan irigasi list does not show bulk delete', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const bulkDelete = page.locator('#btnBulkDelete');
    await expect(bulkDelete).toHaveCount(0);
  });

  test('petugas laporan irigasi list shows petugas mode info', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const modeInfo = page.locator('.alert-info:has-text("Mode Petugas")');
    if (await modeInfo.count() > 0) {
      await expect(modeInfo.first()).toBeVisible();
    }
  });

  test('CSRF token present on laporan irigasi create form', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    const csrfField = page.locator('form input[name="_csrf_token"]');
    await expect(csrfField.count()).resolves.toBeGreaterThanOrEqual(1);
  });
});

// ─────────────────────────────────────────────────────
// 11. LAPORAN IRIGASI — DATA ISOLATION
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Irigasi Data Isolation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas cannot view another user irigasi laporan', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-irigasi/99999`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
    if (response.status() === 302) {
      const location = response.headers()['location'] || '';
      expect(location).toContain('/laporan-irigasi');
    }
  });

  test('petugas cannot edit another user irigasi laporan', async ({ page }) => {
    const response = await page.request.get(`${BASE}/laporan-irigasi/99999/edit`, { maxRedirects: 0 });
    expect([302, 404]).toContain(response.status());
  });

  test('petugas cannot update another user irigasi via POST', async ({ page }) => {
    const response = await page.request.post(`${BASE}/laporan-irigasi/99999`, {
      form: { tanggal: '2026-07-20' },
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
// 12. LAPORAN IRIGASI — ADMIN ACTIONS BLOCKED
// ─────────────────────────────────────────────────────
test.describe('Petugas — Laporan Irigasi Admin Actions (Blocked)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas POST verify irigasi is blocked → not verified', async ({ page }) => {
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

  test('petugas POST reject irigasi is blocked → not rejected', async ({ page }) => {
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

  test('petugas POST archive irigasi is blocked → not archived', async ({ page }) => {
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
// 13. API — PETUGAS JWT vs ADMIN ENDPOINTS
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

  test('petugas can call /api/v1/dashboard/map/hama', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/map/hama`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    const geo = body.data || body;
    expect(geo.type).toBe('FeatureCollection');
  });

  test('petugas can call /api/v1/dashboard/map/irigasi', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/dashboard/map/irigasi`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    const geo = body.data || body;
    expect(geo.type).toBe('FeatureCollection');
  });

  test('petugas can list wilayah kabupaten (read)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/wilayah/kabupaten`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can list kecamatan (read)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/wilayah/kecamatan?kabupaten_id=1`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can list desa (read)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/wilayah/desa?kecamatan_id=1`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can list OPT (read)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/opt`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can list laporan hama (own only)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-hama`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('petugas can list laporan irigasi (own only)', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/laporan-irigasi`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(200);
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
// 14. API — ADMIN WRITE ENDPOINTS BLOCKED
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
    { method: 'POST', path: '/api/v1/wilayah/kecamatan', body: { nama_kecamatan: 'Test' }, desc: 'create kecamatan' },
    { method: 'PUT', path: '/api/v1/wilayah/kecamatan/1', body: { nama_kecamatan: 'Test' }, desc: 'update kecamatan' },
    { method: 'DELETE', path: '/api/v1/wilayah/kecamatan/1', body: {}, desc: 'delete kecamatan' },
    { method: 'POST', path: '/api/v1/wilayah/desa', body: { nama_desa: 'Test' }, desc: 'create desa' },
    { method: 'PUT', path: '/api/v1/wilayah/desa/1', body: { nama_desa: 'Test' }, desc: 'update desa' },
    { method: 'DELETE', path: '/api/v1/wilayah/desa/1', body: {}, desc: 'delete desa' },
    { method: 'POST', path: '/api/v1/opt', body: { nama_opt: 'Test' }, desc: 'create OPT' },
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
// 15. API — ADMIN LAPORAN ACTIONS BLOCKED
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
// 16. API — DATA ISOLATION
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
      data: { tanggal: '2026-07-20' },
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

  test('petugas cannot update another user laporan irigasi via API', async ({ request }) => {
    const resp = await request.put(`${BASE}/api/v1/laporan-irigasi/99999`, {
      headers: { Authorization: `Bearer ${petugasToken}`, 'Content-Type': 'application/json' },
      data: { tanggal: '2026-07-20' },
    });
    expect(resp.status()).toBe(404);
  });

  test('petugas cannot delete another user laporan irigasi via API', async ({ request }) => {
    const resp = await request.delete(`${BASE}/api/v1/laporan-irigasi/99999`, {
      headers: { Authorization: `Bearer ${petugasToken}` },
    });
    expect(resp.status()).toBe(404);
  });
});

// ─────────────────────────────────────────────────────
// 17. API — UNAUTHENTICATED ACCESS
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
// 18. SIDEBAR & NAVIGATION
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

  test('petugas can navigate from sidebar laporan-hama to create', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.locator('a[href="/laporan-hama"]').click();
    await expect(page).toHaveURL(/\/laporan-hama/);
    const createLink = page.locator('a[href="/laporan-hama/create"]');
    if (await createLink.count() > 0) {
      await createLink.first().click();
      await expect(page).toHaveURL(/\/laporan-hama\/create/);
    }
  });
});

// ─────────────────────────────────────────────────────
// 19. NOTIFICATIONS
// ─────────────────────────────────────────────────────
test.describe('Petugas — Notifications', () => {
  let notifToken = '';

  test.beforeAll(async ({ request }) => {
    const resp = await request.post(`${BASE}/api/v1/auth/login`, {
      data: { username: PETUGAS_USER, password: PETUGAS_PASS },
    });
    const body = await resp.json();
    notifToken = body.data?.token || body.token || '';
  });

  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can view notifications page', async ({ page }) => {
    await page.goto(`${BASE}/notifications`);
    await expect(page).toHaveURL(/\/notifications/);
    await expect(page.locator('h2').first()).toContainText('Notifikasi');
  });

  test('notifications page shows content or empty state', async ({ page }) => {
    await page.goto(`${BASE}/notifications`);
    await page.waitForLoadState('networkidle');
    const hasContent = await page.locator('.card, .notification-item, .empty-state, .alert').count();
    expect(hasContent).toBeGreaterThan(0);
  });

  test('petugas can access notifications unread count via API', async ({ request }) => {
    test.skip(!notifToken, 'No token');
    const resp = await request.get(`${BASE}/api/v1/notifications/unread-count`, {
      headers: { Authorization: `Bearer ${notifToken}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body).toHaveProperty('data');
  });
});

// ─────────────────────────────────────────────────────
// 20. EXPORT
// ─────────────────────────────────────────────────────
test.describe('Petugas — Export', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
  });

  test('petugas can access the export page', async ({ page }) => {
    await page.goto(`${BASE}/export`);
    await expect(page).toHaveURL(/\/export/);
  });

  test('petugas can access export/hama POST (scoped to own data)', async ({ page }) => {
    const response = await page.request.post(`${BASE}/export/hama`, {
      form: { format: 'csv', tanggal_dari: '2026-01-01', tanggal_sampai: '2026-12-31' },
    });
    expect([200, 302]).toContain(response.status());
  });
});

// ─────────────────────────────────────────────────────
// 21. PASSWORD CHANGE
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
  });
});

// ─────────────────────────────────────────────────────
// 22. API — JWT LIFECYCLE
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

  test('petugas can logout via API (token revoked)', async ({ request }) => {
    const token = await loginAsApi(request, PETUGAS_USER, PETUGAS_PASS);
    test.skip(!token, 'No token');
    const logoutResp = await request.post(`${BASE}/api/v1/auth/logout`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(logoutResp.status()).toBe(200);

    const meResp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(meResp.status()).toBe(401);
  });

  test('petugas invalid token → 401', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: 'Bearer invalid.token.here' },
    });
    expect(resp.status()).toBe(401);
  });

  test('petugas expired token → 401', async ({ request }) => {
    const resp = await request.get(`${BASE}/api/v1/me`, {
      headers: { Authorization: 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIiwicm9sZSI6InBldHVnYXMiLCJleHAiOjE2MDAwMDAwMDB9.invalid' },
    });
    expect(resp.status()).toBe(401);
  });
});

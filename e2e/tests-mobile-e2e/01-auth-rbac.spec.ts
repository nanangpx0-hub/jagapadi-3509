/**
 * Suite 01 — Autentikasi & Role-Based Access Control (RBAC)
 *
 * Menguji seluruh alur autentikasi dan otorisasi untuk 5 role:
 * - admin, petugas, operator, statistisi, viewer
 *
 * Cakupan:
 * - Login page UI & validasi mobile
 * - Login sukses & gagal
 * - JWT API authentication (digunakan Flutter mobile)
 * - Logout & invalidasi sesi
 * - CSRF protection
 * - Rate limiting / brute force
 * - Redirect behavior per role
 * - Must-change-password flow
 * - Token expiry handling
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, ROLES, ADMIN, PETUGAS, OPERATOR, STATISTISI, VIEWER,
  loginWeb, loginApi, loginAsRole, loginAsRoleApi,
  attachConsoleErrorListener, filterCriticalErrors,
  assertApiEnvelope, PERF, getViewportLabel,
  attachScreenshot, attachLog,
} from './helpers';

// ═══ 01-A: Login Page UI pada Mobile ══════════════════════════════════════════

test.describe('01-A: Tampilan Halaman Login pada Mobile', () => {
  test('login page render tanpa JS error @all-devices', async ({ page }, testInfo) => {
    const errors = attachConsoleErrorListener(page);
    const t0 = Date.now();
    await page.goto(BASE + '/login');
    await page.waitForLoadState('networkidle');
    const loadTime = Date.now() - t0;

    // Elemen wajib ada
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();

    // Attach evidence
    testInfo.annotations.push(
      { type: 'Load time (ms)', description: String(loadTime) },
      { type: 'Viewport', description: getViewportLabel(page) },
    );
    await attachScreenshot(page, testInfo, 'login-page');

    const criticalErrors = filterCriticalErrors(errors);
    expect(criticalErrors, `JS errors: ${criticalErrors.join('\n')}`).toHaveLength(0);
  });

  test('login form tidak overflow pada layar kecil @all-devices', async ({ page }) => {
    await page.goto(BASE + '/login');
    const form = page.locator('form');
    await expect(form).toBeVisible();
    const box = await form.boundingBox();
    expect(box).not.toBeNull();
    const vp = page.viewportSize()!;
    expect(box!.x + box!.width).toBeLessThanOrEqual(vp.width + 5);
  });

  test('body scroll width tidak melebihi viewport @all-devices', async ({ page }) => {
    await page.goto(BASE + '/login');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 5);
  });

  test('tombol submit memiliki touch target minimal 44px (WCAG 2.5.5)', async ({ page }) => {
    await page.goto(BASE + '/login');
    const btn = page.locator('button[type="submit"]');
    const box = await btn.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.height).toBeGreaterThanOrEqual(44);
  });

  test('input field dapat difokus dan diisi di mobile', async ({ page }) => {
    await page.goto(BASE + '/login');
    await page.fill('input[name="username"]', 'test_user');
    await page.fill('#password', 'test_pass');
    const usernameVal = await page.inputValue('input[name="username"]');
    const passwordVal = await page.inputValue('#password');
    expect(usernameVal).toBe('test_user');
    expect(passwordVal).toBe('test_pass');
  });

  test('CSRF token wajib ada di form login', async ({ page }) => {
    await page.goto(BASE + '/login');
    const csrf = page.locator('input[name="_csrf_token"], input[name="csrf_token"]');
    const count = await csrf.count();
    expect(count).toBeGreaterThan(0);
  });
});

// ═══ 01-B: Login Fungsional — Semua Role ═════════════════════════════════════

for (const [roleName, role] of Object.entries(ROLES)) {
  test.describe(`01-B: Login Role — ${role.label} (${roleName})`, () => {
    test(`login ${roleName} sukses diarahkan ke dashboard`, async ({ page }) => {
      await loginWeb(page, role.user, role.pass);
      expect(page.url()).toMatch(/\/(dashboard|password\/change)/);
    });

    test(`login ${roleName} via API mengembalikan JWT`, async ({ page }) => {
      const token = await loginApi(page, role.user, role.pass);
      expect(typeof token).toBe('string');
      expect(token.length).toBeGreaterThan(10);
    });
  });
}

test.describe('01-C: Login Gagal & Validasi', () => {
  test('login gagal dengan kredensial salah menampilkan pesan error', async ({ page }) => {
    await page.goto(BASE + '/login');
    await page.fill('input[name="username"]', 'bukan_user');
    await page.fill('#password', 'salah_pass');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toMatch(/\/login/);
    const alert = page.locator('.alert-danger, .alert, [class*="error"], [class*="alert"]');
    await expect(alert.first()).toBeVisible();
  });

  test('login tanpa CSRF token ditolak', async ({ page }) => {
    await page.goto(BASE + '/login');
    await page.evaluate(() => {
      document.querySelectorAll('input[name="_csrf_token"], input[name="csrf_token"]')
        .forEach((el) => el.remove());
    });
    await page.fill('input[name="username"]', ADMIN.user);
    await page.fill('#password', ADMIN.pass);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/login/);
  });

  test('halaman dilindungi redirect ke login jika belum autentikasi', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/login/);
  });

  test('API login gagal dengan password salah mengembalikan 401', async ({ page }) => {
    const res = await page.request.post(API_BASE + '/auth/login', {
      data: { username: PETUGAS.user, password: 'salah_sekali' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(res.status()).toBe(401);
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(false);
  });

  test('API login tanpa field wajib mengembalikan 400/422', async ({ page }) => {
    const res = await page.request.post(API_BASE + '/auth/login', {
      data: { username: '' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect([400, 422]).toContain(res.status());
  });
});

// ═══ 01-D: Logout & Sesi ════════════════════════════════════════════════════

test.describe('01-D: Logout & Invalidasi Sesi', () => {
  test('logout menghapus sesi dan redirect ke login', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    const logoutBtn = page.locator(
      'form[action*="logout"] button[type="submit"], a[href*="logout"]',
    ).first();
    await logoutBtn.click();
    await page.waitForURL(/\/login/, { timeout: 10000 });

    // Pastikan session tidak bisa digunakan kembali
    await page.goto(BASE + '/dashboard');
    expect(page.url()).toMatch(/\/login/);
  });

  test('web session yang invalid di-redirect ke login', async ({ page }) => {
    await page.context().addCookies([{
      name: 'PHPSESSID',
      value: 'invalid_session_xyz_playwright',
      domain: 'localhost',
      path: '/',
    }]);
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/login/);
  });

  test('POST /auth/logout API memanggil endpoint tanpa error', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.post(API_BASE + '/auth/logout', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
  });
});

// ═══ 01-E: JWT API Authentication ════════════════════════════════════════════

test.describe('01-E: JWT API Authentication (REST API)', () => {
  test('POST /auth/login mengembalikan JWT token', async ({ page }) => {
    const res = await page.request.post(API_BASE + '/auth/login', {
      data: { username: PETUGAS.user, password: PETUGAS.pass },
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    assertApiEnvelope(body);
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(typeof data['token']).toBe('string');
    expect((data['token'] as string).length).toBeGreaterThan(10);
  });

  test('GET /me mengembalikan profil user saat token valid', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.get(API_BASE + '/me', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(data['username']).toBe(PETUGAS.user);
    expect(data['role']).toBe('petugas');
  });

  test('GET /me mengembalikan 401 tanpa token', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/me');
    expect([401, 403]).toContain(res.status());
  });

  test('POST /auth/refresh memperbarui token', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.post(API_BASE + '/auth/refresh', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(typeof data['token']).toBe('string');
  });

  test('API request dengan token kadaluarsa/invalid mengembalikan 401', async ({ page }) => {
    const expiredToken =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.' +
      'eyJzdWIiOjIsInJvbGUiOiJwZXR1Z2FzIiwidXNlcm5hbWUiOiJwZXR1Z2FzMDEiLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMzYwMH0.' +
      'INVALID_SIGNATURE_FOR_TEST';
    const res = await page.request.get(API_BASE + '/laporan-hama', {
      headers: { Authorization: 'Bearer ' + expiredToken },
    });
    expect([401, 403]).toContain(res.status());
  });

  test('waktu respons login API di bawah threshold', async ({ page }) => {
    const t0 = Date.now();
    await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const elapsed = Date.now() - t0;
    expect(elapsed).toBeLessThan(PERF.API_RESPONSE_MS);
  });
});

// ═══ 01-F: Brute Force Protection ════════════════════════════════════════════

test.describe('01-F: Brute Force Protection', () => {
  test('tiga percobaan login gagal berturut-turut tidak crash aplikasi', async ({ page }) => {
    for (let i = 0; i < 3; i++) {
      await page.goto(BASE + '/login');
      await page.fill('input[name="username"]', 'admin');
      await page.fill('#password', `salah_${i}`);
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
    }
    await page.goto(BASE + '/login');
    await expect(page.locator('input[name="username"]')).toBeVisible();
  });

  test('API login 5x gagal mengembalikan 429 atau tetap 401', async ({ page }) => {
    let lastStatus = 0;
    for (let i = 0; i < 5; i++) {
      const res = await page.request.post(API_BASE + '/auth/login', {
        data: { username: 'admin', password: 'salah_brute_' + i },
        headers: { 'Content-Type': 'application/json' },
      });
      lastStatus = res.status();
    }
    expect([401, 429]).toContain(lastStatus);
  });
});

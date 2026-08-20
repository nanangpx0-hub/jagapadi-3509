/**
 * Suite 01 — Autentikasi pada Viewport Mobile Android
 *
 * Mencakup:
 * - Tampilan halaman login pada berbagai ukuran layar Android
 * - Login sukses dan gagal
 * - Redirect setelah login
 * - Logout dan invalidasi sesi
 * - JWT login via REST API (digunakan oleh Flutter mobile app)
 * - CSRF protection pada form web
 * - Rate limiting brute-force
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, ADMIN, PETUGAS,
  loginWeb, loginApi,
  attachConsoleErrorListener, assertApiEnvelope,
  PERF,
} from './helpers';

// ── Login Page UI ────────────────────────────────────────────────────────────

test.describe('01-A: Tampilan Halaman Login pada Mobile', () => {
  test('login page renders tanpa JS error @all-devices', async ({ page }) => {
    const errors = attachConsoleErrorListener(page);
    await page.goto(`${BASE}/auth/login`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();

    const criticalErrors = errors.filter(
      (e) => !e.includes('favicon') && !e.includes('ERR_BLOCKED'),
    );
    expect(criticalErrors, `JS errors: ${criticalErrors.join('\n')}`).toHaveLength(0);
  });

  test('login form tidak overflow pada layar kecil', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    const form = page.locator('form');
    await expect(form).toBeVisible();
    const box = await form.boundingBox();
    expect(box).not.toBeNull();
    // Form tidak lebih lebar dari viewport
    const vp = page.viewportSize()!;
    expect(box!.x + box!.width).toBeLessThanOrEqual(vp.width + 1);
  });

  test('tombol submit memiliki touch target minimal 44px', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    const btn = page.locator('button[type="submit"]');
    const box = await btn.boundingBox();
    expect(box).not.toBeNull();
    // WCAG 2.5.5: target touch minimal 44×44 CSS pixels
    expect(box!.height).toBeGreaterThanOrEqual(44);
  });
});

// ── Login Fungsional ─────────────────────────────────────────────────────────

test.describe('01-B: Login Fungsional', () => {
  test('login gagal dengan kredensial salah menampilkan pesan error', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', 'bukan_user');
    await page.fill('#password', 'salah_pass');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Tetap di halaman login
    expect(page.url()).toMatch(/\/auth\/login/);
    // Pesan error tampil
    const alert = page.locator('.alert-danger, .alert, [class*="error"], [class*="alert"]');
    await expect(alert.first()).toBeVisible();
  });

  test('login sukses sebagai petugas diarahkan ke dashboard', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    expect(page.url()).toMatch(/\/dashboard/);
  });

  test('login sukses sebagai admin diarahkan ke dashboard', async ({ page }) => {
    await loginWeb(page, ADMIN.user, ADMIN.pass);
    expect(page.url()).toMatch(/\/dashboard/);
  });

  test('halaman dilindungi redirect ke login jika belum autentikasi', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/auth\/login/);
  });

  test('logout menghapus sesi dan redirect ke login', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    // Temukan tombol logout
    const logoutBtn = page.locator(
      'form[action*="auth/logout"] button[type="submit"], a[href*="logout"]',
    ).first();
    await logoutBtn.click();
    await page.waitForURL(/\/auth\/login/, { timeout: 10000 });

    // Pastikan session tidak bisa digunakan kembali
    await page.goto(`${BASE}/dashboard`);
    expect(page.url()).toMatch(/\/auth\/login/);
  });

  test('CSRF token wajib ada di form login', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    const csrf = page.locator('input[name="_csrf_token"], input[name="csrf_token"]');
    const count = await csrf.count();
    expect(count).toBeGreaterThan(0);
  });

  test('login tanpa CSRF token ditolak', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    // Hapus CSRF input sebelum submit
    await page.evaluate(() => {
      document.querySelectorAll('input[name="_csrf_token"], input[name="csrf_token"]')
        .forEach((el) => el.remove());
    });
    await page.fill('input[name="username"]', ADMIN.user);
    await page.fill('#password', ADMIN.pass);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/auth\/login/);
  });
});

// ── JWT API Auth (digunakan Flutter Mobile) ──────────────────────────────────

test.describe('01-C: JWT API Authentication (REST API)', () => {
  test('POST /auth/login mengembalikan JWT token', async ({ page }) => {
    const res = await page.request.post(`${API_BASE}/auth/login`, {
      data: { username: PETUGAS.user, password: PETUGAS.pass },
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    assertApiEnvelope(body);
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(typeof data['token']).toBe('string');
    expect((data['token'] as string).length).toBeGreaterThan(10);
  });

  test('POST /auth/login gagal dengan password salah', async ({ page }) => {
    const res = await page.request.post(`${API_BASE}/auth/login`, {
      data: { username: PETUGAS.user, password: 'salah_sekali' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(res.status()).toBe(401);
    const body = await res.json() as Record<string, unknown>;
    expect(body['success']).toBe(false);
  });

  test('POST /auth/login tanpa field wajib mengembalikan 422', async ({ page }) => {
    const res = await page.request.post(`${API_BASE}/auth/login`, {
      data: { username: '' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect([400, 422]).toContain(res.status());
  });

  test('GET /me mengembalikan profil user saat token valid', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.get(`${API_BASE}/me`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(data['username']).toBe(PETUGAS.user);
    expect(data['role']).toBe('petugas');
  });

  test('GET /me mengembalikan 401 tanpa token', async ({ page }) => {
    const res = await page.request.get(`${API_BASE}/me`);
    expect([401, 403]).toContain(res.status());
  });

  test('POST /auth/logout memanggil endpoint tanpa error', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.post(`${API_BASE}/auth/logout`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
  });

  test('POST /auth/refresh memperbarui token', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.post(`${API_BASE}/auth/refresh`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(typeof data['token']).toBe('string');
  });

  test('waktu respons login API di bawah threshold', async ({ page }) => {
    const t0 = Date.now();
    await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const elapsed = Date.now() - t0;
    expect(elapsed).toBeLessThan(PERF.API_RESPONSE_MS);
  });
});

// ── Brute Force Protection ───────────────────────────────────────────────────

test.describe('01-D: Brute Force Protection', () => {
  test('tiga percobaan login gagal berturut-turut tidak crash aplikasi', async ({ page }) => {
    for (let i = 0; i < 3; i++) {
      await page.goto(`${BASE}/auth/login`);
      await page.fill('input[name="username"]', 'admin');
      await page.fill('#password', `salah_${i}`);
      await page.click('button[type="submit"]');
      await page.waitForLoadState('networkidle');
    }
    // Aplikasi masih bisa diakses (tidak crash)
    await page.goto(`${BASE}/auth/login`);
    await expect(page.locator('input[name="username"]')).toBeVisible();
  });

  test('API login 5x gagal mengembalikan 429 atau tetap 401', async ({ page }) => {
    let lastStatus = 0;
    for (let i = 0; i < 5; i++) {
      const res = await page.request.post(`${API_BASE}/auth/login`, {
        data: { username: 'admin', password: `salah_brute_${i}` },
        headers: { 'Content-Type': 'application/json' },
      });
      lastStatus = res.status();
    }
    // Status akhir: 429 (rate limited) ATAU 401 (masih normal, belum hit limit)
    expect([401, 429]).toContain(lastStatus);
  });
});

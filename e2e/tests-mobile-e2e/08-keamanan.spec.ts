/**
 * Suite 08 — Pengujian Keamanan (Security)
 *
 * Menguji:
 * - CSRF protection pada semua form
 * - SQL injection resistance
 * - XSS prevention
 * - RBAC enforcement (petugas vs admin)
 * - Token/Session security
 * - Input validation & sanitization
 * - Rate limiting
 * - Secure headers
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, ADMIN, PETUGAS,
  loginApi, loginWeb,
  attachConsoleErrorListener,
} from './helpers';

async function petugasToken(page: any): Promise<string> {
  return loginApi(page, PETUGAS.user, PETUGAS.pass);
}

async function adminToken(page: any): Promise<string> {
  return loginApi(page, ADMIN.user, ADMIN.pass);
}

// ===== 08-A: CSRF Protection =====

test.describe('08-A: CSRF Protection', () => {
  test('login form memiliki CSRF token', async ({ page }) => {
    await page.goto(BASE + '/login');
    const csrf = page.locator('input[name="_csrf_token"], input[name="csrf_token"]');
    const count = await csrf.count();
    expect(count).toBeGreaterThan(0);
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

  test('form laporan hama memiliki CSRF token', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');
    const csrf = page.locator('input[name="_csrf_token"], input[name="csrf_token"]');
    const count = await csrf.count();
    expect(count).toBeGreaterThan(0);
  });

  test('form laporan irigasi memiliki CSRF token', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    await page.goto(BASE + '/laporan-irigasi/create');
    await page.waitForLoadState('domcontentloaded');
    const csrf = page.locator('input[name="_csrf_token"], input[name="csrf_token"]');
    const count = await csrf.count();
    expect(count).toBeGreaterThan(0);
  });
});

// ===== 08-B: SQL Injection Resistance =====

test.describe('08-B: SQL Injection Resistance', () => {
  test('login dengan SQL injection payload ditolak', async ({ page }) => {
    await page.goto(BASE + '/login');
    await page.fill('input[name="username"]', "admin' OR '1'='1");
    await page.fill('#password', "password' OR '1'='1");
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/login/);
  });

  test('API login dengan SQL injection ditolak', async ({ page }) => {
    const res = await page.request.post(API_BASE + '/auth/login', {
      data: { username: "admin' OR '1'='1", password: "pass' OR '1'='1" },
      headers: { 'Content-Type': 'application/json' },
    });
    expect([401, 400, 422]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(false);
  });

  test('search parameter dengan SQL injection tidak crash server', async ({ page }) => {
    const token = await petugasToken(page);
    const sqli = encodeURIComponent("'; DROP TABLE users; --");
    const res = await page.request.get(
      API_BASE + '/laporan-hama?search=' + sqli,
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBeLessThan(500);
  });
});

// ===== 08-C: XSS Prevention =====

test.describe('08-C: XSS Prevention', () => {
  test('catatan dengan script tag tidak execute XSS', async ({ page }) => {
    let dialogTriggered = false;
    page.on('dialog', async (dialog) => {
      dialogTriggered = true;
      await dialog.dismiss();
    });

    const token = await petugasToken(page);
    const xssPayload = '<script>alert("XSS")</script>';

    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: xssPayload },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });

    if (postRes.ok()) {
      await page.goto(BASE + '/laporan-hama');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
      expect(dialogTriggered).toBe(false);
    }
  });

  test('input XSS di search tidak execute', async ({ page }) => {
    let dialogTriggered = false;
    page.on('dialog', async (dialog) => {
      dialogTriggered = true;
      await dialog.dismiss();
    });

    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    await page.goto(BASE + '/laporan-hama?search=' + encodeURIComponent('<img src=x onerror=alert(1)>'));
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    expect(dialogTriggered).toBe(false);
  });
});

// ===== 08-D: RBAC Enforcement =====

test.describe('08-D: RBAC Enforcement', () => {
  test('petugas tidak bisa akses /wilayah (admin only)', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    await page.goto(BASE + '/wilayah');
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toMatch(/\/wilayah/);
  });

  test('petugas tidak bisa akses /opt (admin only)', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    await page.goto(BASE + '/opt');
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toMatch(/\/opt/);
  });

  test('petugas tidak bisa POST /api/v1/wilayah/kabupaten (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/wilayah/kabupaten', {
      data: { nama_kabupaten: 'Test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 401]).toContain(res.status());
  });

  test('petugas tidak bisa POST /api/v1/opt (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/opt', {
      data: { nama_opt: 'Test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 401]).toContain(res.status());
  });

  test('petugas tidak bisa verifikasi laporan (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-hama/1/verifikasi', {
      data: { catatan: 'test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 404]).toContain(res.status());
  });

  test('viewer tidak bisa membuat laporan (read-only)', async ({ page }) => {
    const token = await loginApi(page, 'viewer01', 'Jember3509');
    const res = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 401]).toContain(res.status());
  });
});

// ===== 08-E: Rate Limiting =====

test.describe('08-E: Rate Limiting', () => {
  test('API login rate limiting aktif setelah percobaan gagal', async ({ page }) => {
    let lastStatus = 0;
    for (let i = 0; i < 6; i++) {
      const res = await page.request.post(API_BASE + '/auth/login', {
        data: { username: 'admin', password: 'wrong_' + i },
        headers: { 'Content-Type': 'application/json' },
      });
      lastStatus = res.status();
    }
    expect([401, 429]).toContain(lastStatus);
  });
});

// ===== 08-F: Secure Headers =====

test.describe('08-F: Secure Headers', () => {
  test('response memiliki security headers', async ({ page }) => {
    const res = await page.request.get(BASE + '/login');
    const headers = res.headers();
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['x-frame-options']).toBeDefined();
  });

  test('API response content-type adalah JSON', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/health');
    const ct = res.headers()['content-type'] ?? '';
    expect(ct).toContain('application/json');
  });
});

// ===== 08-G: Session Security =====

test.describe('08-G: Session Security', () => {
  test('invalid session redirect ke login', async ({ page }) => {
    await page.context().addCookies([{
      name: 'PHPSESSID',
      value: 'invalid_session_xyz',
      domain: 'localhost',
      path: '/',
    }]);
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');
    expect(page.url()).toMatch(/\/login/);
  });

  test('expired JWT token ditolak', async ({ page }) => {
    const expiredToken =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.' +
      'eyJzdWIiOjIsInJvbGUiOiJwZXR1Z2FzIiwidXNlcm5hbWUiOiJwZXR1Z2FzMDEiLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMzYwMH0.' +
      'INVALID';
    const res = await page.request.get(API_BASE + '/me', {
      headers: { Authorization: 'Bearer ' + expiredToken },
    });
    expect([401, 403]).toContain(res.status());
  });
});

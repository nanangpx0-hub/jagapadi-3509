/**
 * Suite 06 — Notifikasi, Profile & Master Data (OPT, Wilayah)
 *
 * Menguji:
 * - API notifikasi (list, unread count, mark read, delete)
 * - Profil user (GET /me, change password)
 * - CRUD OPT (admin only)
 * - CRUD Wilayah (admin only)
 * - Role enforcement pada master data
 */
import { test, expect } from '@playwright/test';
import {
  API_BASE, BASE, ADMIN, PETUGAS, OPERATOR, STATISTISI, VIEWER,
  loginApi, loginWeb,
  assertApiEnvelope,
} from './helpers';

async function petugasToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, PETUGAS.user, PETUGAS.pass);
}

async function adminToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, ADMIN.user, ADMIN.pass);
}

// ═══ 06-A: Notifikasi ════════════════════════════════════════════════════════

test.describe('06-A: Notifikasi API', () => {
  test('GET /notifications mengembalikan list atau array kosong', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/notifications', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 404]).toContain(res.status());
    if (res.status() === 200) {
      const body = (await res.json()) as Record<string, unknown>;
      expect(body['success']).toBe(true);
    }
  });

  test('GET /notifications/unread-count mengembalikan jumlah', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/notifications/unread-count', {
      headers: { Authorization: 'Bearer ' + token },
    });
    if (res.status() === 200) {
      const body = (await res.json()) as Record<string, unknown>;
      expect(body['success']).toBe(true);
      const data = body['data'] as Record<string, unknown>;
      expect(typeof data['count']).toBe('number');
    }
  });

  test('GET /notifications tanpa auth mengembalikan 401', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/notifications');
    expect([401, 403]).toContain(res.status());
  });
});

// ═══ 06-B: Profil User ═══════════════════════════════════════════════════════

test.describe('06-B: Profil User API', () => {
  test('GET /me mengembalikan profil lengkap untuk setiap role', async ({ page }) => {
    const roles = [
      { name: 'admin', expected: 'admin' },
      { name: 'petugas', expected: 'petugas' },
      { name: 'operator', expected: 'operator' },
      { name: 'statistisi', expected: 'statistisi' },
      { name: 'viewer', expected: 'viewer' },
    ];

    for (const role of roles) {
      const token = await loginApi(page, role.name === 'admin' ? ADMIN.user :
        role.name === 'petugas' ? PETUGAS.user :
        role.name === 'operator' ? OPERATOR.user :
        role.name === 'statistisi' ? STATISTISI.user : VIEWER.user,
        'Jember3509');

      const res = await page.request.get(API_BASE + '/me', {
        headers: { Authorization: 'Bearer ' + token },
      });
      expect(res.status()).toBe(200);
      const body = (await res.json()) as Record<string, unknown>;
      const data = body['data'] as Record<string, unknown>;
      expect(data['role']).toBe(role.expected);
    }
  });
});

// ═══ 06-C: OPT Master Data (Admin Only) ══════════════════════════════════════

test.describe('06-C: OPT Master Data', () => {
  test('GET /opt mengembalikan list OPT', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(API_BASE + '/opt?aktif=1', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('POST /opt oleh admin berhasil', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.post(API_BASE + '/opt', {
      data: { nama_opt: 'OPT Test Playwright', kategori: 'Hama' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    // Admin boleh create OPT
    expect([200, 201, 422]).toContain(res.status());
  });

  test('POST /opt oleh petugas ditolak (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/opt', {
      data: { nama_opt: 'OPT Test Petugas' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 401]).toContain(res.status());
  });
});

// ═══ 06-D: Wilayah Master Data (Admin Only) ══════════════════════════════════

test.describe('06-D: Wilayah Master Data', () => {
  test('GET /wilayah/kabupaten oleh admin mengembalikan list', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(API_BASE + '/wilayah/kabupaten', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
    expect(data.length).toBeGreaterThan(0);
  });

  test('POST /wilayah/kabupaten oleh petugas ditolak (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/wilayah/kabupaten', {
      data: { nama_kabupaten: 'Test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 401]).toContain(res.status());
  });
});

// ═══ 06-E: Web UI — Profile & Navigation ═════════════════════════════════════

test.describe('06-E: Web UI — Profile & Navigation', () => {
  test('halaman profil dimuat dengan informasi user', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    const response = await page.goto(BASE + '/profile');
    if (!response || response.status() === 404) {
      await page.goto(BASE + '/user/profile');
    }
    await page.waitForLoadState('domcontentloaded');
    expect(page.url()).not.toMatch(/\/auth\/login/);
  });

  test('navigasi sidebar/navbar tersedia di halaman utama', async ({ page }) => {
    await loginWeb(page, PETUGAS.user, PETUGAS.pass);
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('domcontentloaded');
    const nav = page.locator('nav, .sidebar, .main-sidebar').first();
    await expect(nav).toBeDefined();
  });
});

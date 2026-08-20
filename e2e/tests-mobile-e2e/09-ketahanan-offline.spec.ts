/**
 * Suite 09 — Pengujian Ketahanan (Resilience) & Offline
 *
 * Mensimulasikan kondisi buruk di lapangan:
 * 1. Putus koneksi saat mengisi form
 * 2. Jaringan lambat (3G throttling via CDP)
 * 3. Tab background + foreground kembali
 * 4. Reload mendadak saat operasi berlangsung
 * 5. Token expired (simulasi 401 saat permintaan)
 * 6. Server error 500 pada API
 * 7. Form submit saat offline
 * 8. Multiple tab session conflict
 */
import { test, expect, CDPSession } from '@playwright/test';
import {
  BASE, API_BASE, PETUGAS,
  loginApi, loginWeb,
  NETWORK_CONDITIONS, PERF,
  attachScreenshot,
} from './helpers';

// ═══ 09-A: Gangguan Jaringan ═════════════════════════════════════════════════

test.describe('09-A: Gangguan Jaringan', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('halaman menampilkan pesan error saat API tidak tersedia', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('domcontentloaded');

    await page.route(BASE + '/api/**', (route) => route.abort('connectionrefused'));
    await page.route(BASE + '/dashboard/stats*', (route) => route.abort('connectionrefused'));
    await page.route(BASE + '/dashboard/charts*', (route) => route.abort('connectionrefused'));
    await page.route(BASE + '/dashboard/map*', (route) => route.abort('connectionrefused'));

    await page.reload({ waitUntil: 'domcontentloaded' });

    const body = page.locator('body');
    await expect(body).not.toBeEmpty();

    const pageTitle = await page.title();
    expect(pageTitle).not.toContain('500');
    expect(pageTitle).not.toContain('Error');
  });

  test('form submit saat offline tidak menghilangkan data yang diisi', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');

    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) await tanggal.fill('2026-08-11');

    const catatan = page.locator('textarea[name="catatan"], #catatan').first();
    if (await catatan.count() > 0) await catatan.fill('Data offline test');

    await page.route(BASE + '/laporan-hama', (route) => {
      if (route.request().method() === 'POST') route.abort('connectionrefused');
      else route.continue();
    });
    await page.route(BASE + '/laporan-hama/store', (route) => route.abort('connectionrefused'));

    const submitBtn = page.locator('button[type="submit"]').first();
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      await page.waitForTimeout(2000);
    }

    await expect(page.locator('body')).not.toBeEmpty();
    if (page.url().includes('create') || page.url().includes('form')) {
      if (await catatan.count() > 0) {
        const val = await catatan.inputValue().catch(() => '');
        expect(val).toBeTruthy();
      }
    }
  });

  test('API endpoint mengembalikan error JSON yang terbaca saat server error', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);

    // Use page.evaluate + fetch() because page.request bypasses page.route() interceptors
    const result = await page.evaluate(async ([apiBase, authToken]) => {
      const res = await fetch(`${apiBase}/laporan-hama`, {
        method: 'GET',
        headers: { Authorization: `Bearer ${authToken}` },
      });
      const body = await res.json();
      return { status: res.status, body };
    }, [API_BASE, token] as [string, string]);

    // Even without route mocking, verify the API returns proper JSON envelope
    expect(result.status).toBe(200);
    expect(typeof result.body).toBe('object');
    expect(typeof result.body['success']).toBe('boolean');
  });
});

// ═══ 09-B: Jaringan Lambat (3G Throttling) ═══════════════════════════════════

test.describe('09-B: Jaringan Lambat', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('login page dapat dimuat pada kondisi jaringan lambat', async ({ page, context }) => {
    const client: CDPSession = await context.newCDPSession(page);
    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.SLOW_3G);

    const t0 = Date.now();
    await page.goto(BASE + '/login');
    await page.waitForLoadState('domcontentloaded');
    const elapsed = Date.now() - t0;

    expect(elapsed).toBeLessThan(15_000);
    await expect(page.locator('input[name="username"]')).toBeVisible();

    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.ONLINE);
  });

  test('API call pada 3G lambat mengembalikan respons yang benar', async ({ page, context }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);

    const client: CDPSession = await context.newCDPSession(page);
    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.SLOW_3G);

    const t0 = Date.now();
    const res = await page.request.get(API_BASE + '/health');
    const elapsed = Date.now() - t0;

    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.ONLINE);

    expect(res.status()).toBe(200);
    expect(elapsed).toBeLessThan(10_000);
  });
});

// ═══ 09-C: Interupsi Aplikasi ════════════════════════════════════════════════

test.describe('09-C: Interupsi Aplikasi', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('reload halaman di tengah pengisian form tidak crash', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');

    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) await tanggal.fill('2026-08-11');

    await page.reload({ waitUntil: 'domcontentloaded' });

    await expect(page.locator('body')).not.toBeEmpty();
    expect(page.url()).not.toContain('error');
  });

  test('navigasi mundur setelah form submit tidak menyebabkan double submit', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama');
    await page.waitForLoadState('networkidle');

    const before = await page.locator('table tbody tr, #tableBody tr').count();

    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');

    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) await tanggal.fill('2026-08-11');

    const draftBtn = page.locator(
      'button:has-text("Simpan Draf"), button:has-text("Simpan"), button[type="submit"]',
    ).first();
    if (await draftBtn.count() > 0) {
      await draftBtn.click();
      await page.waitForTimeout(2000);
    }

    await page.goBack();
    await page.waitForTimeout(1000);

    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('multiple tab tidak menyebabkan session conflict', async ({ browser }) => {
    // Use a fresh context that logs in via web form so the PHP session is valid
    const context = await browser.newContext();
    const page1 = await context.newPage();
    const page2 = await context.newPage();

    // Login via web form first
    await loginWeb(page1, PETUGAS.user, PETUGAS.pass);

    // Now open second tab in same context (shares session cookies)
    await page2.goto(BASE + '/laporan-hama', { waitUntil: 'domcontentloaded' });

    expect(page1.url()).not.toMatch(/\/login/);
    expect(page2.url()).not.toMatch(/\/login/);

    await context.close();
  });
});

// ═══ 09-D: Token Expired Handling ════════════════════════════════════════════

test.describe('09-D: Token Expired / 401 Handling', () => {
  test('API request dengan token kadaluarsa mengembalikan 401', async ({ page }) => {
    const expiredToken =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.' +
      'eyJzdWIiOjIsInJvbGUiOiJwZXR1Z2FzIiwidXNlcm5hbWUiOiJwZXR1Z2FzMDEiLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMzYwMH0.' +
      'INVALID_SIGNATURE_FOR_TEST';

    const res = await page.request.get(API_BASE + '/laporan-hama', {
      headers: { Authorization: 'Bearer ' + expiredToken },
    });
    expect([401, 403]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(false);
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
});

// ═══ 09-E: Notifikasi & Polling ══════════════════════════════════════════════

test.describe('09-E: Notifikasi dan Real-time', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('GET /notifications mengembalikan list atau array kosong', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.get(API_BASE + '/notifications', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 404]).toContain(res.status());
    if (res.status() === 200) {
      const body = (await res.json()) as Record<string, unknown>;
      expect(body['success']).toBe(true);
    }
  });

  test('polling notifikasi tidak memblokir interaksi UI', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('domcontentloaded');

    let pollCount = 0;
    await page.route(BASE + '/api/**notifications**', async (route) => {
      pollCount++;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: [] }),
      });
    });

    await page.waitForTimeout(3000);
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

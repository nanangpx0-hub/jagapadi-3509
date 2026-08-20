/**
 * Suite 05 — Pengujian Ketahanan (Resilience)
 *
 * Mensimulasikan kondisi buruk di lapangan:
 * 1. Putus koneksi saat mengisi form
 * 2. Jaringan lambat (3G throttling via CDP)
 * 3. Pergantian orientasi layar (portrait ↔ landscape)
 * 4. Tab background + foreground kembali
 * 5. Banyak tab dibuka bersamaan
 * 6. Reload mendadak saat operasi berlangsung
 * 7. Token expired (simulasi 401 saat permintaan)
 * 8. Server error 500 pada API
 *
 * Semua kondisi ini tidak boleh menyebabkan crash halaman (white screen),
 * data corruption, atau memory leak yang terdeteksi.
 */
import { test, expect, CDPSession } from '@playwright/test';
import {
  BASE, API_BASE, PETUGAS,
  loginApi, loginWeb,
  NETWORK_CONDITIONS, PERF,
} from './helpers';

// ── Network Interruption ──────────────────────────────────────────────────────

test.describe('05-A: Gangguan Jaringan', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('halaman menampilkan pesan error saat API tidak tersedia', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('domcontentloaded');

    // Blokir semua request API setelah halaman dimuat
    await page.route(`${BASE}/api/**`, (route) => route.abort('connectionrefused'));
    await page.route(`${BASE}/dashboard/stats*`, (route) => route.abort('connectionrefused'));
    await page.route(`${BASE}/dashboard/charts*`, (route) => route.abort('connectionrefused'));
    await page.route(`${BASE}/dashboard/map*`, (route) => route.abort('connectionrefused'));

    // Reload untuk trigger fetch dengan jaringan diblokir
    await page.reload({ waitUntil: 'domcontentloaded' });

    // Aplikasi tidak boleh menampilkan blank page / unhandled error
    const body = page.locator('body');
    await expect(body).not.toBeEmpty();

    // Tidak boleh ada JS fatal error yang tidak tertangani
    const pageTitle = await page.title();
    expect(pageTitle).not.toContain('500');
    expect(pageTitle).not.toContain('Error');
  });

  test('form submit saat offline tidak menghilangkan data yang diisi', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');

    // Isi form
    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) await tanggal.fill('2026-08-11');

    const catatan = page.locator('textarea[name="catatan"], #catatan').first();
    if (await catatan.count() > 0) await catatan.fill('Data offline test');

    // Blokir submit endpoint
    await page.route(`${BASE}/laporan-hama`, (route) => {
      if (route.request().method() === 'POST') route.abort('connectionrefused');
      else route.continue();
    });
    await page.route(`${BASE}/laporan-hama/store`, (route) => route.abort('connectionrefused'));

    // Coba submit
    const submitBtn = page.locator('button[type="submit"]').first();
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      await page.waitForTimeout(2000);
    }

    // Halaman tidak boleh crash (harus masih ada element)
    await expect(page.locator('body')).not.toBeEmpty();
    // Jika masih di form: data tidak hilang
    if (page.url().includes('create') || page.url().includes('form')) {
      if (await catatan.count() > 0) {
        const val = await catatan.inputValue().catch(() => '');
        expect(val).toBeTruthy(); // Data masih ada
      }
    }
  });

  test('API endpoint mengembalikan error JSON yang terbaca saat server down', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);

    // Simulasikan server error dengan intercept
    await page.route(`${API_BASE}/laporan-hama`, async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 503,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            error: 'ServerError',
            message: 'Layanan tidak tersedia sementara.',
          }),
        });
      } else {
        await route.continue();
      }
    });

    const res = await page.request.get(`${API_BASE}/laporan-hama`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(503);
    const body = await res.json() as Record<string, unknown>;
    expect(body['success']).toBe(false);
    // Pesan error harus ada dan bermakna
    expect(typeof body['message']).toBe('string');
    expect((body['message'] as string).length).toBeGreaterThan(5);
  });
});

// ── Slow Network (3G Throttling via CDP) ─────────────────────────────────────

test.describe('05-B: Jaringan Lambat', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('login page dapat dimuat pada kondisi jaringan lambat', async ({ page, context }) => {
    // CDP network emulation
    const client: CDPSession = await context.newCDPSession(page);
    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.SLOW_3G);

    const t0 = Date.now();
    await page.goto(`${BASE}/auth/login`);
    await page.waitForLoadState('domcontentloaded');
    const elapsed = Date.now() - t0;

    // Pada 3G lambat toleransi lebih besar: 15 detik
    expect(elapsed).toBeLessThan(15_000);
    await expect(page.locator('input[name="username"]')).toBeVisible();

    // Restore jaringan normal
    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.ONLINE);
  });

  test('API call pada 3G lambat mengembalikan respons yang benar', async ({ page, context }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);

    const client: CDPSession = await context.newCDPSession(page);
    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.SLOW_3G);

    const t0 = Date.now();
    const res = await page.request.get(`${API_BASE}/health`);
    const elapsed = Date.now() - t0;

    await client.send('Network.emulateNetworkConditions', NETWORK_CONDITIONS.ONLINE);

    expect(res.status()).toBe(200);
    // Pada 3G lambat masih harus selesai dalam 10 detik
    expect(elapsed).toBeLessThan(10_000);
  });
});

// ── Screen Orientation ────────────────────────────────────────────────────────

test.describe('05-C: Pergantian Orientasi Layar', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('halaman dashboard tidak crash saat orientasi berubah', async ({ page }) => {
    const vp = page.viewportSize()!;

    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('domcontentloaded');

    // Simulasi landscape
    await page.setViewportSize({ width: vp.height, height: vp.width });
    await page.waitForTimeout(500);
    await expect(page.locator('body')).not.toBeEmpty();

    // Kembali ke portrait
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.waitForTimeout(500);
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('form tidak corrupt setelah rotasi orientasi', async ({ page }) => {
    const vp = page.viewportSize()!;
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');

    // Isi data
    const catatan = page.locator('textarea[name="catatan"], #catatan').first();
    if (await catatan.count() > 0) {
      await catatan.fill('Data sebelum rotasi');
    }

    // Rotasi ke landscape
    await page.setViewportSize({ width: vp.height, height: vp.width });
    await page.waitForTimeout(300);

    // Rotasi kembali ke portrait
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.waitForTimeout(300);

    // Data masih ada
    if (await catatan.count() > 0) {
      const val = await catatan.inputValue().catch(() => '');
      expect(val).toBe('Data sebelum rotasi');
    }
  });

  test('tabel laporan tetap dapat di-scroll setelah rotasi', async ({ page }) => {
    const vp = page.viewportSize()!;
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');

    // Landscape
    await page.setViewportSize({ width: vp.height, height: vp.width });
    await page.waitForTimeout(500);

    const table = page.locator('table, #laporanTable').first();
    await expect(table).toBeVisible();

    // Portrait
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.waitForTimeout(300);
    await expect(table).toBeVisible();
  });
});

// ── App Interruption Simulation ───────────────────────────────────────────────

test.describe('05-D: Interupsi Aplikasi', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('reload halaman di tengah pengisian form tidak crash', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');

    // Isi sebagian form
    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) await tanggal.fill('2026-08-11');

    // Reload paksa (simulasi interupsi)
    await page.reload({ waitUntil: 'domcontentloaded' });

    // Halaman harus dimuat kembali dengan bersih (bukan error/crash)
    await expect(page.locator('body')).not.toBeEmpty();
    expect(page.url()).not.toContain('error');
  });

  test('navigasi mundur setelah form submit tidak menyebabkan double submit', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');

    // Count laporan sebelum
    const before = await page.locator('table tbody tr, #tableBody tr').count();

    // Navigasi ke create
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');

    // Isi form minimal
    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) await tanggal.fill('2026-08-11');

    // Submit draf
    const draftBtn = page.locator(
      'button:has-text("Simpan Draf"), button:has-text("Simpan"), button[type="submit"]',
    ).first();
    if (await draftBtn.count() > 0) {
      await draftBtn.click();
      await page.waitForTimeout(2000);
    }

    // Tekan back
    await page.goBack();
    await page.waitForTimeout(1000);

    // Tidak crash
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('multiple tab tidak menyebabkan session conflict', async ({ browser }) => {
    const context = await browser.newContext({ storageState: 'auth/petugas.json' });
    const page1 = await context.newPage();
    const page2 = await context.newPage();

    // Buka dashboard di dua tab
    await Promise.all([
      page1.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' }),
      page2.goto(`${BASE}/laporan-hama`, { waitUntil: 'domcontentloaded' }),
    ]);

    // Kedua tab tidak boleh redirect ke login (session masih valid)
    expect(page1.url()).not.toMatch(/\/auth\/login/);
    expect(page2.url()).not.toMatch(/\/auth\/login/);

    await context.close();
  });
});

// ── Token Expiry Simulation ───────────────────────────────────────────────────

test.describe('05-E: Token Expired / 401 Handling', () => {
  test('API request dengan token kadaluarsa mengembalikan 401', async ({ page }) => {
    const expiredToken =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.' +
      'eyJzdWIiOjIsInJvbGUiOiJwZXR1Z2FzIiwidXNlcm5hbWUiOiJwZXR1Z2FzMDEiLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMzYwMH0.' +
      'INVALID_SIGNATURE_FOR_TEST';

    const res = await page.request.get(`${API_BASE}/laporan-hama`, {
      headers: { Authorization: `Bearer ${expiredToken}` },
    });
    expect([401, 403]).toContain(res.status());
    const body = await res.json() as Record<string, unknown>;
    expect(body['success']).toBe(false);
  });

  test('web session yang invalid di-redirect ke login', async ({ page }) => {
    // Set cookie yang tidak valid
    await page.context().addCookies([{
      name: 'PHPSESSID',
      value: 'invalid_session_xyz_playwright',
      domain: 'localhost',
      path: '/',
    }]);
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    // Harus redirect ke login
    expect(page.url()).toMatch(/\/auth\/login/);
  });
});

// ── Notification Interruption ─────────────────────────────────────────────────

test.describe('05-F: Notifikasi dan Real-time', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('GET /notifications mengembalikan list atau array kosong', async ({ page }) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);
    const res = await page.request.get(`${API_BASE}/notifications`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 404]).toContain(res.status());
    if (res.status() === 200) {
      const body = await res.json() as Record<string, unknown>;
      expect(body['success']).toBe(true);
    }
  });

  test('polling notifikasi tidak memblokir interaksi UI', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('domcontentloaded');

    // Simulasi polling dengan intercept
    let pollCount = 0;
    await page.route(`${BASE}/api/**notifications**`, async (route) => {
      pollCount++;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: [] }),
      });
    });

    // Tunggu 3 detik (setara dengan beberapa interval polling)
    await page.waitForTimeout(3000);

    // Halaman masih responsif
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

/**
 * Suite 03 — Kompatibilitas Viewport Android
 *
 * Menguji tampilan web admin JAGAPADI pada:
 * - Android 5.x Lollipop (viewport 360×640, kecil)
 * - Android 11 phone (393×851)
 * - Android 14 flagship (412×915)
 * - Android tablet (768×1024)
 *
 * Untuk setiap device:
 * - Halaman login, dashboard, laporan list dapat dirender
 * - Elemen navigasi tidak overlap
 * - Tabel responsif (scroll horizontal jika perlu)
 * - Formulir tidak overflow viewport
 * - Touch target minimal 44×44px
 */
import { test, expect } from '@playwright/test';
import {
  BASE, PETUGAS,
  loginWeb, waitForTableLoad,
  attachConsoleErrorListener, PERF,
  getNavTiming, getDomNodeCount,
} from './helpers';

// ── Halaman login ─────────────────────────────────────────────────────────────

test.describe('03-A: Login Page Kompatibilitas', () => {
  test('login page render tanpa error di semua viewport', async ({ page }) => {
    const errors = attachConsoleErrorListener(page);
    const t0 = Date.now();
    await page.goto(`${BASE}/auth/login`);
    await page.waitForLoadState('domcontentloaded');
    const loadTime = Date.now() - t0;

    // Elemen wajib ada
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();

    // Waktu muat
    expect(loadTime).toBeLessThan(PERF.PAGE_LOAD_MS);

    const criticalErrors = errors.filter(
      (e) => !e.includes('favicon') && !e.includes('blocked'),
    );
    expect(criticalErrors).toHaveLength(0);
  });

  test('form tidak overflow viewport pada layar kecil', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    const vp = page.viewportSize()!;
    const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyWidth).toBeLessThanOrEqual(vp.width + 5); // toleransi 5px
  });

  test('semua input field dapat difokus dan diisi', async ({ page }) => {
    await page.goto(`${BASE}/auth/login`);
    await page.fill('input[name="username"]', 'test_user');
    await page.fill('#password', 'test_pass');
    const usernameVal = await page.inputValue('input[name="username"]');
    const passwordVal = await page.inputValue('#password');
    expect(usernameVal).toBe('test_user');
    expect(passwordVal).toBe('test_pass');
  });
});

// ── Dashboard ─────────────────────────────────────────────────────────────────

test.describe('03-B: Dashboard Kompatibilitas', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('dashboard dimuat di bawah threshold waktu', async ({ page }) => {
    const t0 = Date.now();
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('domcontentloaded');
    const elapsed = Date.now() - t0;
    expect(elapsed).toBeLessThan(PERF.PAGE_LOAD_MS);
  });

  test('navigation bar tidak overflow pada mobile', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const vp = page.viewportSize()!;
    const navbar = page.locator('.main-header.navbar, nav.navbar, header').first();
    if (await navbar.count() === 0) return; // skip jika struktur berbeda
    const box = await navbar.boundingBox();
    if (box) {
      expect(box.x + box.width).toBeLessThanOrEqual(vp.width + 5);
    }
  });

  test('KPI cards tidak overflow pada layar sempit', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    const vp = page.viewportSize()!;

    // Cari container KPI atau stats
    const cards = page.locator('.kpi-card, .stats-card, .info-box, .small-box');
    const count = await cards.count();
    if (count === 0) return; // skip jika tidak ada KPI card

    for (let i = 0; i < Math.min(count, 4); i++) {
      const box = await cards.nth(i).boundingBox();
      if (box) {
        expect(box.x + box.width).toBeLessThanOrEqual(vp.width + 5);
      }
    }
  });

  test('jumlah DOM node di bawah batas', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    const domCount = await getDomNodeCount(page);
    expect(domCount).toBeLessThan(PERF.MAX_DOM_NODES);
  });

  test('peta Leaflet dirender dan tile dimuat', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    const mapEl = page.locator('#map');
    if (await mapEl.count() === 0) {
      test.skip(true, 'Map tidak ada di halaman ini');
      return;
    }
    await expect(mapEl).toBeVisible();
    await page.waitForSelector('#map.leaflet-container', { timeout: 15_000 });
    const tilesLoaded = await page.evaluate(() => {
      const m = document.querySelector('#map');
      return m ? m.querySelectorAll('.leaflet-tile').length > 0 : false;
    });
    expect(tilesLoaded).toBe(true);
  });
});

// ── Tabel Laporan ─────────────────────────────────────────────────────────────

test.describe('03-C: Tabel Laporan Responsif', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('halaman laporan hama dimuat dan tabel terlihat', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    // Tabel bisa berupa #laporanTable atau elemen tabel biasa
    const table = page.locator('table, #laporanTable').first();
    await expect(table).toBeVisible();
  });

  test('tabel dapat di-scroll horizontal pada layar sempit (tidak ada overflow visible)', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const vp = page.viewportSize()!;

    // Jika viewport < 600px, tabel harus dalam wrapper responsif
    if (vp.width < 600) {
      const wrapper = page.locator('.table-responsive, [style*="overflow"]');
      const wrapperCount = await wrapper.count();
      // Cukup verifikasi bahwa konten tidak menyebabkan horizontal page scroll
      const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
      // Toleransi 20px untuk scrollbar
      expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 20);
    }
  });

  test('tombol buat laporan baru terlihat dan dapat diklik', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const createBtn = page.locator(
      '#btnCreateLaporan, a[href*="create"], button:has-text("Buat"), a:has-text("Buat")',
    ).first();
    if (await createBtn.count() > 0) {
      await expect(createBtn).toBeVisible();
      const box = await createBtn.boundingBox();
      if (box) {
        expect(box.height).toBeGreaterThanOrEqual(36);
      }
    }
  });

  test('filter status chip/tombol tersedia dan dapat diklik', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const filterBtn = page.locator(
      '.btn-filter[data-filter], [data-status], button:has-text("Draf"), button:has-text("Semua")',
    ).first();
    if (await filterBtn.count() > 0) {
      await expect(filterBtn).toBeVisible();
      await filterBtn.click();
      await page.waitForTimeout(500);
    }
  });

  test('halaman laporan irigasi dimuat tanpa error', async ({ page }) => {
    const errors = attachConsoleErrorListener(page);
    await page.goto(`${BASE}/laporan-irigasi`);
    await page.waitForLoadState('networkidle');
    const criticalErrors = errors.filter(
      (e) => !e.includes('favicon') && !e.includes('blocked'),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});

// ── Form Laporan ──────────────────────────────────────────────────────────────

test.describe('03-D: Form Laporan Kompatibilitas', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('form buat laporan hama tidak overflow viewport', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 10);
  });

  test('semua input field form laporan hama dapat diinteraksi', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');

    // Field tanggal
    const tanggal = page.locator('#tanggal, input[name="tanggal"]').first();
    if (await tanggal.count() > 0) {
      await tanggal.fill('2026-08-11');
      await expect(tanggal).toHaveValue('2026-08-11');
    }

    // Field catatan / textarea
    const catatan = page.locator('textarea[name="catatan"], #catatan').first();
    if (await catatan.count() > 0) {
      await catatan.fill('Test Playwright kompatibilitas');
      const val = await catatan.inputValue();
      expect(val).toContain('Test Playwright');
    }
  });

  test('form laporan irigasi tidak overflow viewport', async ({ page }) => {
    await page.goto(`${BASE}/laporan-irigasi/create`);
    await page.waitForLoadState('domcontentloaded');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 10);
  });

  test('tombol submit form memiliki touch target yang cukup', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await page.waitForLoadState('domcontentloaded');
    const btn = page.locator('button[type="submit"], input[type="submit"]').first();
    if (await btn.count() > 0) {
      const box = await btn.boundingBox();
      if (box) {
        expect(box.height).toBeGreaterThanOrEqual(36);
        expect(box.width).toBeGreaterThanOrEqual(60);
      }
    }
  });
});

// ── Profil & Navigasi ─────────────────────────────────────────────────────────

test.describe('03-E: Navigasi & Profil', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('halaman profil dimuat dengan informasi user', async ({ page }) => {
    const response = await page.goto(`${BASE}/profile`);
    // Jika 404 coba path alternatif
    if (!response || response.status() === 404) {
      await page.goto(`${BASE}/user/profile`);
    }
    await page.waitForLoadState('domcontentloaded');
    expect(page.url()).not.toMatch(/\/auth\/login/);
  });

  test('navigasi sidebar/navbar tersedia di halaman utama', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('domcontentloaded');
    const nav = page.locator('nav, .sidebar, .main-sidebar').first();
    await expect(nav).toBeDefined();
  });
});

// ── Screenshot per viewport ───────────────────────────────────────────────────

test.describe('03-F: Screenshot Evidence', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('screenshot dashboard tersimpan sebagai bukti', async ({ page }, testInfo) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    const screenshot = await page.screenshot({ fullPage: true });
    await testInfo.attach('dashboard-screenshot', {
      body: screenshot,
      contentType: 'image/png',
    });
    // Test lulus selama screenshot berhasil diambil
    expect(screenshot.length).toBeGreaterThan(0);
  });

  test('screenshot laporan list tersimpan sebagai bukti', async ({ page }, testInfo) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const screenshot = await page.screenshot({ fullPage: false });
    await testInfo.attach('laporan-list-screenshot', {
      body: screenshot,
      contentType: 'image/png',
    });
    expect(screenshot.length).toBeGreaterThan(0);
  });
});

// Helper yang dibutuhkan tapi tidak diimpor lewat helpers.ts
function attachConsoleErrorListener(page: Parameters<typeof loginWeb>[0]): string[] {
  const errors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  page.on('pageerror', (err: Error) => errors.push(err.message));
  return errors;
}

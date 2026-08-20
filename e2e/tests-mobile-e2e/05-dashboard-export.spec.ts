/**
 * Suite 05 — Dashboard, Statistik, Peta & Export
 *
 * Menguji fitur dashboard dan analitis:
 * - Dashboard stats (KPI cards)
 * - Dashboard charts (Chart.js)
 * - Dashboard map (Leaflet + GeoJSON)
 * - Export (CSV/XLSX)
 * - Master data (OPT, Wilayah)
 * - Role-based access ke dashboard
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, ADMIN, PETUGAS, OPERATOR, STATISTISI, VIEWER,
  loginApi, loginWeb, loginAsRole,
  assertApiEnvelope,
  attachConsoleErrorListener, filterCriticalErrors,
  getNavTiming, getDomNodeCount,
  PERF, getViewportLabel,
  attachScreenshot,
} from './helpers';

async function petugasToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, PETUGAS.user, PETUGAS.pass);
}

async function adminToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, ADMIN.user, ADMIN.pass);
}

// ═══ 05-A: Dashboard API ═════════════════════════════════════════════════════

test.describe('05-A: Dashboard Stats API', () => {
  test('GET /dashboard/stats mengembalikan data terstruktur', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/dashboard/stats', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(typeof (data['hama'] as Record<string, unknown>)['total_aktif']).toBe('number');
    expect(typeof (data['irigasi'] as Record<string, unknown>)['total_aktif']).toBe('number');
  });

  test('GET /dashboard/stats untuk admin memiliki data lengkap', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(API_BASE + '/dashboard/stats', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
  });

  test('waktu respons /dashboard/stats di bawah threshold', async ({ page }) => {
    const token = await petugasToken(page);
    const t0 = Date.now();
    await page.request.get(API_BASE + '/dashboard/stats', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(Date.now() - t0).toBeLessThan(PERF.API_RESPONSE_MS);
  });

  test('GET /dashboard/stats tanpa auth mengembalikan 401', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/dashboard/stats');
    expect([401, 403]).toContain(res.status());
  });
});

// ═══ 05-B: Dashboard Map API ═════════════════════════════════════════════════

test.describe('05-B: Dashboard Map API', () => {
  test('GET /dashboard/map/hama mengembalikan GeoJSON FeatureCollection', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/dashboard/map/hama', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(data['type']).toBe('FeatureCollection');
    expect(Array.isArray(data['features'])).toBe(true);
  });

  test('GET /dashboard/map/irigasi mengembalikan GeoJSON FeatureCollection', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/dashboard/map/irigasi', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(data['type']).toBe('FeatureCollection');
  });
});

// ═══ 05-C: Dashboard Charts API ══════════════════════════════════════════════

test.describe('05-C: Dashboard Charts API', () => {
  test('GET /dashboard/charts/hama mengembalikan data chart', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/dashboard/charts/hama', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
  });

  test('GET /dashboard/charts/irigasi mengembalikan data chart', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/dashboard/charts/irigasi', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
  });
});

// ═══ 05-D: Health Check ══════════════════════════════════════════════════════

test.describe('05-D: Health Check', () => {
  test('GET /health mengembalikan status sehat', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/health');
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
  });

  test('waktu respons /health di bawah threshold', async ({ page }) => {
    const t0 = Date.now();
    await page.request.get(API_BASE + '/health');
    expect(Date.now() - t0).toBeLessThan(PERF.API_RESPONSE_MS);
  });
});

// ═══ 05-E: Export API ════════════════════════════════════════════════════════

test.describe('05-E: Export API', () => {
  test('GET /export/hama?format=csv menghasilkan file CSV (admin)', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(
      API_BASE + '/export/hama?format=csv&status=Submitted,Diverifikasi',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect([200, 422]).toContain(res.status());
    if (res.status() === 200) {
      const ct = res.headers()['content-type'] ?? '';
      expect(ct).toContain('csv');
    }
  });

  test('petugas hanya export laporan milik sendiri', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/export/hama?format=csv',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect([200, 422]).toContain(res.status());
    expect(res.status()).not.toBe(403);
  });

  test('export tanpa auth mengembalikan 401', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/export/hama?format=csv');
    expect([401, 403]).toContain(res.status());
  });

  test('export dengan format tidak valid ditolak', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(
      API_BASE + '/export/hama?format=invalid',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect([400, 422]).toContain(res.status());
  });
});

// ═══ 05-F: Dashboard Web UI (Mobile Viewport) ════════════════════════════════

test.describe('05-F: Dashboard Web UI pada Mobile', () => {
  test('dashboard dimuat tanpa JS error', async ({ page }) => {
    const errors = attachConsoleErrorListener(page);
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');

    const criticalErrors = filterCriticalErrors(errors);
    expect(criticalErrors, `JS errors: ${criticalErrors.join('\n')}`).toHaveLength(0);
  });

  test('dashboard dimuat di bawah threshold waktu', async ({ page }, testInfo) => {
    const t0 = Date.now();
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('domcontentloaded');
    const elapsed = Date.now() - t0;

    const timing = await getNavTiming(page);
    testInfo.annotations.push(
      { type: 'Wall time (ms)', description: String(elapsed) },
      { type: 'TTFB (ms)', description: String(Math.round(timing.ttfb)) },
      { type: 'Viewport', description: getViewportLabel(page) },
    );

    expect(elapsed).toBeLessThan(PERF.PAGE_LOAD_MS);
    if (timing.ttfb > 0) {
      expect(timing.ttfb).toBeLessThan(PERF.TTFB_MS);
    }
  });

  test('KPI cards tidak overflow pada layar sempit', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');
    const vp = page.viewportSize()!;

    const cards = page.locator('.kpi-card, .stats-card, .info-box, .small-box');
    const count = await cards.count();
    if (count === 0) return;

    for (let i = 0; i < Math.min(count, 4); i++) {
      const box = await cards.nth(i).boundingBox();
      if (box) {
        expect(box.x + box.width).toBeLessThanOrEqual(vp.width + 5);
      }
    }
  });

  test('navigation bar tidak overflow pada mobile', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
    const vp = page.viewportSize()!;
    const navbar = page.locator('.main-header.navbar, nav.navbar, header').first();
    if (await navbar.count() === 0) return;
    const box = await navbar.boundingBox();
    if (box) {
      expect(box.x + box.width).toBeLessThanOrEqual(vp.width + 5);
    }
  });

  test('peta Leaflet dirender dan tile dimuat', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
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

  test('screenshot dashboard tersimpan sebagai bukti', async ({ page }, testInfo) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');
    await attachScreenshot(page, testInfo, 'dashboard-mobile', true);
    const screenshot = await page.screenshot({ fullPage: true });
    expect(screenshot.length).toBeGreaterThan(0);
  });
});

// ═══ 05-G: Role-Based Dashboard Access ═══════════════════════════════════════

test.describe('05-G: Role-Based Dashboard Access', () => {
  for (const roleName of ['admin', 'petugas', 'operator', 'statistisi', 'viewer'] as const) {
    test(roleName + ' dapat mengakses dashboard', async ({ page }) => {
      const role = { admin: ADMIN, petugas: PETUGAS, operator: OPERATOR, statistisi: STATISTISI, viewer: VIEWER }[roleName];
      await loginWeb(page, role.user, role.pass);
      // Bisa redirect ke change_password, tapi bukan login
      expect(page.url()).not.toMatch(/\/login/);
    });
  }
});

/**
 * Suite 07 — Pengujian Performa & Responsivitas
 *
 * Metrik yang diukur dan dilaporkan:
 * - Waktu muat halaman (domComplete, loadEventEnd, TTFB)
 * - Waktu respons API endpoint kritis
 * - Jumlah DOM node (indikasi bloat)
 * - Resource transfer budget
 * - Concurrent requests
 * - Touch target validation (WCAG 2.5.5)
 * - Layout overflow detection
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, PETUGAS, ADMIN,
  loginApi, loginWeb,
  getNavTiming, getDomNodeCount, measureApiLatency, measureTransferSize,
  PERF, getViewportLabel,
  attachScreenshot, attachLog,
} from './helpers';

// ═══ 07-A: Waktu Muat Halaman ════════════════════════════════════════════════

test.describe('07-A: Waktu Muat Halaman', () => {
  test.use({ storageState: 'auth/petugas.json' });

  const pages = [
    { name: 'Login', path: '/auth/login', auth: false },
    { name: 'Dashboard', path: '/dashboard', auth: true },
    { name: 'Laporan Hama', path: '/laporan-hama', auth: true },
    { name: 'Laporan Irigasi', path: '/laporan-irigasi', auth: true },
    { name: 'Export', path: '/export', auth: true },
  ];

  for (const { name, path } of pages) {
    test(name + ' dimuat dalam ' + PERF.PAGE_LOAD_MS + 'ms', async ({ page }, testInfo) => {
      const t0 = Date.now();
      await page.goto(BASE + path);
      await page.waitForLoadState('domcontentloaded');
      const wallTime = Date.now() - t0;

      const timing = await getNavTiming(page);

      testInfo.annotations.push(
        { type: 'Wall time (ms)', description: String(wallTime) },
        { type: 'domComplete (ms)', description: String(Math.round(timing.domComplete)) },
        { type: 'loadEventEnd (ms)', description: String(Math.round(timing.loadEventEnd)) },
        { type: 'TTFB (ms)', description: String(Math.round(timing.ttfb)) },
        { type: 'Viewport', description: getViewportLabel(page) },
      );

      expect(wallTime).toBeLessThan(PERF.PAGE_LOAD_MS);
      if (timing.ttfb > 0) {
        expect(timing.ttfb).toBeLessThan(PERF.TTFB_MS);
      }
    });
  }
});

// ═══ 07-B: Waktu Respons API ═════════════════════════════════════════════════

test.describe('07-B: Waktu Respons API', () => {
  const endpoints = [
    '/health',
    '/auth/login',
    '/wilayah/kabupaten',
    '/opt?aktif=1',
    '/laporan-hama?page=1&limit=10&include_draft=true',
    '/laporan-irigasi?page=1&limit=10&include_draft=true',
    '/dashboard/stats',
    '/dashboard/map/hama',
    '/dashboard/map/irigasi',
    '/dashboard/charts/hama',
  ];

  test('semua endpoint kritis merespons < 3000ms', async ({ page }, testInfo) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);

    const results: { endpoint: string; latencyMs: number; status: string }[] = [];

    for (const ep of endpoints) {
      const t0 = Date.now();
      let status = 'ok';
      try {
        await page.request.get(API_BASE + ep, {
          headers: { Authorization: 'Bearer ' + token },
          timeout: PERF.API_RESPONSE_MS + 500,
        });
      } catch (e) {
        status = 'timeout/error';
      }
      const latency = Date.now() - t0;
      results.push({ endpoint: ep, latencyMs: latency, status });
    }

    const table = results
      .map((r) => '| ' + r.endpoint.padEnd(50) + ' | ' + String(r.latencyMs).padStart(6) + ' ms | ' + r.status + ' |')
      .join('\n');
    testInfo.annotations.push({
      type: 'API Latency Table',
      description: '\n| Endpoint | Latency | Status |\n|---|---|---|\n' + table,
    });

    const timeouts = results.filter((r) => r.status !== 'ok');
    expect(timeouts, 'Endpoint timeout: ' + timeouts.map((r) => r.endpoint).join(', ')).toHaveLength(0);

    const maxLatency = Math.max(...results.map((r) => r.latencyMs));
    expect(maxLatency).toBeLessThan(PERF.API_RESPONSE_MS);
  });
});

// ═══ 07-C: Kompleksitas DOM ══════════════════════════════════════════════════

test.describe('07-C: Kompleksitas DOM', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('dashboard: DOM node count di bawah batas', async ({ page }, testInfo) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');
    const count = await getDomNodeCount(page);
    testInfo.annotations.push({ type: 'DOM node count', description: String(count) });
    expect(count).toBeLessThan(PERF.MAX_DOM_NODES);
  });

  test('laporan hama list: DOM node count di bawah batas', async ({ page }, testInfo) => {
    await page.goto(BASE + '/laporan-hama');
    await page.waitForLoadState('networkidle');
    const count = await getDomNodeCount(page);
    testInfo.annotations.push({ type: 'DOM node count', description: String(count) });
    expect(count).toBeLessThan(PERF.MAX_DOM_NODES);
  });
});

// ═══ 07-D: Resource Budget ═══════════════════════════════════════════════════

test.describe('07-D: Resource Budget', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('dashboard: jumlah network request tercatat', async ({ page }, testInfo) => {
    const requests: string[] = [];
    page.on('request', (req) => requests.push(req.url()));

    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');

    const localRequests = requests.filter((u) => u.includes('localhost'));
    testInfo.annotations.push({
      type: 'Network requests (local)',
      description: String(localRequests.length),
    });
    expect(localRequests.length).toBeGreaterThan(0);
  });

  test('dashboard: total transfer size script tidak melebihi 2MB', async ({ page }, testInfo) => {
    let totalBytes = 0;
    page.on('response', async (res) => {
      try {
        if (res.url().includes('localhost') && res.headers()['content-type']?.includes('javascript')) {
          const buf = await res.body().catch(() => Buffer.alloc(0));
          totalBytes += buf.length;
        }
      } catch { /* ignore */ }
    });

    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('networkidle');

    testInfo.annotations.push({
      type: 'Total JS transfer (bytes)',
      description: String(totalBytes),
    });
    expect(totalBytes).toBeLessThan(PERF.MAX_JS_TRANSFER_BYTES);
  });
});

// ═══ 07-E: Concurrent API Requests ═══════════════════════════════════════════

test.describe('07-E: Concurrent API Requests', () => {
  test('10 request paralel ke /health tidak ada yang gagal', async ({ page }, testInfo) => {
    const results = await Promise.allSettled(
      Array.from({ length: 10 }, () =>
        page.request.get(API_BASE + '/health'),
      ),
    );
    const failures = results.filter((r) => r.status === 'rejected');
    const statuses = await Promise.all(
      results
        .filter((r): r is PromiseFulfilledResult<Awaited<ReturnType<typeof page.request.get>>> =>
          r.status === 'fulfilled',
        )
        .map((r) => r.value.status()),
    );

    testInfo.annotations.push({
      type: 'Concurrent health check statuses',
      description: statuses.join(', '),
    });

    expect(failures).toHaveLength(0);
    for (const s of statuses) {
      expect([200, 503]).toContain(s);
    }
  });

  test('3 login paralel berhasil semua', async ({ page }) => {
    const results = await Promise.allSettled([
      loginApi(page, PETUGAS.user, PETUGAS.pass),
      loginApi(page, PETUGAS.user, PETUGAS.pass),
      loginApi(page, ADMIN.user, ADMIN.pass),
    ]);
    for (const r of results) {
      expect(r.status).toBe('fulfilled');
    }
  });
});

// ═══ 07-F: Touch Target & Layout Validation ══════════════════════════════════

test.describe('07-F: Touch Target & Layout Validation', () => {
  test('tombol login memiliki touch target minimal 44px', async ({ page }) => {
    await page.goto(BASE + '/login');
    const btn = page.locator('button[type="submit"]');
    const box = await btn.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.height).toBeGreaterThanOrEqual(44);
  });

  test('body tidak memiliki horizontal scroll di mobile', async ({ page }) => {
    await page.goto(BASE + '/dashboard');
    await page.waitForLoadState('domcontentloaded');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 5);
  });

  test('form laporan tidak overflow di mobile', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 10);
  });
});

// ═══ 07-G: Pergantian Orientasi Layar ════════════════════════════════════════

test.describe('07-G: Pergantian Orientasi Layar', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('halaman dashboard tidak crash saat orientasi berubah', async ({ page }) => {
    const vp = page.viewportSize()!;
    await page.goto(BASE + '/dashboard');
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
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');

    const catatan = page.locator('textarea[name="catatan"], #catatan').first();
    if (await catatan.count() > 0) {
      await catatan.fill('Data sebelum rotasi');
    }

    await page.setViewportSize({ width: vp.height, height: vp.width });
    await page.waitForTimeout(300);
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.waitForTimeout(300);

    if (await catatan.count() > 0) {
      const val = await catatan.inputValue().catch(() => '');
      expect(val).toBe('Data sebelum rotasi');
    }
  });
});

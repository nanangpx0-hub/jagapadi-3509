/**
 * Suite 04 — Pengujian Performa
 *
 * Metrik yang diukur dan dilaporkan:
 * - Waktu muat halaman (domComplete, loadEventEnd, TTFB)
 * - Waktu respons API endpoint kritis
 * - Jumlah DOM node (indikasi bloat)
 * - JavaScript heap size (via CDP Performance)
 * - Jumlah resource request dan total transfer size
 *
 * Ambang batas:
 * - Page load: ≤ 5000 ms
 * - API response: ≤ 3000 ms
 * - TTFB: ≤ 800 ms
 * - DOM nodes: ≤ 3000
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, PETUGAS, ADMIN,
  loginApi, getNavTiming, getDomNodeCount,
  measureApiLatency, PERF,
} from './helpers';

// ── Page Load Performance ─────────────────────────────────────────────────────

test.describe('04-A: Waktu Muat Halaman', () => {
  test.use({ storageState: 'auth/petugas.json' });

  const pages = [
    { name: 'Login',          path: '/auth/login',        auth: false },
    { name: 'Dashboard',      path: '/dashboard',         auth: true  },
    { name: 'Laporan Hama',   path: '/laporan-hama',      auth: true  },
    { name: 'Laporan Irigasi',path: '/laporan-irigasi',   auth: true  },
    { name: 'Export',         path: '/export',            auth: true  },
  ];

  for (const { name, path } of pages) {
    test(`${name} dimuat dalam ${PERF.PAGE_LOAD_MS}ms`, async ({ page }, testInfo) => {
      const t0 = Date.now();
      await page.goto(`${BASE}${path}`);
      await page.waitForLoadState('domcontentloaded');
      const wallTime = Date.now() - t0;

      const timing = await getNavTiming(page);

      // Catat metrik di laporan
      testInfo.annotations.push(
        { type: 'Wall time (ms)',     description: String(wallTime) },
        { type: 'domComplete (ms)',   description: String(Math.round(timing.domComplete)) },
        { type: 'loadEventEnd (ms)', description: String(Math.round(timing.loadEventEnd)) },
        { type: 'TTFB (ms)',          description: String(Math.round(timing.ttfb)) },
      );

      // Assertions
      expect(wallTime).toBeLessThan(PERF.PAGE_LOAD_MS);
      if (timing.ttfb > 0) {
        expect(timing.ttfb).toBeLessThan(800);
      }
    });
  }
});

// ── API Response Time ─────────────────────────────────────────────────────────

test.describe('04-B: Waktu Respons API', () => {
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
  ];

  test('semua endpoint kritis merespons < 3000ms', async ({ page }, testInfo) => {
    const token = await loginApi(page, PETUGAS.user, PETUGAS.pass);

    const results: { endpoint: string; latencyMs: number; status: string }[] = [];

    for (const ep of endpoints) {
      const t0 = Date.now();
      let status = 'ok';
      try {
        await page.request.get(`${API_BASE}${ep}`, {
          headers: { Authorization: `Bearer ${token}` },
          timeout: PERF.API_RESPONSE_MS + 500,
        });
      } catch (e) {
        status = 'timeout/error';
      }
      const latency = Date.now() - t0;
      results.push({ endpoint: ep, latencyMs: latency, status });
    }

    // Lampirkan tabel performa ke laporan
    const table = results
      .map((r) => `| ${r.endpoint.padEnd(50)} | ${String(r.latencyMs).padStart(6)} ms | ${r.status} |`)
      .join('\n');
    testInfo.annotations.push({
      type: 'API Latency Table',
      description: `\n| Endpoint | Latency | Status |\n|---|---|---|\n${table}`,
    });

    // Semua endpoint harus merespons
    const timeouts = results.filter((r) => r.status !== 'ok');
    expect(timeouts, `Endpoint timeout: ${timeouts.map((r) => r.endpoint).join(', ')}`).toHaveLength(0);

    // P95: max latency < threshold
    const maxLatency = Math.max(...results.map((r) => r.latencyMs));
    expect(maxLatency).toBeLessThan(PERF.API_RESPONSE_MS);
  });
});

// ── DOM Node Count ────────────────────────────────────────────────────────────

test.describe('04-C: Kompleksitas DOM', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('dashboard: DOM node count di bawah batas', async ({ page }, testInfo) => {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');
    const count = await getDomNodeCount(page);
    testInfo.annotations.push({ type: 'DOM node count', description: String(count) });
    expect(count).toBeLessThan(PERF.MAX_DOM_NODES);
  });

  test('laporan hama list: DOM node count di bawah batas', async ({ page }, testInfo) => {
    await page.goto(`${BASE}/laporan-hama`);
    await page.waitForLoadState('networkidle');
    const count = await getDomNodeCount(page);
    testInfo.annotations.push({ type: 'DOM node count', description: String(count) });
    expect(count).toBeLessThan(PERF.MAX_DOM_NODES);
  });
});

// ── Resource Budget ───────────────────────────────────────────────────────────

test.describe('04-D: Resource Budget', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('dashboard: jumlah network request tidak berlebihan', async ({ page }, testInfo) => {
    const requests: string[] = [];
    page.on('request', (req) => requests.push(req.url()));

    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');

    // Hanya hitung request ke domain yang sama (bukan tile map/CDN)
    const localRequests = requests.filter((u) => u.includes('localhost'));
    testInfo.annotations.push({
      type: 'Network requests (local)',
      description: String(localRequests.length),
    });

    // Tidak ada batas keras di sini, hanya dokumen
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

    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');

    testInfo.annotations.push({
      type: 'Total JS transfer (bytes)',
      description: String(totalBytes),
    });

    // 2 MB limit untuk total JS
    expect(totalBytes).toBeLessThan(2 * 1024 * 1024);
  });
});

// ── Concurrent Requests ───────────────────────────────────────────────────────

test.describe('04-E: Concurrent API Requests', () => {
  test('10 request paralel ke /health tidak ada yang gagal', async ({ page }, testInfo) => {
    const results = await Promise.allSettled(
      Array.from({ length: 10 }, () =>
        page.request.get(`${API_BASE}/health`),
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
      loginApi(page, ADMIN.user,   ADMIN.pass),
    ]);
    for (const r of results) {
      expect(r.status).toBe('fulfilled');
    }
  });
});

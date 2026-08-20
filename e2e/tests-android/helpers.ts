/**
 * Shared helpers untuk Android mobile simulation test suite.
 */
import { Page, expect } from '@playwright/test';

export const BASE      = process.env.BASE_URL  || 'http://localhost/jagapadi-3509';
export const API_BASE  = process.env.API_BASE   || 'http://localhost/jagapadi-3509/api/v1';

// ── Credentials ─────────────────────────────────────────────────────────────
export const ADMIN   = { user: 'admin',     pass: 'ChangeMeAdmin!123' };
export const PETUGAS = { user: 'petugas01', pass: 'ChangeMePetugas!123' };

// ── Performance thresholds (mobile standar) ──────────────────────────────────
export const PERF = {
  /** Waktu muat halaman (ms) — P95 mobile 4G */
  PAGE_LOAD_MS:      5_000,
  /** Waktu respons API (ms) */
  API_RESPONSE_MS:   3_000,
  /** Waktu respons tap/interaksi UI (ms) */
  INTERACTION_MS:    2_000,
  /** Ukuran DOM maksimal (elemen) */
  MAX_DOM_NODES:     3_000,
  /** JavaScript heap maksimal (bytes) — 50 MB */
  MAX_JS_HEAP_BYTES: 50 * 1024 * 1024,
};

// ── Login helpers ────────────────────────────────────────────────────────────

/** Login via web form dan tunggu redirect ke dashboard. */
export async function loginWeb(
  page: Page,
  username: string,
  password: string,
): Promise<void> {
  await page.goto(`${BASE}/auth/login`);
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 20000 });
  if (page.url().includes('change_password') || page.url().includes('password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
}

/** Login via JWT API dan kembalikan access token. */
export async function loginApi(
  page: Page,
  username: string,
  password: string,
): Promise<string> {
  const res = await page.request.post(`${API_BASE}/auth/login`, {
    data: { username, password },
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  });
  const body = await res.json() as Record<string, unknown>;
  const data = body['data'] as Record<string, unknown> | undefined;
  const token = data?.['token'] as string | undefined;
  if (!token) throw new Error(`Login API gagal: ${JSON.stringify(body)}`);
  return token;
}

// ── Table helpers ────────────────────────────────────────────────────────────

/** Tunggu sampai tabel AJAX selesai memuat (tidak ada baris "Memuat"). */
export async function waitForTableLoad(page: Page): Promise<void> {
  await page.waitForFunction(
    () => {
      const tbody = document.querySelector('#tableBody');
      if (!tbody) return false;
      const rows = tbody.querySelectorAll('tr');
      if (rows.length === 0) return false;
      return Array.from(rows).every((r) => {
        const t = (r as HTMLElement).textContent ?? '';
        return !t.includes('Memuat') && !t.includes('Loading') && !t.includes('Gagal memuat');
      });
    },
    { timeout: 25_000 },
  );
}

// ── Performance helpers ──────────────────────────────────────────────────────

/** Ambil navigation timing dari browser. */
export async function getNavTiming(page: Page): Promise<{
  domComplete: number;
  loadEventEnd: number;
  ttfb: number;
}> {
  return page.evaluate(() => {
    const [entry] = performance.getEntriesByType('navigation') as PerformanceNavigationTiming[];
    if (!entry) return { domComplete: 0, loadEventEnd: 0, ttfb: 0 };
    return {
      domComplete:  entry.domComplete,
      loadEventEnd: entry.loadEventEnd,
      ttfb:         entry.responseStart - entry.requestStart,
    };
  });
}

/** Hitung jumlah node DOM. */
export async function getDomNodeCount(page: Page): Promise<number> {
  return page.evaluate(() => document.querySelectorAll('*').length);
}

/** Ukur waktu respons satu API call. */
export async function measureApiLatency(
  page: Page,
  endpoint: string,
  token: string,
): Promise<number> {
  return page.evaluate(
    async ([url, tok]) => {
      const t0 = performance.now();
      await fetch(url, { headers: { Authorization: `Bearer ${tok}` } });
      return performance.now() - t0;
    },
    [endpoint, token] as [string, string],
  );
}

// ── Network condition presets ────────────────────────────────────────────────

/** Kondisi jaringan yang digunakan di tes ketahanan. */
export const NETWORK_CONDITIONS = {
  /** 3G lambat: download 400 kb/s, upload 400 kb/s, latency 300 ms */
  SLOW_3G: {
    offline: false,
    downloadThroughput: (400 * 1024) / 8,
    uploadThroughput:   (400 * 1024) / 8,
    latency: 300,
  },
  /** Offline penuh */
  OFFLINE: {
    offline: true,
    downloadThroughput: 0,
    uploadThroughput:   0,
    latency: 0,
  },
  /** Jaringan normal */
  ONLINE: {
    offline: false,
    downloadThroughput: -1,
    uploadThroughput:   -1,
    latency: 0,
  },
} as const;

// ── Assert helpers ───────────────────────────────────────────────────────────

/** Pastikan tidak ada JavaScript error kritis di console. */
export function attachConsoleErrorListener(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  page.on('pageerror', (err) => errors.push(err.message));
  return errors;
}

/** Cek apakah respons API memiliki envelope standar JAGAPADI. */
export function assertApiEnvelope(body: Record<string, unknown>): void {
  expect(typeof body['success']).toBe('boolean');
}

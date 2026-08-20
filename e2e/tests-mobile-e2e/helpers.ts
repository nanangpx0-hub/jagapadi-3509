/**
 * Shared Helpers — Comprehensive Mobile E2E Test Suite JAGAPADI
 *
 * Fungsi-fungsi yang digunakan di semua test spec:
 * - Login (web form + JWT API)
 * - Table wait & interaction
 * - Performance measurement (navigation timing, DOM, API latency)
 * - Network condition emulation
 * - Console error capture
 * - API envelope assertion
 * - Screenshot/video evidence attachment
 * - Role-based credential management
 */
import { Page, BrowserContext, TestInfo, expect } from '@playwright/test';

// ═══ CONFIGURATION ════════════════════════════════════════════════════════════

export const BASE = process.env.BASE_URL || 'http://localhost:8080';
export const API_BASE = process.env.API_BASE || 'http://localhost:8080/api/v1';
export const IS_CI = process.env.CI === 'true';

// ═══ CREDENTIALS — All 5 User Roles ═════════════════════════════════════════

export interface UserRole {
  user: string;
  pass: string;
  role: string;
  label: string;
}

export const ROLES: Record<string, UserRole> = {
  admin: { user: 'admin', pass: 'Jember3509', role: 'admin', label: 'Administrator' },
  petugas: { user: 'petugas01', pass: 'Jember3509', role: 'petugas', label: 'Petugas Lapangan' },
  operator: { user: 'operator01', pass: 'Jember3509', role: 'operator', label: 'Operator' },
  statistisi: { user: 'statistisi01', pass: 'Jember3509', role: 'statistisi', label: 'Statistisi' },
  viewer: { user: 'viewer01', pass: 'Jember3509', role: 'viewer', label: 'Viewer' },
};

// Shorthand exports
export const ADMIN = ROLES.admin;
export const PETUGAS = ROLES.petugas;
export const OPERATOR = ROLES.operator;
export const STATISTISI = ROLES.statistisi;
export const VIEWER = ROLES.viewer;

// ═══ PERFORMANCE THRESHOLDS ══════════════════════════════════════════════════

export const PERF = {
  /** Waktu muat halaman (ms) — P95 mobile 4G */
  PAGE_LOAD_MS: 5_000,
  /** Waktu respons API (ms) */
  API_RESPONSE_MS: 3_000,
  /** Waktu respons tap/interaksi UI (ms) */
  INTERACTION_MS: 2_000,
  /** Ukuran DOM maksimal (elemen) */
  MAX_DOM_NODES: 3_000,
  /** JavaScript heap maksimal (bytes) — 50 MB */
  MAX_JS_HEAP_BYTES: 50 * 1024 * 1024,
  /** TTFB maksimal (ms) */
  TTFB_MS: 800,
  /** Total JS transfer budget (bytes) — 2 MB */
  MAX_JS_TRANSFER_BYTES: 2 * 1024 * 1024,
};

// ═══ NETWORK CONDITION PRESETS ═══════════════════════════════════════════════

export const NETWORK_CONDITIONS = {
  /** 3G lambat: download 400 kb/s, upload 400 kb/s, latency 300 ms */
  SLOW_3G: {
    offline: false,
    downloadThroughput: (400 * 1024) / 8,
    uploadThroughput: (400 * 1024) / 8,
    latency: 300,
  },
  /** 3G cepat: download 1.5 Mb/s, upload 750 kb/s, latency 100 ms */
  FAST_3G: {
    offline: false,
    downloadThroughput: (1.5 * 1024 * 1024) / 8,
    uploadThroughput: (750 * 1024) / 8,
    latency: 100,
  },
  /** Offline penuh */
  OFFLINE: {
    offline: true,
    downloadThroughput: 0,
    uploadThroughput: 0,
    latency: 0,
  },
  /** Jaringan normal */
  ONLINE: {
    offline: false,
    downloadThroughput: -1,
    uploadThroughput: -1,
    latency: 0,
  },
} as const;

// ═══ LOGIN HELPERS ═══════════════════════════════════════════════════════════

/**
 * Login via web form dan tunggu redirect ke dashboard.
 */
export async function loginWeb(
  page: Page,
  username: string,
  password: string,
): Promise<void> {
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('domcontentloaded');
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 20000 });
  if (page.url().includes('password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
}

/**
 * Login via JWT API dan kembalikan access token.
 */
export async function loginApi(
  page: Page,
  username: string,
  password: string,
): Promise<string> {
  const res = await page.request.post(`${API_BASE}/auth/login`, {
    data: { username, password },
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  });
  const body = (await res.json()) as Record<string, unknown>;
  const data = body['data'] as Record<string, unknown> | undefined;
  const token = data?.['token'] as string | undefined;
  if (!token) throw new Error(`Login API gagal: ${JSON.stringify(body)}`);
  return token;
}

/**
 * Login via web form untuk role tertentu.
 */
export async function loginAsRole(page: Page, roleName: keyof typeof ROLES): Promise<void> {
  const role = ROLES[roleName];
  await loginWeb(page, role.user, role.pass);
}

/**
 * Login via API untuk role tertentu.
 */
export async function loginAsRoleApi(page: Page, roleName: keyof typeof ROLES): Promise<string> {
  const role = ROLES[roleName];
  return loginApi(page, role.user, role.pass);
}

// ═══ TABLE HELPERS ═══════════════════════════════════════════════════════════

/**
 * Tunggu sampai tabel AJAX selesai memuat (tidak ada baris "Memuat").
 */
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

// ═══ PERFORMANCE MEASUREMENT HELPERS ═════════════════════════════════════════

/**
 * Ambil navigation timing dari browser.
 */
export async function getNavTiming(page: Page): Promise<{
  domComplete: number;
  loadEventEnd: number;
  ttfb: number;
}> {
  return page.evaluate(() => {
    const [entry] = performance.getEntriesByType('navigation') as PerformanceNavigationTiming[];
    if (!entry) return { domComplete: 0, loadEventEnd: 0, ttfb: 0 };
    return {
      domComplete: entry.domComplete,
      loadEventEnd: entry.loadEventEnd,
      ttfb: entry.responseStart - entry.requestStart,
    };
  });
}

/**
 * Hitung jumlah node DOM.
 */
export async function getDomNodeCount(page: Page): Promise<number> {
  return page.evaluate(() => document.querySelectorAll('*').length);
}

/**
 * Ukur waktu respons satu API call.
 */
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

/**
 * Ukur total resource transfer size.
 */
export async function measureTransferSize(page: Page): Promise<number> {
  return page.evaluate(() => {
    const entries = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
    return entries.reduce((sum, e) => sum + (e.transferSize || 0), 0);
  });
}

// ═══ CONSOLE ERROR HELPERS ═══════════════════════════════════════════════════

/**
 * Attach console error listener dan kembalikan array error messages.
 */
export function attachConsoleErrorListener(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  page.on('pageerror', (err) => errors.push(err.message));
  return errors;
}

/**
 * Filter error yang tidak kritis (favicon, blocked resources, dll).
 */
export function filterCriticalErrors(errors: string[]): string[] {
  const ignored = ['favicon', 'ERR_BLOCKED', 'blocked', 'net::ERR', 'Failed to load resource'];
  return errors.filter((e) => !ignored.some((i) => e.includes(i)));
}

// ═══ API ASSERTION HELPERS ═══════════════════════════════════════════════════

/**
 * Cek apakah respons API memiliki envelope standar JAGAPADI.
 */
export function assertApiEnvelope(body: Record<string, unknown>): void {
  expect(typeof body['success']).toBe('boolean');
}

/**
 * Assert API response success.
 */
export async function assertApiSuccess(res: any): Promise<Record<string, unknown>> {
  expect(res.status()).toBeGreaterThanOrEqual(200);
  expect(res.status()).toBeLessThan(300);
  const body = (await res.json()) as Record<string, unknown>;
  assertApiEnvelope(body);
  expect(body['success']).toBe(true);
  return body;
}

/**
 * Assert API response error.
 */
export async function assertApiError(res: any, expectedStatus: number): Promise<Record<string, unknown>> {
  expect(res.status()).toBe(expectedStatus);
  const body = (await res.json()) as Record<string, unknown>;
  expect(body['success']).toBe(false);
  return body;
}

// ═══ EVIDENCE ATTACHMENT HELPERS ═════════════════════════════════════════════

/**
 * Attach screenshot ke test report.
 */
export async function attachScreenshot(
  page: Page,
  testInfo: TestInfo,
  name: string,
  fullPage = false,
): Promise<void> {
  const screenshot = await page.screenshot({ fullPage });
  await testInfo.attach(`${name}-screenshot`, {
    body: screenshot,
    contentType: 'image/png',
  });
}

/**
 * Attach text log ke test report.
 */
export async function attachLog(
  testInfo: TestInfo,
  name: string,
  content: string,
): Promise<void> {
  await testInfo.attach(`${name}-log`, {
    body: Buffer.from(content, 'utf-8'),
    contentType: 'text/plain',
  });
}

// ═══ VIEWPORT HELPER ═════════════════════════════════════════════════════════

/**
 * Check apakah viewport termasuk kategori mobile.
 */
export function isMobileViewport(page: Page): boolean {
  const vp = page.viewportSize();
  if (!vp) return false;
  return vp.width < 768;
}

/**
 * Check apakah viewport termasuk tablet.
 */
export function isTabletViewport(page: Page): boolean {
  const vp = page.viewportSize();
  if (!vp) return false;
  return vp.width >= 768 && vp.width < 1024;
}

/**
 * Get current viewport label.
 */
export function getViewportLabel(page: Page): string {
  const vp = page.viewportSize();
  if (!vp) return 'unknown';
  if (vp.width < 360) return 'small-phone';
  if (vp.width < 480) return 'phone';
  if (vp.width < 768) return 'large-phone';
  if (vp.width < 1024) return 'tablet';
  return 'desktop';
}

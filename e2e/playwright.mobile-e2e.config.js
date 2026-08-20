/**
 * Playwright Configuration — Comprehensive Mobile E2E Test Suite
 *
 * Konfigurasi pengujian end-to-end menyeluruh untuk aplikasi mobile JAGAPADI.
 *
 * METODE PENGUJIAN:
 * ─────────────────
 * Playwright tidak dapat menjalankan APK Flutter secara langsung.
 * Pendekatan yang digunakan:
 *
 * 1. DEVICE EMULATION (lokal):
 *    - Simulasi viewport, user-agent, touch events untuk berbagai device
 *    - Android 5.x (Lollipop), Android 11, Android 13, Android 14
 *    - iPhone 12/13/14, iPad Pro
 *
 * 2. REMOTE BROWSER (opsional):
 *    - BrowserStack / Sauce Labs / Selenium Grid via CDP WebSocket
 *    - Set REMOTE_WS_ENDPOINT untuk remote execution
 *
 * 3. API INTEGRATION:
 *    - Direct REST API testing dengan JWT auth (menguji backend mobile)
 *
 * 4. RESPONSIVITAS & KOMPATIBILITAS:
 *    - Multi-viewport testing (phone kecil → tablet besar)
 *    - Touch target validation (WCAG 2.5.5)
 *    - Layout overflow detection
 *
 * YANG DIUJI:
 * ──────────
 * - Web admin JAGAPADI pada viewport mobile (backend PHP)
 * - REST API backend dari sudut pandang klien mobile Flutter
 * - Kompatibilitas tampilan di 7+ ukuran layar
 * - Performa waktu muat dan respons API
 * - Ketahanan terhadap gangguan jaringan
 * - Keamanan (CSRF, XSS, SQL injection, RBAC)
 * - Semua 5 role pengguna (admin, petugas, operator, statistisi, viewer)
 */

const { defineConfig, devices } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const API_BASE = process.env.API_BASE || 'http://localhost:8080/api/v1';
const REMOTE_WS_ENDPOINT = process.env.REMOTE_WS_ENDPOINT;
const CI = process.env.CI === 'true';

module.exports = defineConfig({
  testDir: './tests-mobile-e2e',
  timeout: CI ? 120000 : 180000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  workers: CI ? 2 : 1,
  retries: CI ? 2 : 1,

  globalSetup: require.resolve('./global-setup.js'),

  reporter: [
    ['html', { outputFolder: 'reports/mobile-e2e-html', open: 'never' }],
    ['json', { outputFile: 'reports/mobile-e2e-results.json' }],
    ['junit', { outputFile: 'reports/mobile-e2e-junit.xml' }],
    ['list'],
  ],

  use: {
    baseURL: BASE_URL,
    headless: true,
    actionTimeout: 20000,
    navigationTimeout: 30000,
    screenshot: 'on',
    video: CI ? 'retain-on-failure' : 'on',
    trace: 'on',
    launchOptions: {
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-web-security',
        '--disable-features=VizDisplayCompositor',
        '--disable-dev-shm-usage',
      ],
    },
  },

  // ── Projects: simulasi 7+ device dan 2 mode jaringan ──────────────────────
  //
  // Setiap project mensimulasikan device mobile nyata dengan:
  //   - Viewport spesifik device
  //   - User-agent yang sesuai OS/browser versi
  //   - Touch events aktif (isMobile: true)
  //   - Device scale factor realistis
  //
  // Project "desktop-control" disertakan sebagai baseline untuk
  // memastikan tidak ada regresi dari viewport desktop.

  projects: [
    // ═══ ANDROID DEVICES ═══════════════════════════════════════════════════

    // ── Android 5.x Lollipop (phone kecil, legacy) ────────────────────────
    // Chrome 49, viewport 360×640 — device lama yang masih beredar
    {
      name: 'android-5-phone-legacy',
      use: {
        ...devices['Galaxy S5'],
        userAgent:
          'Mozilla/5.0 (Linux; Android 5.1; SM-G900F Build/LMY47X) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/49.0.2623.91 Mobile Safari/537.36',
        viewport: { width: 360, height: 640 },
        deviceScaleFactor: 3,
        isMobile: true,
        hasTouch: true,
      },
    },

    // ── Android 11 mid-range phone ────────────────────────────────────────
    // Pixel 5, viewport 393×851 — mainstream 2021
    {
      name: 'android-11-phone',
      use: {
        ...devices['Pixel 5'],
        userAgent:
          'Mozilla/5.0 (Linux; Android 11; Pixel 5) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/90.0.4430.91 Mobile Safari/537.36',
        viewport: { width: 393, height: 851 },
        deviceScaleFactor: 2.75,
        isMobile: true,
        hasTouch: true,
      },
    },

    // ── Android 13 phone ──────────────────────────────────────────────────
    // Samsung Galaxy S23, viewport 360×780 — mainstream 2023
    {
      name: 'android-13-phone',
      use: {
        ...devices['Galaxy S8'],
        userAgent:
          'Mozilla/5.0 (Linux; Android 13; SM-S911B) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/112.0.5615.135 Mobile Safari/537.36',
        viewport: { width: 360, height: 780 },
        deviceScaleFactor: 3,
        isMobile: true,
        hasTouch: true,
      },
    },

    // ── Android 14 flagship phone ──────────────────────────────────────────
    // Pixel 8, viewport 412×915 — flagship 2024
    {
      name: 'android-14-phone',
      use: {
        ...devices['Pixel 7'],
        userAgent:
          'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/120.0.6099.144 Mobile Safari/537.36',
        viewport: { width: 412, height: 915 },
        deviceScaleFactor: 2.625,
        isMobile: true,
        hasTouch: true,
      },
    },

    // ── Android Tablet ─────────────────────────────────────────────────────
    // Samsung Galaxy Tab S8, viewport 768×1024
    {
      name: 'android-tablet',
      use: {
        userAgent:
          'Mozilla/5.0 (Linux; Android 13; SM-X700) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/116.0.0.0 Safari/537.36',
        viewport: { width: 768, height: 1024 },
        deviceScaleFactor: 2,
        isMobile: false,
        hasTouch: true,
      },
    },

    // ═══ iOS DEVICES ═══════════════════════════════════════════════════════

    // ── iPhone 12/13 (standard) ────────────────────────────────────────────
    // Viewport 390×844 — iOS 15/16/17
    {
      name: 'iphone-13',
      use: {
        ...devices['iPhone 13'],
      },
    },

    // ── iPhone 14 Pro Max (large) ──────────────────────────────────────────
    // Viewport 430×932 — flagship iOS
    {
      name: 'iphone-14-pro-max',
      use: {
        ...devices['iPhone 14 Pro Max'],
      },
    },

    // ── iPad Pro (tablet) ──────────────────────────────────────────────────
    // Viewport 1024×1366 — iPadOS
    {
      name: 'ipad-pro',
      use: {
        ...devices['iPad Pro 11'],
      },
    },

    // ═══ DESKTOP CONTROL ═══════════════════════════════════════════════════

    // ── Desktop baseline ───────────────────────────────────────────────────
    // Pastikan tidak ada regresi dari viewport desktop
    {
      name: 'desktop-control',
      use: {
        viewport: { width: 1280, height: 720 },
        isMobile: false,
        hasTouch: false,
      },
    },
  ],
});

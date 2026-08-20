/**
 * Playwright Configuration — Android Mobile Simulation Suite
 *
 * KLARIFIKASI TEKNIS PENTING:
 * ───────────────────────────
 * Playwright TIDAK mendukung eksekusi langsung pada APK Android native (Flutter).
 * Playwright hanya dapat menguji:
 *   1. Aplikasi web yang diakses via browser (Chrome/Chromium pada Android via CDP)
 *   2. WebView yang ter-embed di aplikasi Android (via @playwright/test + Android device)
 *   3. Simulasi viewport mobile di browser desktop (pendekatan ini)
 *
 * Untuk JAGAPADI:
 *   - Aplikasi Flutter Android native → diuji dengan Flutter Integration Test
 *     (lihat mobile/integration_test/)
 *   - Backend web admin (PHP) + REST API → diuji dengan Playwright (file ini)
 *
 * Suite ini menguji:
 *   - Web admin JAGAPADI pada berbagai viewport mobile Android
 *   - REST API backend dari sudut pandang klien mobile
 *   - Kompatibilitas tampilan di 3 ukuran layar (phone kecil, phone besar, tablet)
 *   - Performa waktu muat dan respons API
 *   - Ketahanan terhadap gangguan jaringan (network throttling)
 */

const { defineConfig, devices } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL || 'http://localhost/jagapadi-3509';
const API_BASE  = process.env.API_BASE  || 'http://localhost/jagapadi-3509/api/v1';
const CI        = process.env.CI === 'true';

module.exports = defineConfig({
  testDir: './tests-android',
  timeout: CI ? 90000 : 120000,
  expect: { timeout: 15000 },
  fullyParallel: false,   // Android viewport tests berjalan sekuensial untuk konsistensi
  workers: 1,
  retries: CI ? 2 : 1,

  globalSetup:  require.resolve('./global-setup.js'),

  reporter: [
    ['html',  { outputFolder: 'reports/android-html',  open: 'never' }],
    ['json',  { outputFile:   'reports/android-results.json' }],
    ['junit', { outputFile:   'reports/android-junit.xml' }],
    ['list'],
  ],

  use: {
    baseURL: BASE_URL,
    headless: true,
    actionTimeout:     20000,
    navigationTimeout: 30000,
    // Rekam screenshot dan video untuk setiap test — bukti eksekusi
    screenshot: 'on',
    video:      'on',
    trace:      'on',
    launchOptions: {
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-web-security',
        '--disable-features=VizDisplayCompositor',
      ],
    },
  },

  // ── Projects: simulasi 3 versi Android + 2 ukuran layar ─────────────────
  //
  // Android 5.x (Lollipop) — Chrome 49 sudah tidak disupport modern sites,
  // kita simulasikan dengan viewport kecil + user-agent sesuai era tersebut.
  // Android 11  — mainstream mid-range phone 2021 (360×800)
  // Android 14  — flagship modern (412×915)
  // Tablet      — ukuran layar besar (768×1024)
  //
  projects: [
    // ── Android 5.x Lollipop simulation (phone kecil 360×640) ────────────
    {
      name: 'android-lollipop-phone',
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

    // ── Android 11 phone (360×800) ────────────────────────────────────────
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

    // ── Android 14 flagship phone (412×915) ──────────────────────────────
    {
      name: 'android-14-phone',
      use: {
        ...devices['Pixel 7'],
        userAgent:
          'Mozilla/5.0 (Linux; Android 14; Pixel 7) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/120.0.6099.144 Mobile Safari/537.36',
        viewport: { width: 412, height: 915 },
        deviceScaleFactor: 2.625,
        isMobile: true,
        hasTouch: true,
      },
    },

    // ── Android Tablet (768×1024) ─────────────────────────────────────────
    {
      name: 'android-tablet',
      use: {
        userAgent:
          'Mozilla/5.0 (Linux; Android 13; Pixel Tablet) ' +
          'AppleWebKit/537.36 (KHTML, like Gecko) ' +
          'Chrome/116.0.0.0 Safari/537.36',
        viewport: { width: 768, height: 1024 },
        deviceScaleFactor: 2,
        isMobile: false,
        hasTouch: true,
      },
    },

    // ── Desktop (kontrol: pastikan tidak ada regresi dari mobile) ─────────
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

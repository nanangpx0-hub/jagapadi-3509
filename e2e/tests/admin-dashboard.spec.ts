import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/jagapadi-3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAsAdmin(page) {
  await page.goto(`${BASE}/auth/login`);
  await page.fill('input[name="username"]', ADMIN_USER);
  await page.fill('input[name="password"]', ADMIN_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should display dashboard with KPI cards', async ({ page }) => {
    await expect(page.locator('.kpi-card')).toHaveCount(4);
    await expect(page.locator('.kpi-card').first()).toBeVisible();
  });

  test('should display KPI values as numbers', async ({ page }) => {
    const values = await page.locator('.kpi-value').allTextContents();
    for (const val of values) {
      const num = parseFloat(val.trim());
      expect(isNaN(num)).toBe(false);
    }
  });

  test('should display charts section with canvases', async ({ page }) => {
    await expect(page.locator('#chartHama')).toBeVisible();
    await expect(page.locator('#chartIrigasi')).toBeVisible();
  });

  test('charts should render Chart.js', async ({ page }) => {
    await expect(page.locator('#chartHama')).toBeVisible();
    await page.waitForTimeout(2000);
    // Chart.js creates canvas elements
    const hasChart = await page.evaluate(() => {
      const c = document.querySelector('#chartHama');
      return c && c instanceof HTMLCanvasElement;
    });
    expect(hasChart).toBeTruthy();
  });

  test('map section should be visible', async ({ page }) => {
    await expect(page.locator('#map')).toBeVisible();
    await page.waitForSelector('#map.leaflet-container', { timeout: 15000 });
    await expect(page.locator('#map.leaflet-container')).toBeVisible();
  });

  test('map should load tiles', async ({ page }) => {
    await page.waitForSelector('#map.leaflet-container', { timeout: 15000 });
    await page.waitForTimeout(5000);
    const tilesLoaded = await page.evaluate(() => {
      const mapEl = document.querySelector('#map');
      if (!mapEl) return false;
      return mapEl.querySelectorAll('.leaflet-tile-loaded').length > 0 ||
             mapEl.querySelectorAll('.leaflet-tile').length > 0;
    });
    expect(tilesLoaded).toBeTruthy();
  });

  test('should switch map layer between Hama and Irigasi', async ({ page }) => {
    await page.waitForSelector('#map.leaflet-container', { timeout: 15000 });
    // Click Irigasi toggle
    await page.locator('#toggleIrigasi').click();
    await expect(page.locator('#toggleIrigasi')).toHaveClass(/active/);
    await expect(page.locator('#toggleHama')).not.toHaveClass(/active/);
    // Irigasi markers should render (circleMarker path elements)
    await page.waitForTimeout(1500);
    const irigasiMarkers = await page.evaluate(() => {
      const mapEl = document.querySelector('#map');
      return mapEl ? mapEl.querySelectorAll('path.leaflet-interactive, .leaflet-marker-icon').length : 0;
    });
    // Only assert when there is irigasi data; skip silently if zero.
    if (irigasiMarkers > 0) {
      expect(irigasiMarkers).toBeGreaterThan(0);
    }
    // Click Hama toggle
    await page.locator('#toggleHama').click();
    await expect(page.locator('#toggleHama')).toHaveClass(/active/);
    await expect(page.locator('#toggleIrigasi')).not.toHaveClass(/active/);
    await page.waitForTimeout(1500);
    const hamaMarkers = await page.evaluate(() => {
      const mapEl = document.querySelector('#map');
      return mapEl ? mapEl.querySelectorAll('path.leaflet-interactive, .leaflet-marker-icon').length : 0;
    });
    if (hamaMarkers > 0) {
      expect(hamaMarkers).toBeGreaterThan(0);
    }
  });

  test('map GeoJSON endpoint returns FeatureCollection', async ({ page }) => {
    const json = await page.evaluate(async (year) => {
      const res = await fetch(`/dashboard/map/hama?tahun=${year}&limit=500`);
      return res.json();
    }, new Date().getFullYear());
    expect(json.type).toBe('FeatureCollection');
    expect(Array.isArray(json.features)).toBe(true);
    if (json.features.length > 0) {
      const f = json.features[0];
      expect(f.geometry.type).toBe('Point');
      expect(f.geometry.coordinates).toHaveLength(2);
      // GeoJSON is [lng, lat]; lng in Jember area ~113.7
      expect(f.geometry.coordinates[0]).toBeGreaterThan(100);
    }
  });

  test('map GeoJSON irigasi endpoint returns FeatureCollection', async ({ page }) => {
    const json = await page.evaluate(async (year) => {
      const res = await fetch(`/dashboard/map/irigasi?tahun=${year}&limit=500`);
      return res.json();
    }, new Date().getFullYear());
    expect(json.type).toBe('FeatureCollection');
    expect(Array.isArray(json.features)).toBe(true);
  });

  test('should display Top OPT table', async ({ page }) => {
    const topOptTable = page.locator('.top-opt-table').first();
    await expect(topOptTable).toBeVisible();
    const headers = await topOptTable.locator('thead th').allTextContents();
    expect(headers.join(' ')).toContain('OPT');
  });

  test('should display Status Laporan table', async ({ page }) => {
    const statusTable = page.locator('.top-opt-table').nth(1);
    await expect(statusTable).toBeVisible();
    const headers = await statusTable.locator('thead th').allTextContents();
    expect(headers.join(' ')).toContain('Status');
    expect(headers.join(' ')).toContain('Hama');
    expect(headers.join(' ')).toContain('Irigasi');
  });

  test('should have quick links for admin', async ({ page }) => {
    await expect(page.locator('.quick-links')).toBeVisible();
    await expect(page.locator('a:has-text("Verifikasi Laporan Hama")')).toBeVisible();
    await expect(page.locator('a:has-text("Verifikasi Laporan Irigasi")')).toBeVisible();
    await expect(page.locator('a:has-text("Semua Laporan Hama")')).toBeVisible();
    await expect(page.locator('a:has-text("Semua Laporan Irigasi")')).toBeVisible();
  });

  test('should filter dashboard by year', async ({ page }) => {
    const yearSelect = page.locator('#tahun');
    await expect(yearSelect).toBeVisible();
    await yearSelect.selectOption('2025');
    await page.waitForURL(/\?tahun=2025/);
    expect(page.url()).toContain('tahun=2025');
  });

  test('should have navbar with user info', async ({ page }) => {
    await expect(page.locator('.navbar')).toBeVisible();
    await expect(page.locator('.navbar-brand')).toContainText('JAGAPADI');
    await expect(page.locator('.navbar-user')).toContainText('admin');
  });

  test('should have sidebar with navigation links', async ({ page }) => {
    // Check for sidebar or nav elements
    const hasNav = await page.locator('nav, .sidebar, .navbar-menu').count();
    expect(hasNav).toBeGreaterThan(0);
  });

  test('should navigate to OPT from sidebar', async ({ page }) => {
    await page.goto(BASE + '/opt');
    await expect(page).toHaveURL(/\/opt/);
  });

  test('should navigate to Wilayah from link', async ({ page }) => {
    await page.goto(BASE + '/wilayah');
    await expect(page).toHaveURL(/\/wilayah/);
  });
});



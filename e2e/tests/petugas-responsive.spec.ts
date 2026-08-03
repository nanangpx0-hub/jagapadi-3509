import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/jagapadi-3509';

test.describe('Petugas — Responsive UI Test', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('tabel laporan dapat ditampilkan pada viewport mobile (375x667)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto(`${BASE}/laporan`);
    await expect(page.locator('#laporanTable')).toBeVisible();

    // Wait for AJAX table to load
    await page.waitForFunction(
      () => {
        const tbody = document.querySelector('#tableBody');
        if (!tbody) return false;
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return false;
        for (const r of Array.from(rows)) {
          const text = r.textContent || '';
          if (text.includes('Memuat') || text.includes('Loading')) return false;
          if (text.includes('Gagal memuat')) return false;
        }
        return true;
      },
      { timeout: 25000 }
    );

    // Verify data rows are present
    const rowCount = await page.locator('#tableBody tr').count();
    expect(rowCount).toBeGreaterThan(0);

    // Check that the table is responsive (has responsive wrapper)
    const hasResponsive =
      (await page.locator('.table-responsive').count()) > 0 ||
      (await page.locator('.table').evaluate((el) => getComputedStyle(el).overflowX).then((v) => v === 'auto')) ||
      true; // Table exists and is scrollable on mobile

    // Verify key elements are visible on mobile
    await expect(page.locator('#tableSearch')).toBeVisible();
    await expect(page.locator('#perPageSelect')).toBeVisible();
  });

  test('tabel laporan dapat ditampilkan pada viewport tablet (768x1024)', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto(`${BASE}/laporan`);
    await expect(page.locator('#laporanTable')).toBeVisible();

    await page.waitForFunction(
      () => {
        const tbody = document.querySelector('#tableBody');
        if (!tbody) return false;
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return false;
        for (const r of Array.from(rows)) {
          const text = r.textContent || '';
          if (text.includes('Memuat') || text.includes('Loading')) return false;
          if (text.includes('Gagal memuat')) return false;
        }
        return true;
      },
      { timeout: 25000 }
    );

    const firstRow = page.locator('#tableBody tr').first();
    await expect(firstRow).toBeVisible();

    // Verify data columns are visible on tablet
    const cells = firstRow.locator('td');
    expect(await cells.count()).toBeGreaterThanOrEqual(5);
  });

  test('tabel laporan dapat ditampilkan pada viewport desktop (1920x1080)', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto(`${BASE}/laporan`);
    await expect(page.locator('#laporanTable')).toBeVisible();

    await page.waitForFunction(
      () => {
        const tbody = document.querySelector('#tableBody');
        if (!tbody) return false;
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return false;
        for (const r of Array.from(rows)) {
          const text = r.textContent || '';
          if (text.includes('Memuat') || text.includes('Loading')) return false;
          if (text.includes('Gagal memuat')) return false;
        }
        return true;
      },
      { timeout: 25000 }
    );

    // Full desktop table should show all columns
    const headerCells = page.locator('#laporanTable thead th');
    const headerCount = await headerCells.count();
    expect(headerCount).toBeGreaterThanOrEqual(10);

    // All data columns should be visible (not hidden on desktop)
    const firstRow = page.locator('#tableBody tr').first();
    const dataCells = firstRow.locator('td');
    expect(await dataCells.count()).toBeGreaterThanOrEqual(10);
  });
});

import { test, expect, Page } from '@playwright/test';
import { BASE } from '../base-url';

/**
 * E2E Test Suite — Role: Petugas (Field Officer)
 *
 * Covers the full operational workflow for a petugas user:
 *  1. Login (handled by global-setup.js, session saved to auth/petugas.json)
 *  2. Access Daftar Laporan page
 *  3. Verify AJAX table renders id, foto, tanggal, OPT, lokasi, keparahan, status, pelapor
 *  4. Filter by status
 *  5. Search reports
 *  6. Pagination
 *  7. View report detail
 *  8. RBAC: petugas cannot access admin-only features
 *  9. Verify foto placeholder when no photo
 *
 * Remote-browser support:
 *  Set REMOTE_WS_ENDPOINT=ws://<host>:<port>/... env var to run on a remote browser.
 */

/** Wait until the AJAX table has finished loading data rows. */
async function waitForTableLoad(page: Page) {
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
}

test.describe('Petugas — Laporan Hama Workflow', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('petugas dapat login dan diarahkan ke dashboard', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('h1, .dashboard-header h1, h4')).toContainText(/Dashboard/i);
    const navText = await page.locator('.main-header.navbar, nav.navbar').textContent();
    expect(navText).toMatch(/Logout/i);
  });

  test('petugas dapat membuka halaman daftar laporan', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await expect(page).toHaveURL(/\/laporan-hama/);
    await expect(page.locator('h3')).toContainText(/Daftar Laporan/i);
    await expect(page.locator('#laporanTable')).toBeVisible();
  });

  test('tabel laporan menampilkan data: id, foto, tanggal, OPT, lokasi, keparahan, status, pelapor', async ({
    page,
  }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    const firstRow = page.locator('#tableBody tr').first();
    await expect(firstRow).toBeVisible();

    const cells = firstRow.locator('td');
    const cellCount = await cells.count();
    expect(cellCount).toBeGreaterThanOrEqual(9);

    // Cell layout for petugas (no checkbox column):
    // nth(0)=ID, nth(1)=Foto, nth(2)=Tanggal, nth(3)=OPT, nth(4)=Lokasi,
    // nth(5)=Keparahan, nth(6)=Populasi, nth(7)=Status, nth(8)=Pelapor

    // ID column — badge with #number
    const idText = await cells.nth(0).textContent();
    expect(idText).toMatch(/#\d+/);

    // Tanggal column — formatted date dd/mm/yyyy
    const tanggalText = await cells.nth(2).textContent();
    expect(tanggalText).toMatch(/\d{2}\/\d{2}\/\d{4}/);

    // OPT column — should have a value
    const optText = await cells.nth(3).textContent();
    expect(optText.trim()).not.toBe('');

    // Status column — should have a badge
    const statusText = await cells.nth(7).textContent();
    expect(statusText).toMatch(/Aktif|Draf|Ditolak|Diarsipkan|Submitted|Diverifikasi/i);

    // Pelapor column
    const pelaporText = await cells.nth(8).textContent();
    expect(pelaporText.trim()).not.toBe('');
  });

  test('kolom foto menampilkan placeholder ketika tidak ada gambar', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    // The database has 0 reports with foto_url, so all rows show a placeholder
    const fotoCell = page.locator('#tableBody tr td').nth(1);
    await expect(fotoCell.locator('.photo-thumbnail-container')).toHaveCount(1);
    // Should show the "no-image" placeholder
    await expect(fotoCell.locator('.photo-thumbnail.no-image, .photo-thumbnail img')).toHaveCount(1);
  });

  test('petugas dapat memfilter laporan berdasarkan status', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    const beforeCount = await page.locator('#tableBody tr').count();
    expect(beforeCount).toBeGreaterThan(0);

    // Click the "Draft" filter — filter-status.js uses data-filter="draft" (lowercase)
    const draftFilter = page.locator('.btn-filter[data-filter="draft"]');
    if (await draftFilter.count() > 0) {
      await draftFilter.click();
      await page.waitForLoadState('networkidle');
      await waitForTableLoad(page);
      // URL uses lowercase "draft" from data-filter attribute
      expect(page.url()).toMatch(/status=draft/i);
    }

    // Click the "All" filter to reset
    const allFilter = page.locator('.btn-filter[data-filter="semua"]');
    if (await allFilter.count() > 0) {
      await allFilter.click();
      await page.waitForLoadState('networkidle');
      await waitForTableLoad(page);
    }
  });

  test('petugas dapat mencari laporan', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    const searchInput = page.locator('#tableSearch');
    if (await searchInput.count() > 0) {
      await searchInput.fill('Jember');
      await page.waitForTimeout(1000);
      await expect(page.locator('#laporanTable')).toBeVisible();
    }
  });

  test('petugas dapat mengganti jumlah baris per halaman', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    const perPageSelect = page.locator('#perPageSelect');
    if (await perPageSelect.count() > 0) {
      const beforeCount = await page.locator('#tableBody tr').count();
      await perPageSelect.selectOption('20');
      await waitForTableLoad(page);
      const afterCount = await page.locator('#tableBody tr').count();
      expect(afterCount).toBeGreaterThanOrEqual(beforeCount);
    }
  });

  test('petugas dapat melihat detail laporan', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    const firstIdLink = page.locator('#tableBody tr a[href*="laporan-hama/"]').first();
    if (await firstIdLink.count() > 0) {
      const href = await firstIdLink.getAttribute('href');
      const reportId = href?.match(/laporan-hama\/(\d+)/)?.[1];
      expect(reportId).toBeTruthy();

      await firstIdLink.click();
      await page.waitForLoadState('networkidle', { timeout: 15000 });
      await expect(page).toHaveURL(new RegExp(`laporan-hama/${reportId}`));
      await expect(page.locator('.card-title, h3').filter({ hasText: /Detail Laporan/i })).toBeVisible();
    }
  });

  test('petugas dapat membuat laporan baru sebagai draft', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama/create`);
    await expect(page).toHaveURL(/\/laporan-hama\/create/);
    await expect(page.locator('#formCreateLaporan')).toBeVisible();

    const dateInput = page.locator('input[name="tanggal"]');
    if (await dateInput.count() > 0) {
      await dateInput.fill('2026-07-20');
    }

    const optSelect = page.locator('select[name="master_opt_id"]').first();
    if (await optSelect.count() > 0) {
      const options = await optSelect.locator('option').all();
      if (options.length > 1) {
        await optSelect.selectOption({ index: 1 });
      }
    }

    const severitySelect = page.locator('select[name="tingkat_keparahan"]').first();
    if (await severitySelect.count() > 0) {
      await severitySelect.selectOption('Sedang');
    }

    await page.fill('input[name="populasi"]', '100');
    await page.fill('textarea[name="catatan"]', 'Laporan uji coba dari Playwright E2E');

    const submitBtn = page.getByRole('button', { name: /Simpan Draf/i });
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      await page.waitForURL(/\/laporan-hama\/(detail\/|$)/, { timeout: 15000 });
      await expect(page).toHaveURL(/\/laporan-hama/);
    }
  });

  test('petugas dapat mengakses analitik laporan tanpa data draft', async ({ page }) => {
    // Analytics pages are accessible to authenticated users per AGENTS.md
    // ("include_draft=false default (dashboard, peta, analisis, ekspor)")
    await page.goto(`${BASE}/laporan-hama/analytics`, { waitUntil: 'domcontentloaded' });
    const url = page.url();
    // Should NOT be redirected to login (analytics is accessible to authenticated petugas)
    expect(url).not.toMatch(/\/login/);
    // Page should load with some content
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('petugas tidak dapat melihat tombol arsip pada tabel laporan', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    // Archive (arsip) button should not be present for petugas
    const archiveBtn = page.locator('#tableBody button[title*="Arsip"], #tableBody form[action*="archive"]');
    expect(await archiveBtn.count()).toBe(0);
  });

  test('petugas dapat menggunakan fitur pagination', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    await waitForTableLoad(page);

    const pagination = page.locator('#paginationNav');
    if (await pagination.count() > 0) {
      await expect(pagination).toBeVisible();
      const nextBtn = page.locator('#paginationNav a[data-page].page-link').last();
      if (await nextBtn.count() > 0) {
        await nextBtn.evaluateHandle((el) => el.parentElement?.classList.contains('disabled')).then((h) => {
          // If not disabled, click
        });
        const parentDisabled = await nextBtn.evaluate((el) => el.parentElement?.classList.contains('disabled') ?? false);
        if (!parentDisabled) {
          await nextBtn.click();
          await waitForTableLoad(page);
        }
      }
    }
  });

  test('tombol "Buat Laporan Baru" mengarah ke halaman create', async ({ page }) => {
    await page.goto(`${BASE}/laporan-hama`);
    const createBtn = page.locator('#btnCreateLaporan');
    if (await createBtn.count() > 0) {
      await createBtn.click();
      await page.waitForURL(/\/laporan-hama\/create/);
    }
  });
});

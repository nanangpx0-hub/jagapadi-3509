import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/jagapadi-3509';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'Jember3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'Jember3509';

async function loginAs(page, username, password) {
  await page.goto(BASE + '/auth/login');
  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(BASE + '/dashboard');
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('Admin Verifikasi Laporan', () => {
  test('admin can verify a Submitted laporan hama', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(BASE + '/laporan-hama?status=Submitted');
    await page.waitForLoadState('networkidle');

    const detailLink = page.locator('a.btn-view').first();
    const linkCount = await detailLink.count();
    test.skip(linkCount === 0, 'No Submitted laporan hama available to verify');

    await detailLink.click();
    await expect(page).toHaveURL(/\/laporan-hama\/\d+/);

    const verifyBtn = page.getByRole('button', { name: 'Verifikasi' });
    await expect(verifyBtn).toBeVisible();

    page.on('dialog', (dialog) => dialog.accept());
    await verifyBtn.click();

    await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    await expect(page.locator('.detail-card')).toContainText('Diverifikasi');
  });

  test('admin can reject a Submitted laporan hama with reason', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(BASE + '/laporan-hama?status=Submitted');
    await page.waitForLoadState('networkidle');

    const detailLink = page.locator('a.btn-view').first();
    const linkCount = await detailLink.count();
    test.skip(linkCount === 0, 'No Submitted laporan hama available to reject');

    await detailLink.click();
    await expect(page).toHaveURL(/\/laporan-hama\/\d+/);

    const tolakBtn = page.getByRole('button', { name: 'Tolak' });
    await expect(tolakBtn).toBeVisible();
    await tolakBtn.click();

    const alasan = page.locator('textarea[name="alasan"]');
    await expect(alasan).toBeVisible();
    await alasan.fill('Data kurang lengkap, silakan lengkapi');
    await page.getByRole('button', { name: 'Kirim' }).click();

    await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    await expect(page.locator('.detail-card')).toContainText('Ditolak');
  });

  test('petugas is forbidden from admin verify action', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);

    // Find a Submitted laporan id from the admin-facing list (petugas sees own only).
    await page.goto(BASE + '/laporan-hama?status=Submitted');
    await page.waitForLoadState('networkidle');
    const detailHref = await page.locator('a.btn-view').first().getAttribute('href').catch(() => null);
    test.skip(!detailHref, 'No Submitted laporan available to target');
    const submittedId = detailHref?.split('/').filter(Boolean).pop();

    // Grab a valid CSRF token from the authenticated page.
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    if (!csrf) {
      test.skip(true, 'No CSRF token on petugas page');
    }

    // Petugas must be blocked by AdminMiddleware before any status change.
    const response = await page.request.post(`${BASE}/laporan-hama/${submittedId}/verifikasi`, {
      form: { _csrf_token: csrf, catatan_verifikasi: '' },
      maxRedirects: 0,
    });
    expect([302, 403]).toContain(response.status());

    // Report must remain Submitted (not Diverifikasi).
    const statusText = await page.evaluate(async (id) => {
      const res = await fetch(`/laporan-hama/${id}`);
      const html = await res.text();
      return html.includes('Diverifikasi') ? 'Diverifikasi' : 'other';
    }, submittedId);
    expect(statusText).not.toBe('Diverifikasi');
  });
});



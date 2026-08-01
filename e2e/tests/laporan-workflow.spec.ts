import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'ChangeMePetugas!123';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'ChangeMeAdmin!123';

async function loginAs(page, username, password) {
  await page.goto(BASE + '/login');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/(dashboard|password\/change)/);
  if (page.url().includes('/password/change')) {
    await page.goto(BASE + '/dashboard');
    await page.waitForURL(/\/dashboard/);
  }
}

test.describe('Laporan Workflow', () => {
  test('petugas can create a draft laporan hama', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.goto(BASE + '/laporan-hama/create');
    await expect(page).toHaveURL(/\/laporan-hama\/create/);
    await expect(page.locator('h2')).toContainText('Buat Laporan Hama');

    await page.locator('#tanggal').fill('2026-07-19');
    await page.locator('select[name="master_opt_id"]').first().selectOption({ index: 1 });
    await page.getByRole('button', { name: /Simpan Draf/i }).click();
    await expect(page).toHaveURL(/\/laporan-hama(\/\d+)?$/);
  });

  test('petugas can create a draft laporan irigasi', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.goto(BASE + '/laporan-irigasi/create');
    await expect(page).toHaveURL(/\/laporan-irigasi\/create/);
    await expect(page.locator('h2')).toContainText('Buat Laporan Irigasi');

    await page.locator('#tanggal').fill('2026-07-19');
    await page.getByRole('button', { name: /Simpan Draf/i }).click();
    await expect(page).toHaveURL(/\/laporan-irigasi(\/\d+)?$/);
  });

  test('detail page loads for existing laporan hama', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(BASE + '/laporan-hama');
    await page.waitForLoadState('networkidle');

    const detailLink = page.locator('a.btn-view').first();
    const linkCount = await detailLink.count();
    test.skip(linkCount === 0, 'No laporan hama data available');

    await detailLink.click();
    await expect(page).toHaveURL(/\/laporan-hama\/\d+/);
    await expect(page.locator('.detail-card')).toBeVisible();
    await expect(page.locator('.detail-card h2')).toContainText('Detail Laporan Hama');
  });

  test('detail page loads for existing laporan irigasi', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(BASE + '/laporan-irigasi');
    await page.waitForLoadState('networkidle');

    const detailLink = page.locator('a.btn-view').first();
    const linkCount = await detailLink.count();
    test.skip(linkCount === 0, 'No laporan irigasi data available');

    await detailLink.click();
    await expect(page).toHaveURL(/\/laporan-irigasi\/\d+/);
    await expect(page.locator('.detail-card')).toBeVisible();
    await expect(page.locator('.detail-card h2')).toContainText('Detail Laporan Irigasi');
  });

  test('edit page loads for existing draft laporan hama as petugas', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.goto(BASE + '/laporan-hama');
    await page.waitForLoadState('networkidle');

    const editLink = page.locator('a.btn-edit').first();
    const linkCount = await editLink.count();
    test.skip(linkCount === 0, 'No editable draft laporan hama available');

    await editLink.click();
    await expect(page).toHaveURL(/\/laporan-hama\/\d+\/edit/);
    await expect(page.locator('#tanggal')).toBeVisible();
  });

  test('list page shows delete forms for drafts as petugas', async ({ page }) => {
    await loginAs(page, PETUGAS_USER, PETUGAS_PASS);
    await page.goto(BASE + '/laporan-hama');
    await page.waitForLoadState('networkidle');

    const deleteForm = page.locator('form[action*="/delete"]').first();
    const formCount = await deleteForm.count();
    test.skip(formCount === 0, 'No draft laporan hama with delete form available');

    await expect(deleteForm.locator('input[name="_csrf_token"]')).toHaveCount(1);
    await expect(deleteForm.locator('button[type="submit"]')).toContainText('Hapus');
  });
});

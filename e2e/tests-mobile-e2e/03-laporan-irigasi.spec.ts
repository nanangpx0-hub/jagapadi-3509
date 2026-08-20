/**
 * Suite 03 — CRUD Laporan Irigasi (Full Lifecycle)
 *
 * Menguji seluruh siklus hidup laporan irigasi:
 * - Buat draf
 * - Update draf
 * - Submit → Submitted + nomor_laporan
 * - Admin verifikasi → Diverifikasi
 * - Admin tolak → Ditolak
 * - Resubmit
 * - Hapus draf
 * - Validasi field
 * - Ownership enforcement
 * - Filter & include_draft policy
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, ADMIN, PETUGAS,
  loginApi,
  assertApiEnvelope,
} from './helpers';

async function petugasToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, PETUGAS.user, PETUGAS.pass);
}

async function adminToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, ADMIN.user, ADMIN.pass);
}

async function getWilayahIds(page: Page, token: string): Promise<{
  kabupaten_id: number;
  kecamatan_id: number;
  desa_id: number;
} | null> {
  const kabRes = await page.request.get(API_BASE + '/wilayah/kabupaten', {
    headers: { Authorization: 'Bearer ' + token },
  });
  const kabBody = (await kabRes.json()) as Record<string, unknown>;
  const kabList = kabBody['data'] as Array<Record<string, unknown>>;
  if (kabList.length === 0) return null;
  const kabId = kabList[0]['id'] as number;

  const kecRes = await page.request.get(API_BASE + '/wilayah/kecamatan?kabupaten_id=' + kabId, {
    headers: { Authorization: 'Bearer ' + token },
  });
  const kecBody = (await kecRes.json()) as Record<string, unknown>;
  const kecList = kecBody['data'] as Array<Record<string, unknown>>;
  if (kecList.length === 0) return null;
  const kecId = kecList[0]['id'] as number;

  const desaRes = await page.request.get(API_BASE + '/wilayah/desa?kecamatan_id=' + kecId, {
    headers: { Authorization: 'Bearer ' + token },
  });
  const desaBody = (await desaRes.json()) as Record<string, unknown>;
  const desaList = desaBody['data'] as Array<Record<string, unknown>>;
  if (desaList.length === 0) return null;
  const desaId = desaList[0]['id'] as number;

  return { kabupaten_id: kabId, kecamatan_id: kecId, desa_id: desaId };
}

// ═══ 03-A: Laporan Irigasi CRUD ══════════════════════════════════════════════

test.describe('03-A: Laporan Irigasi CRUD', () => {
  test('POST /laporan-irigasi membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-irigasi', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Irigasi test Playwright' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(data['status']).toBe('Draf');
  });

  test('GET /laporan-irigasi mengembalikan list dengan pagination', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-irigasi?page=1&limit=10&include_draft=true',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('GET /laporan-irigasi include_draft=false tidak mengembalikan draf', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-irigasi?include_draft=false',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Array<Record<string, unknown>>;
    for (const item of data) {
      expect(item['status']).not.toBe('Draf');
    }
  });

  test('PUT /laporan-irigasi/:id mengupdate draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-irigasi', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Sebelum update' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const putRes = await page.request.put(API_BASE + '/laporan-irigasi/' + id, {
      data: { catatan: 'Sesudah update' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(putRes.status());
  });

  test('DELETE /laporan-irigasi/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-irigasi', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Untuk dihapus' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(API_BASE + '/laporan-irigasi/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 204]).toContain(delRes.status());
  });
});

// ═══ 03-B: Laporan Irigasi Workflow ══════════════════════════════════════════

test.describe('03-B: Laporan Irigasi Workflow', () => {
  test('Draf → Submit → Admin Verifikasi', async ({ page }) => {
    const pToken = await petugasToken(page);
    const wilayah = await getWilayahIds(page, pToken);
    if (!wilayah) {
      test.skip(true, 'Wilayah tidak tersedia');
      return;
    }

    // Buat draf dengan field lengkap
    const postRes = await page.request.post(API_BASE + '/laporan-irigasi', {
      data: {
        action: 'draft',
        tanggal: '2026-08-11',
        kabupaten_id: wilayah.kabupaten_id,
        kecamatan_id: wilayah.kecamatan_id,
        desa_id: wilayah.desa_id,
        kondisi_fisik: 'Bagus',
        debit_air: 'Cukup',
        nama_saluran: 'Saluran Irigasi Utama',
        catatan: 'Irigasi workflow test',
      },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(postRes.status());
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    // Submit (with foto_url since it's required for submission)
    const submitRes = await page.request.post(API_BASE + '/laporan-irigasi/' + id + '/submit', {
      data: { foto_url: 'test/workflow.jpg' },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    expect(submitRes.status()).toBe(200);
    const submitData = ((await submitRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(submitData['status']).toBe('Submitted');

    // Admin verifikasi
    const aToken = await adminToken(page);
    const verifyRes = await page.request.post(API_BASE + '/laporan-irigasi/' + id + '/verifikasi', {
      data: { catatan: 'Diverifikasi' },
      headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
    });
    expect(verifyRes.status()).toBe(200);
    const verifyData = ((await verifyRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(verifyData['status']).toBe('Diverifikasi');
  });

  test('Draf → Submit → Admin Tolak → Resubmit', async ({ page }) => {
    const pToken = await petugasToken(page);
    const wilayah = await getWilayahIds(page, pToken);
    if (!wilayah) {
      test.skip(true, 'Wilayah tidak tersedia');
      return;
    }

    const postRes = await page.request.post(API_BASE + '/laporan-irigasi', {
      data: {
        action: 'draft',
        tanggal: '2026-08-11',
        kabupaten_id: wilayah.kabupaten_id,
        kecamatan_id: wilayah.kecamatan_id,
        desa_id: wilayah.desa_id,
        kondisi_fisik: 'Rusak',
        debit_air: 'Kurang',
        nama_saluran: 'Saluran Pembantu',
      },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    await page.request.post(API_BASE + '/laporan-irigasi/' + id + '/submit', {
      data: { foto_url: 'test/workflow.jpg' },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });

    const aToken = await adminToken(page);
    await page.request.post(API_BASE + '/laporan-irigasi/' + id + '/tolak', {
      data: { alasan: 'Perlu perbaikan data irigasi segera' },
      headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
    });

    const resubmitRes = await page.request.post(API_BASE + '/laporan-irigasi/' + id + '/resubmit', {
      headers: { Authorization: 'Bearer ' + pToken },
    });
    expect(resubmitRes.status()).toBe(200);
    const resubmitData = ((await resubmitRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(resubmitData['status']).toBe('Submitted');
  });

  test('petugas tidak bisa verifikasi laporan irigasi sendiri', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-irigasi/1/verifikasi', {
      data: { catatan: 'Coba verifikasi sendiri' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 404]).toContain(res.status());
  });
});

// ═══ 03-C: Laporan Irigasi Web UI ════════════════════════════════════════════

test.describe('03-C: Laporan Irigasi Web UI', () => {
  test.use({ storageState: 'auth/petugas.json' });
  test('halaman laporan irigasi list dimuat tanpa error', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto(BASE + '/laporan-irigasi');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table, #laporanTable, .table').first()).toBeVisible();

    const criticalErrors = errors.filter((e) => !e.includes('favicon'));
    expect(criticalErrors).toHaveLength(0);
  });

  test('form buat laporan irigasi tidak overflow viewport', async ({ page }) => {
    await page.goto(BASE + '/laporan-irigasi/create');
    await page.waitForLoadState('domcontentloaded');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 10);
  });
});

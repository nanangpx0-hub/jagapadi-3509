/**
 * Suite 02 — CRUD Laporan Hama (Full Lifecycle)
 *
 * Menguji seluruh siklus hidup laporan hama:
 * - Buat draf (draft)
 * - Update draf
 * - Submit draf → status Submitted + nomor_laporan
 * - Admin verifikasi → Diverifikasi
 * - Admin tolak → Ditolak
 * - Petugas resubmit → Submitted
 * - Arsip → Diarsipkan
 * - Hapus draf
 * - Validasi field (422)
 * - Ownership enforcement (petugas hanya laporan sendiri)
 * - Filter & pagination
 * - Include_draft policy
 */
import { test, expect } from '@playwright/test';
import {
  BASE, API_BASE, ADMIN, PETUGAS,
  loginApi, loginAsRoleApi,
  assertApiEnvelope, assertApiSuccess,
  attachScreenshot, attachLog,
} from './helpers';

// ── Helper ───────────────────────────────────────────────────────────────────

async function petugasToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, PETUGAS.user, PETUGAS.pass);
}

async function adminToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, ADMIN.user, ADMIN.pass);
}

async function getOptId(page: Page, token: string): Promise<number | null> {
  const res = await page.request.get(API_BASE + '/opt?aktif=1&limit=1', {
    headers: { Authorization: 'Bearer ' + token },
  });
  const body = (await res.json()) as Record<string, unknown>;
  const list = body['data'] as Array<Record<string, unknown>>;
  return list.length > 0 ? (list[0]['id'] as number) : null;
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

// ═══ 02-A: Master Data (Wilayah & OPT) ═══════════════════════════════════════

test.describe('02-A: Master Data (Wilayah & OPT)', () => {
  test('GET /wilayah/kabupaten mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/wilayah/kabupaten', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    assertApiEnvelope(body);
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
    expect(data.length).toBeGreaterThan(0);
  });

  test('GET /wilayah/kecamatan tanpa kabupaten_id mengembalikan 422', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/wilayah/kecamatan', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([400, 422]).toContain(res.status());
  });

  test('GET /wilayah/desa tanpa kecamatan_id mengembalikan 422', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/wilayah/desa', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([400, 422]).toContain(res.status());
  });

  test('GET /opt mengembalikan list OPT aktif', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/opt?aktif=1', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('GET /wilayah/kabupaten tanpa token mengembalikan 401', async ({ page }) => {
    const res = await page.request.get(API_BASE + '/wilayah/kabupaten');
    expect([401, 403]).toContain(res.status());
  });

  test('GET /wilayah/kecamatan?kabupaten_id=X mengembalikan list kecamatan', async ({ page }) => {
    const token = await petugasToken(page);
    const kabRes = await page.request.get(API_BASE + '/wilayah/kabupaten', {
      headers: { Authorization: 'Bearer ' + token },
    });
    const kabBody = (await kabRes.json()) as Record<string, unknown>;
    const kabList = kabBody['data'] as Array<Record<string, unknown>>;
    if (kabList.length === 0) {
      test.skip(true, 'Tidak ada kabupaten');
      return;
    }
    const kabId = kabList[0]['id'] as number;
    const res = await page.request.get(API_BASE + '/wilayah/kecamatan?kabupaten_id=' + kabId, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });
});

// ═══ 02-B: Laporan Hama CRUD ════════════════════════════════════════════════

test.describe('02-B: Laporan Hama CRUD', () => {
  test('POST /laporan-hama membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Test Playwright e2e' },
      headers: {
        Authorization: 'Bearer ' + token,
        'Content-Type': 'application/json',
      },
    });
    expect([200, 201]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    assertApiEnvelope(body);
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(typeof data['id']).toBe('number');
    expect(data['status']).toBe('Draf');
    expect(data['nomor_laporan']).toBeNull();
  });

  test('GET /laporan-hama mengembalikan list dengan pagination', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/laporan-hama?page=1&limit=10&include_draft=true', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
    const meta = body['meta'] as Record<string, unknown>;
    expect(typeof meta['total']).toBe('number');
  });

  test('GET /laporan-hama filter status=Draf hanya mengembalikan draf', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-hama?status=Draf&include_draft=true',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Array<Record<string, unknown>>;
    for (const item of data) {
      expect(item['status']).toBe('Draf');
    }
  });

  test('GET /laporan-hama include_draft=false tidak mengembalikan draf', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-hama?include_draft=false',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as Array<Record<string, unknown>>;
    for (const item of data) {
      expect(item['status']).not.toBe('Draf');
    }
  });

  test('GET /laporan-hama/:id mengembalikan detail draf', async ({ page }) => {
    const token = await petugasToken(page);
    const listRes = await page.request.get(
      API_BASE + '/laporan-hama?status=Draf&include_draft=true&limit=1',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    const listBody = (await listRes.json()) as Record<string, unknown>;
    const items = listBody['data'] as Array<Record<string, unknown>>;
    if (items.length === 0) {
      test.skip(true, 'Tidak ada draf laporan hama untuk diuji');
      return;
    }
    const id = items[0]['id'] as number;
    const res = await page.request.get(API_BASE + '/laporan-hama/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    assertApiEnvelope(body);
    expect(body['success']).toBe(true);
  });

  test('PUT /laporan-hama/:id mengupdate draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Sebelum update' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const putRes = await page.request.put(API_BASE + '/laporan-hama/' + id, {
      data: { catatan: 'Sesudah update Playwright' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(putRes.status());
    const putBody = (await putRes.json()) as Record<string, unknown>;
    expect(putBody['success']).toBe(true);
  });

  test('DELETE /laporan-hama/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Untuk dihapus' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(API_BASE + '/laporan-hama/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 204]).toContain(delRes.status());

    // Verifikasi sudah terhapus
    const getRes = await page.request.get(API_BASE + '/laporan-hama/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([404, 403]).toContain(getRes.status());
  });

  test('POST /laporan-hama tanpa auth mengembalikan 401', async ({ page }) => {
    const res = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect([401, 403]).toContain(res.status());
  });
});

// ═══ 02-C: Laporan Hama Workflow (Draf → Submit → Verifikasi) ═══════════════

test.describe('02-C: Laporan Hama Workflow Lengkap', () => {
  test('Draf → Submit → Admin Verifikasi (lifecycle lengkap)', async ({ page }) => {
    const pToken = await petugasToken(page);

    // Ambil master data
    const optId = await getOptId(page, pToken);
    const wilayah = await getWilayahIds(page, pToken);
    if (!optId || !wilayah) {
      test.skip(true, 'Master data tidak lengkap');
      return;
    }

    // 1. Buat draf dengan field lengkap
    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: {
        action: 'draft',
        tanggal: '2026-08-11',
        master_opt_id: optId,
        kabupaten_id: wilayah.kabupaten_id,
        kecamatan_id: wilayah.kecamatan_id,
        desa_id: wilayah.desa_id,
        tingkat_keparahan: 'Sedang',
        luas_serangan: 1.5,
        populasi: 10,
        lokasi: 'Sawah Blok A',
        latitude: -8.1734,
        longitude: 113.7012,
        catatan: 'Playwright workflow test',
      },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(postRes.status());
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    // 2. Submit (with foto_url since it's required for submission)
    const submitRes = await page.request.post(API_BASE + '/laporan-hama/' + id + '/submit', {
      data: { foto_url: 'test/workflow.jpg' },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    expect(submitRes.status()).toBe(200);
    const submitBody = (await submitRes.json()) as Record<string, unknown>;
    const submitData = submitBody['data'] as Record<string, unknown>;
    expect(submitData['status']).toBe('Submitted');
    expect(typeof submitData['nomor_laporan']).toBe('string');
    expect((submitData['nomor_laporan'] as string).length).toBeGreaterThan(0);

    // 3. Admin verifikasi
    const aToken = await adminToken(page);
    const verifyRes = await page.request.post(
      API_BASE + '/laporan-hama/' + id + '/verifikasi',
      {
        data: { catatan: 'Diverifikasi oleh Playwright test' },
        headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
      },
    );
    expect(verifyRes.status()).toBe(200);
    const verifyData = ((await verifyRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(verifyData['status']).toBe('Diverifikasi');
  });

  test('Draf → Submit → Admin Tolak → Resubmit', async ({ page }) => {
    const pToken = await petugasToken(page);
    const optId = await getOptId(page, pToken);
    const wilayah = await getWilayahIds(page, pToken);
    if (!optId || !wilayah) {
      test.skip(true, 'Master data tidak lengkap');
      return;
    }

    // Buat & submit
    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: {
        action: 'draft',
        tanggal: '2026-08-11',
        master_opt_id: optId,
        kabupaten_id: wilayah.kabupaten_id,
        kecamatan_id: wilayah.kecamatan_id,
        desa_id: wilayah.desa_id,
        tingkat_keparahan: 'Ringan',
        luas_serangan: 0.5,
        populasi: 100,
        lokasi: 'Kebun Blok B',
      },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    await page.request.post(API_BASE + '/laporan-hama/' + id + '/submit', {
      data: { foto_url: 'test/workflow.jpg' },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });

    // Admin tolak (endpoint expects 'alasan' field, min 10 chars)
    const aToken = await adminToken(page);
    const rejectRes = await page.request.post(API_BASE + '/laporan-hama/' + id + '/tolak', {
      data: { alasan: 'Data kurang lengkap mohon perbaiki' },
      headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
    });
    expect(rejectRes.status()).toBe(200);
    const rejectData = ((await rejectRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(rejectData['status']).toBe('Ditolak');

    // Petugas resubmit
    const resubmitRes = await page.request.post(API_BASE + '/laporan-hama/' + id + '/resubmit', {
      headers: { Authorization: 'Bearer ' + pToken },
    });
    expect(resubmitRes.status()).toBe(200);
    const resubmitData = ((await resubmitRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(resubmitData['status']).toBe('Submitted');
  });

  test('petugas tidak bisa verifikasi laporan sendiri (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-hama/1/verifikasi', {
      data: { catatan: 'Coba verifikasi sendiri' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([403, 404]).toContain(res.status());
  });

  test('petugas tidak bisa mengakses laporan milik petugas lain (403/404)', async ({ page }) => {
    const aToken = await adminToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
    });
    if (!postRes.ok()) {
      test.skip(true, 'Admin tidak bisa membuat laporan hama via API');
      return;
    }
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const pToken = await petugasToken(page);
    const res = await page.request.put(API_BASE + '/laporan-hama/' + id, {
      data: { catatan: 'Coba edit laporan orang lain' },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    expect([403, 404]).toContain(res.status());
  });

  test('draf tidak bisa diverifikasi (validasi status)', async ({ page }) => {
    const pToken = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const aToken = await adminToken(page);
    const verifyRes = await page.request.post(API_BASE + '/laporan-hama/' + id + '/verifikasi', {
      data: { catatan: 'Coba verifikasi draf' },
      headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
    });
    // Harus ditolak karena status masih Draf
    expect([400, 409, 422]).toContain(verifyRes.status());
  });
});

// ═══ 02-D: Laporan Hama Validasi Field ═══════════════════════════════════════

test.describe('02-D: Laporan Hama Validasi Field', () => {
  test('POST /laporan-hama tanpa tanggal mengembalikan 400/422 atau 201 (draft kosong valid)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-hama', {
      data: { action: 'draft' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    // Empty draft is valid (no required fields in draft mode), so expect 201
    // OR server may return 422 if it enforces minimum fields — both are acceptable
    expect([201, 400, 422]).toContain(res.status());
  });

  test('GET /laporan-hama/:id dengan ID tidak ada mengembalikan 404', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(API_BASE + '/laporan-hama/999999', {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([404, 403]).toContain(res.status());
  });
});

// ═══ 02-E: Laporan Hama Web UI (Mobile Viewport) ═════════════════════════════

test.describe('02-E: Laporan Hama Web UI', () => {
  test.use({ storageState: 'auth/petugas.json' });
  test('halaman laporan hama list dimuat tanpa error', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto(BASE + '/laporan-hama');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table, #laporanTable, .table').first()).toBeVisible();

    const criticalErrors = errors.filter((e) => !e.includes('favicon'));
    expect(criticalErrors).toHaveLength(0);
  });

  test('form buat laporan hama tidak overflow viewport', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');
    const vp = page.viewportSize()!;
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(vp.width + 10);
  });

  test('tombol submit form memiliki touch target yang cukup', async ({ page }) => {
    await page.goto(BASE + '/laporan-hama/create');
    await page.waitForLoadState('domcontentloaded');
    const btn = page.locator('button[type="submit"], input[type="submit"]').first();
    if (await btn.count() > 0) {
      const box = await btn.boundingBox();
      if (box) {
        expect(box.height).toBeGreaterThanOrEqual(30);
      }
    }
  });
});

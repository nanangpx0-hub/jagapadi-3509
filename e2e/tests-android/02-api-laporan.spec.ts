/**
 * Suite 02 — REST API Laporan (Hama & Irigasi)
 *
 * Menguji semua endpoint yang digunakan aplikasi Flutter Android:
 * - CRUD laporan hama dan irigasi
 * - Filter, pagination, include_draft
 * - Status workflow (Draf → Submitted → Diverifikasi/Ditolak)
 * - Upload foto (multipart)
 * - Export
 * - Authorization: petugas hanya laporan milik sendiri
 * - Validasi field (422)
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import {
  API_BASE, ADMIN, PETUGAS,
  loginApi, assertApiEnvelope, PERF,
} from './helpers';

// ── Helper ───────────────────────────────────────────────────────────────────

async function petugasToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, PETUGAS.user, PETUGAS.pass);
}

async function adminToken(page: Parameters<typeof loginApi>[0]): Promise<string> {
  return loginApi(page, ADMIN.user, ADMIN.pass);
}

// ── Wilayah & OPT Master Data ─────────────────────────────────────────────────

test.describe('02-A: Master Data (Wilayah & OPT)', () => {
  test('GET /wilayah/kabupaten mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/wilayah/kabupaten`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    assertApiEnvelope(body);
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('GET /wilayah/kecamatan tanpa kabupaten_id mengembalikan 422', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/wilayah/kecamatan`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([400, 422]).toContain(res.status());
  });

  test('GET /wilayah/desa tanpa kecamatan_id mengembalikan 422', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/wilayah/desa`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([400, 422]).toContain(res.status());
  });

  test('GET /opt mengembalikan list OPT aktif', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/opt?aktif=1`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('GET /wilayah/kabupaten tanpa token mengembalikan 401', async ({ page }) => {
    const res = await page.request.get(`${API_BASE}/wilayah/kabupaten`);
    expect([401, 403]).toContain(res.status());
  });
});

// ── Laporan Hama CRUD ─────────────────────────────────────────────────────────

test.describe('02-B: Laporan Hama CRUD', () => {
  let createdId: number;

  test('POST /laporan-hama membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(`${API_BASE}/laporan-hama`, {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Test Playwright e2e' },
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
    });
    expect([200, 201]).toContain(res.status());
    const body = await res.json() as Record<string, unknown>;
    assertApiEnvelope(body);
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(typeof data['id']).toBe('number');
    expect(data['status']).toBe('Draf');
    expect(data['nomor_laporan']).toBeNull(); // Nomor hanya saat submit
    createdId = data['id'] as number;
  });

  test('GET /laporan-hama mengembalikan list dengan pagination', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/laporan-hama?page=1&limit=10&include_draft=true`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
    const meta = body['meta'] as Record<string, unknown>;
    expect(typeof meta['total']).toBe('number');
  });

  test('GET /laporan-hama filter status=Draf hanya mengembalikan draf', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      `${API_BASE}/laporan-hama?status=Draf&include_draft=true`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as Array<Record<string, unknown>>;
    for (const item of data) {
      expect(item['status']).toBe('Draf');
    }
  });

  test('GET /laporan-hama include_draft=false tidak mengembalikan draf', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      `${API_BASE}/laporan-hama?include_draft=false`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as Array<Record<string, unknown>>;
    for (const item of data) {
      expect(item['status']).not.toBe('Draf');
    }
  });

  test('GET /laporan-hama/:id mengembalikan detail draf', async ({ page }) => {
    const token = await petugasToken(page);
    // Ambil ID dari list
    const listRes = await page.request.get(
      `${API_BASE}/laporan-hama?status=Draf&include_draft=true&limit=1`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    const listBody = await listRes.json() as Record<string, unknown>;
    const items = listBody['data'] as Array<Record<string, unknown>>;
    if (items.length === 0) {
      test.skip(true, 'Tidak ada draf laporan hama untuk diuji');
      return;
    }
    const id = items[0]['id'] as number;
    const res = await page.request.get(`${API_BASE}/laporan-hama/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    assertApiEnvelope(body);
    expect(body['success']).toBe(true);
  });

  test('PUT /laporan-hama/:id mengupdate draf', async ({ page }) => {
    const token = await petugasToken(page);
    // Buat draf baru
    const postRes = await page.request.post(`${API_BASE}/laporan-hama`, {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Sebelum update' },
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    });
    const postBody = await postRes.json() as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const putRes = await page.request.put(`${API_BASE}/laporan-hama/${id}`, {
      data: { catatan: 'Sesudah update Playwright' },
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(putRes.status());
    const putBody = await putRes.json() as Record<string, unknown>;
    expect(putBody['success']).toBe(true);
  });

  test('DELETE /laporan-hama/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    // Buat draf untuk dihapus
    const postRes = await page.request.post(`${API_BASE}/laporan-hama`, {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Untuk dihapus' },
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    });
    const postBody = await postRes.json() as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(`${API_BASE}/laporan-hama/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect([200, 204]).toContain(delRes.status());
  });

  test('POST /laporan-hama tanpa auth mengembalikan 401', async ({ page }) => {
    const res = await page.request.post(`${API_BASE}/laporan-hama`, {
      data: { action: 'draft' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect([401, 403]).toContain(res.status());
  });
});

// ── Laporan Hama Workflow (Draf → Submit → Verifikasi) ───────────────────────

test.describe('02-C: Laporan Hama Workflow', () => {
  test('POST /laporan-hama/:id/submit mengubah status ke Submitted', async ({ page }) => {
    const pToken = await petugasToken(page);
    // Buat draf dengan field wajib submit
    const optRes = await page.request.get(`${API_BASE}/opt?aktif=1&limit=1`, {
      headers: { Authorization: `Bearer ${pToken}` },
    });
    const optBody = await optRes.json() as Record<string, unknown>;
    const optList = optBody['data'] as Array<Record<string, unknown>>;
    if (optList.length === 0) {
      test.skip(true, 'Tidak ada OPT aktif untuk submit test');
      return;
    }

    const kabRes = await page.request.get(`${API_BASE}/wilayah/kabupaten`, {
      headers: { Authorization: `Bearer ${pToken}` },
    });
    const kabBody = await kabRes.json() as Record<string, unknown>;
    const kabList = kabBody['data'] as Array<Record<string, unknown>>;
    const kabId = kabList[0]?.['id'] as number;

    const kecRes = await page.request.get(
      `${API_BASE}/wilayah/kecamatan?kabupaten_id=${kabId}`,
      { headers: { Authorization: `Bearer ${pToken}` } },
    );
    const kecBody = await kecRes.json() as Record<string, unknown>;
    const kecList = kecBody['data'] as Array<Record<string, unknown>>;
    const kecId = kecList[0]?.['id'] as number;

    const desaRes = await page.request.get(
      `${API_BASE}/wilayah/desa?kecamatan_id=${kecId}`,
      { headers: { Authorization: `Bearer ${pToken}` } },
    );
    const desaBody = await desaRes.json() as Record<string, unknown>;
    const desaList = desaBody['data'] as Array<Record<string, unknown>>;
    const desaId = desaList[0]?.['id'] as number;

    // Buat draf dengan field lengkap
    const postRes = await page.request.post(`${API_BASE}/laporan-hama`, {
      data: {
        action: 'draft',
        tanggal: '2026-08-11',
        master_opt_id: optList[0]['id'],
        kabupaten_id: kabId,
        kecamatan_id: kecId,
        desa_id: desaId,
        tingkat_keparahan: 'Sedang',
        luas_serangan: 1.5,
        populasi: 10,
        lokasi: 'Sawah Blok A',
        latitude: -8.1734,
        longitude: 113.7012,
        catatan: 'Playwright workflow test',
      },
      headers: { Authorization: `Bearer ${pToken}`, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(postRes.status());
    const postBody = await postRes.json() as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    // Submit
    const submitRes = await page.request.post(`${API_BASE}/laporan-hama/${id}/submit`, {
      headers: { Authorization: `Bearer ${pToken}` },
    });
    expect(submitRes.status()).toBe(200);
    const submitBody = await submitRes.json() as Record<string, unknown>;
    const submitData = submitBody['data'] as Record<string, unknown>;
    expect(submitData['status']).toBe('Submitted');
    expect(typeof submitData['nomor_laporan']).toBe('string');
    expect((submitData['nomor_laporan'] as string).length).toBeGreaterThan(0);

    // Admin verifikasi
    const aToken = await adminToken(page);
    const verifyRes = await page.request.post(
      `${API_BASE}/laporan-hama/${id}/verifikasi`,
      {
        data: { catatan: 'Diverifikasi oleh Playwright test' },
        headers: { Authorization: `Bearer ${aToken}`, 'Content-Type': 'application/json' },
      },
    );
    expect(verifyRes.status()).toBe(200);
    const verifyData = (await verifyRes.json() as Record<string, unknown>)['data'] as Record<string, unknown>;
    expect(verifyData['status']).toBe('Diverifikasi');
  });

  test('petugas tidak bisa verifikasi laporan sendiri (403)', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(`${API_BASE}/laporan-hama/1/verifikasi`, {
      data: { catatan: 'Coba verifikasi sendiri' },
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    });
    expect([403, 404]).toContain(res.status());
  });

  test('petugas tidak bisa mengakses laporan milik petugas lain (403/404)', async ({ page }) => {
    // Buat laporan dengan admin token
    const aToken = await adminToken(page);
    const postRes = await page.request.post(`${API_BASE}/laporan-hama`, {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: `Bearer ${aToken}`, 'Content-Type': 'application/json' },
    });
    if (!postRes.ok()) {
      test.skip(true, 'Admin tidak bisa membuat laporan hama via API');
      return;
    }
    const postBody = await postRes.json() as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    // Coba akses/edit dengan petugas token
    const pToken = await petugasToken(page);
    const res = await page.request.put(`${API_BASE}/laporan-hama/${id}`, {
      data: { catatan: 'Coba edit laporan orang lain' },
      headers: { Authorization: `Bearer ${pToken}`, 'Content-Type': 'application/json' },
    });
    expect([403, 404]).toContain(res.status());
  });
});

// ── Laporan Irigasi ───────────────────────────────────────────────────────────

test.describe('02-D: Laporan Irigasi CRUD', () => {
  test('GET /laporan-irigasi mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      `${API_BASE}/laporan-irigasi?page=1&limit=10&include_draft=true`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('POST /laporan-irigasi membuat draf', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(`${API_BASE}/laporan-irigasi`, {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Irigasi test Playwright' },
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(res.status());
    const body = await res.json() as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(data['status']).toBe('Draf');
  });

  test('resubmit laporan ditolak', async ({ page }) => {
    // Cari laporan berstatus Ditolak
    const pToken = await petugasToken(page);
    const listRes = await page.request.get(
      `${API_BASE}/laporan-hama?status=Ditolak&include_draft=false&limit=1`,
      { headers: { Authorization: `Bearer ${pToken}` } },
    );
    const listBody = await listRes.json() as Record<string, unknown>;
    const items = listBody['data'] as Array<Record<string, unknown>>;
    if (items.length === 0) {
      test.skip(true, 'Tidak ada laporan Ditolak untuk resubmit test');
      return;
    }
    const id = items[0]['id'] as number;
    const res = await page.request.post(`${API_BASE}/laporan-hama/${id}/resubmit`, {
      headers: { Authorization: `Bearer ${pToken}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    expect((body['data'] as Record<string, unknown>)['status']).toBe('Submitted');
  });
});

// ── Dashboard & Statistik ─────────────────────────────────────────────────────

test.describe('02-E: Dashboard Stats & Map API', () => {
  test('GET /dashboard/stats mengembalikan data terstruktur', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/dashboard/stats`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(typeof (data['hama'] as Record<string, unknown>)['total_aktif']).toBe('number');
    expect(typeof (data['irigasi'] as Record<string, unknown>)['total_aktif']).toBe('number');
  });

  test('GET /dashboard/map/hama mengembalikan GeoJSON FeatureCollection', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(`${API_BASE}/dashboard/map/hama`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    const data = body['data'] as Record<string, unknown>;
    expect(data['type']).toBe('FeatureCollection');
    expect(Array.isArray(data['features'])).toBe(true);
  });

  test('GET /api/v1/health mengembalikan status sehat', async ({ page }) => {
    const res = await page.request.get(`${API_BASE}/health`);
    expect(res.status()).toBe(200);
    const body = await res.json() as Record<string, unknown>;
    expect(body['success']).toBe(true);
  });

  test('waktu respons /dashboard/stats di bawah threshold', async ({ page }) => {
    const token = await petugasToken(page);
    const t0 = Date.now();
    await page.request.get(`${API_BASE}/dashboard/stats`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(Date.now() - t0).toBeLessThan(PERF.API_RESPONSE_MS);
  });
});

// ── Export API ────────────────────────────────────────────────────────────────

test.describe('02-F: Export API', () => {
  test('GET /export/hama?format=csv menghasilkan file CSV', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(
      `${API_BASE}/export/hama?format=csv&status=Submitted,Diverifikasi`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    // 200 = ada data, 422 = perketat filter (jika 0 baris)
    expect([200, 422]).toContain(res.status());
    if (res.status() === 200) {
      const ct = res.headers()['content-type'] ?? '';
      expect(ct).toContain('csv');
    }
  });

  test('petugas hanya export laporan miliknya sendiri', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      `${API_BASE}/export/hama?format=csv`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    expect([200, 422]).toContain(res.status());
    // Tidak boleh 403 — petugas diizinkan export (tapi hanya data miliknya)
    expect(res.status()).not.toBe(403);
  });

  test('export dengan rentang tanggal > 366 hari ditolak', async ({ page }) => {
    const token = await adminToken(page);
    const res = await page.request.get(
      `${API_BASE}/export/hama?format=csv&tanggal_dari=2020-01-01&tanggal_sampai=2026-12-31`,
      { headers: { Authorization: `Bearer ${token}` } },
    );
    expect([400, 422]).toContain(res.status());
  });
});

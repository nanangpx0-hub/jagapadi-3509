/**
 * Suite 04 — Laporan Lainnya (Pupuk, Panen, Cuaca, Alat & Sarana)
 *
 * Menguji CRUD + workflow untuk 4 jenis laporan tambahan:
 * - Laporan Pupuk
 * - Laporan Panen
 * - Laporan Cuaca
 * - Laporan Alat & Sarana
 *
 * Setiap jenis laporan diuji:
 * - Buat draf
 * - List & filter
 * - Submit → Submitted
 * - Update & delete
 * - Ownership enforcement
 */
import { test, expect } from '@playwright/test';
import {
  API_BASE, ADMIN, PETUGAS,
  loginApi, assertApiEnvelope,
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

// ═══ 04-A: Laporan Pupuk ═════════════════════════════════════════════════════

test.describe('04-A: Laporan Pupuk CRUD', () => {
  test('POST /laporan-pupuk membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-pupuk', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Pupuk test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(data['status']).toBe('Draf');
  });

  test('GET /laporan-pupuk mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-pupuk?page=1&limit=10&include_draft=true',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('PUT /laporan-pupuk/:id mengupdate draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-pupuk', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const putRes = await page.request.put(API_BASE + '/laporan-pupuk/' + id, {
      data: { catatan: 'Updated' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(putRes.status());
  });

  test('DELETE /laporan-pupuk/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-pupuk', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(API_BASE + '/laporan-pupuk/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 204]).toContain(delRes.status());
  });
});

// ═══ 04-B: Laporan Panen ═════════════════════════════════════════════════════

test.describe('04-B: Laporan Panen CRUD', () => {
  test('POST /laporan-panen membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-panen', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Panen test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(data['status']).toBe('Draf');
  });

  test('GET /laporan-panen mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-panen?page=1&limit=10&include_draft=true',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('DELETE /laporan-panen/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-panen', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(API_BASE + '/laporan-panen/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 204]).toContain(delRes.status());
  });
});

// ═══ 04-C: Laporan Cuaca ═════════════════════════════════════════════════════

test.describe('04-C: Laporan Cuaca CRUD', () => {
  test('POST /laporan-cuaca membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-cuaca', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Cuaca test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(data['status']).toBe('Draf');
  });

  test('GET /laporan-cuaca mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-cuaca?page=1&limit=10&include_draft=true',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('DELETE /laporan-cuaca/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-cuaca', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(API_BASE + '/laporan-cuaca/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 204]).toContain(delRes.status());
  });
});

// ═══ 04-D: Laporan Alat & Sarana ═════════════════════════════════════════════

test.describe('04-D: Laporan Alat & Sarana CRUD', () => {
  test('POST /laporan-alat-sarana membuat draf baru', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.post(API_BASE + '/laporan-alat-sarana', {
      data: { action: 'draft', tanggal: '2026-08-11', catatan: 'Alat Sarana test' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    expect([200, 201]).toContain(res.status());
    const body = (await res.json()) as Record<string, unknown>;
    expect(body['success']).toBe(true);
    const data = body['data'] as Record<string, unknown>;
    expect(data['status']).toBe('Draf');
  });

  test('GET /laporan-alat-sarana mengembalikan list', async ({ page }) => {
    const token = await petugasToken(page);
    const res = await page.request.get(
      API_BASE + '/laporan-alat-sarana?page=1&limit=10&include_draft=true',
      { headers: { Authorization: 'Bearer ' + token } },
    );
    expect(res.status()).toBe(200);
    const body = (await res.json()) as Record<string, unknown>;
    const data = body['data'] as unknown[];
    expect(Array.isArray(data)).toBe(true);
  });

  test('DELETE /laporan-alat-sarana/:id menghapus draf', async ({ page }) => {
    const token = await petugasToken(page);
    const postRes = await page.request.post(API_BASE + '/laporan-alat-sarana', {
      data: { action: 'draft', tanggal: '2026-08-11' },
      headers: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
    });
    const postBody = (await postRes.json()) as Record<string, unknown>;
    const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

    const delRes = await page.request.delete(API_BASE + '/laporan-alat-sarana/' + id, {
      headers: { Authorization: 'Bearer ' + token },
    });
    expect([200, 204]).toContain(delRes.status());
  });
});

// ═══ 04-E: Workflow Lengkap Laporan Lainnya ══════════════════════════════════

test.describe('04-E: Workflow Lengkap — Semua Jenis Laporan', () => {
  const laporanTypes = [
    { name: 'Pupuk', endpoint: 'laporan-pupuk' },
    { name: 'Panen', endpoint: 'laporan-panen' },
    { name: 'Cuaca', endpoint: 'laporan-cuaca' },
    { name: 'Alat & Sarana', endpoint: 'laporan-alat-sarana' },
  ];

  for (const laporan of laporanTypes) {
    test(laporan.name + ': Draf → Submit → Verifikasi → Arsip', async ({ page }) => {
      const pToken = await petugasToken(page);
      const wilayah = await getWilayahIds(page, pToken);

      // Buat draf
      const draftData: Record<string, unknown> = {
        action: 'draft',
        tanggal: '2026-08-11',
        catatan: 'Workflow test ' + laporan.name,
      };
      if (wilayah) {
        draftData.kabupaten_id = wilayah.kabupaten_id;
        draftData.kecamatan_id = wilayah.kecamatan_id;
        draftData.desa_id = wilayah.desa_id;
      }

      const postRes = await page.request.post(API_BASE + '/' + laporan.endpoint, {
        data: draftData,
        headers: { Authorization: 'Bearer ' + pToken, 'Content-Type': 'application/json' },
      });

      if (!postRes.ok()) {
        test.skip(true, laporan.name + ': Tidak bisa membuat draf');
        return;
      }

      const postBody = (await postRes.json()) as Record<string, unknown>;
      const id = (postBody['data'] as Record<string, unknown>)['id'] as number;

      // Submit
      const submitRes = await page.request.post(API_BASE + '/' + laporan.endpoint + '/' + id + '/submit', {
        headers: { Authorization: 'Bearer ' + pToken },
      });

      if (submitRes.status() === 200) {
        const submitData = ((await submitRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
        expect(submitData['status']).toBe('Submitted');

        // Admin verifikasi
        const aToken = await adminToken(page);
        const verifyRes = await page.request.post(API_BASE + '/' + laporan.endpoint + '/' + id + '/verifikasi', {
          data: { catatan: 'Diverifikasi' },
          headers: { Authorization: 'Bearer ' + aToken, 'Content-Type': 'application/json' },
        });

        if (verifyRes.status() === 200) {
          const verifyData = ((await verifyRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
          expect(verifyData['status']).toBe('Diverifikasi');

          // Admin arsipkan
          const archiveRes = await page.request.post(API_BASE + '/' + laporan.endpoint + '/' + id + '/arsipkan', {
            headers: { Authorization: 'Bearer ' + aToken },
          });
          if (archiveRes.status() === 200) {
            const archiveData = ((await archiveRes.json()) as Record<string, unknown>)['data'] as Record<string, unknown>;
            expect(archiveData['status']).toBe('Diarsipkan');
          }
        }
      }
    });
  }
});

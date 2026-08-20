/* Audit dashboard/map JAGAPADI: capture console, network, API timing, fungsionalitas peta, responsivitas. */
const { chromium } = require('@playwright/test');
const fs = require('fs');

const BASE = 'http://localhost/jagapadi-3509';
const VPORTS = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 375, height: 667 },
];
const report = { timestamp: new Date().toISOString(), url: BASE + '/dashboard/map', storageUsed: null, pages: {} };

(async () => {
  const browser = await chromium.launch({ headless: true });
  fs.mkdirSync('e2e/reports', { recursive: true });

  const storageCandidates = ['auth/admin.json', 'auth/petugas.json'];
  let storage = null;
  for (const s of storageCandidates) {
    if (fs.existsSync(s)) { storage = JSON.parse(fs.readFileSync(s, 'utf8')); report.storageUsed = s; break; }
  }
  if (!storage) { console.log('NO STORAGE STATE. GUNAKAN LOGIN LANGSUNG.'); }
  else { /* verifikasi sesi valid via request */ }

  // Verifikasi sesi storage state masih valid (akses dashboard -> 200?)
  const verifyCtx = await browser.newContext({ storageState: storage });
  const verifyPage = await verifyCtx.newPage();
  const resp = await verifyPage.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' }).catch(e => ({ status: () => 'ERR' }));
  report.sessionValid = resp && resp.status && resp.status() === 200;
  await verifyCtx.close();

  for (const vp of VPORTS) {
    const p = { viewport: vp, loadMs: 0, domMs: 0, consoleErrors: [], pageErrors: [], failedRequests: [], apiTimings: [], map: {}, controls: {} };
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, storageState: storage });
    const page = await ctx.newPage();

    page.on('console', m => { if (m.type() === 'error') p.consoleErrors.push(m.text()); });
    page.on('pageerror', e => p.pageErrors.push(String(e)));
    page.on('requestfailed', r => p.failedRequests.push({ url: r.url(), err: r.failure()?.errorText || 'unknown' }));

    const t0 = Date.now();
    await page.goto(`${BASE}/dashboard/map`, { waitUntil: 'load', timeout: 60000 }).catch(e => p.pageErrors.push('goto:' + String(e)));
    p.loadMs = Date.now() - t0;
    await page.waitForTimeout(5000);

    p.domMs = await page.evaluate(() => Math.round(performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart));
    p.apiTimings = await page.evaluate(() => performance.getEntriesByType('resource')
      .filter(r => r.name.includes('/api/') || r.name.includes('tile.openstreetmap'))
      .map(r => ({ url: r.name, durMs: Math.round(r.duration) }))
      .sort((a, b) => a.durMs - b.durMs));

    p.map = await page.evaluate(() => {
      const out = { leaflet: typeof window.L !== 'undefined', container: !!document.getElementById('dashboardMap'), markerPaths: 0, stats: {}, layers: 0, loadingVisible: false, mapRect: null, hOverflow: false };
      const ov = document.querySelector('.leaflet-overlay-pane');
      if (ov) out.markerPaths = ov.querySelectorAll('path').length;
      ['statHama', 'statIrigasi', 'statRainfall', 'statWind', 'statKecamatan'].forEach(id => { const el = document.getElementById(id); if (el) out.stats[id] = el.textContent; });
      out.layers = document.querySelectorAll('.layer-item').length;
      out.loadingVisible = !document.getElementById('mapLoading')?.classList.contains('hidden');
      const c = document.getElementById('dashboardMap');
      if (c) out.mapRect = { w: c.clientWidth, h: c.clientHeight };
      out.hOverflow = document.body.scrollWidth > document.body.clientWidth;
      return out;
    });
    p.controls = await page.evaluate(() => ({
      refresh: !!document.getElementById('btnRefreshMap'),
      reset: !!document.getElementById('btnResetView'),
      filterYear: !!document.getElementById('filterYear'),
      filterStatus: !!document.getElementById('filterStatus'),
    }));

    await page.screenshot({ path: `e2e/reports/audit-map-${vp.name}.png`, fullPage: false }).catch(() => {});
    report.pages[vp.name] = p;
    await ctx.close();
  }

  await browser.close();
  fs.writeFileSync('e2e/reports/audit-map-report.json', JSON.stringify(report, null, 2));
  console.log('AUDIT SELESAI -> e2e/reports/audit-map-report.json');
  console.log(JSON.stringify(report, null, 2));
})().catch(e => { console.error('AUDIT GAGAL:', e); process.exit(1); });
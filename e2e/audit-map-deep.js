/* Audit mendalam: interaksi peta (zoom/pan/marker), semua layer API, verifikasi resource, responsivitas 3 viewport. */
const { chromium } = require('@playwright/test');
const fs = require('fs');

const BASE = 'http://localhost/jagapadi-3509';
const VPORTS = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 375, height: 667 },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  fs.mkdirSync('e2e/reports', { recursive: true });
  const out = { timestamp: new Date().toISOString(), pages: {} };

  // Login
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(`${BASE}/auth/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', 'audit_admin');
  await page.fill('#password', 'AuditAdmin!123');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  const storage = await ctx.storageState();
  await ctx.close();

  for (const vp of VPORTS) {
    const ctx2 = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, storageState: storage });
    const p = await ctx2.newPage();
    const rec = { viewport: vp, consoleErrors: [], pageErrors: [], failedRequests: [], apiResponses: [], interactions: {} };

    p.on('console', m => { if (m.type() === 'error') rec.consoleErrors.push(m.text().slice(0, 400)); });
    p.on('pageerror', e => rec.pageErrors.push(String(e).slice(0, 400)));
    p.on('requestfailed', r => rec.failedRequests.push({ url: r.url(), err: (r.failure() && r.failure().errorText) || 'unknown' }));
    p.on('response', r => {
      const u = r.url();
      if (u.includes('/api/dashboard/map/')) {
        rec.apiResponses.push({ status: r.status(), url: u });
      }
    });

    await p.goto(`${BASE}/dashboard/map`, { waitUntil: 'load', timeout: 60000 }).catch(e => rec.pageErrors.push('goto:' + String(e)));
    await p.waitForTimeout(3000);

    // 1. Uji zoom in/out
    rec.interactions.zoom = await p.evaluate(() => {
      const map = window.map;
      if (!map) return { ok: false, reason: 'window.map undefined' };
      const z0 = map.getZoom();
      map.zoomIn();
      const z1 = map.getZoom();
      map.zoomOut();
      map.zoomOut();
      const z2 = map.getZoom();
      return { ok: z1 === z0 + 1 && z2 === z0 - 1, z0, z1, z2 };
    });

    // 2. Uji pan
    rec.interactions.pan = await p.evaluate(() => {
      const map = window.map;
      if (!map) return { ok: false, reason: 'window.map undefined' };
      const c0 = map.getCenter();
      map.panBy([100, 50]);
      const c1 = map.getCenter();
      return { ok: c0.lat !== c1.lat || c0.lng !== c1.lng, c0: { lat: c0.lat, lng: c0.lng }, c1: { lat: c1.lat, lng: c1.lng } };
    });

    // 3. Aktifkan semua layer (klik layer-item)
    rec.interactions.layerToggle = await p.evaluate(() => {
      const items = document.querySelectorAll('.layer-item');
      const results = [];
      items.forEach(item => {
        const id = item.getAttribute('data-layer');
        if (id !== 'hama') { item.click(); results.push(id); }
      });
      return { clicked: results, total: items.length };
    });
    await p.waitForTimeout(4000);

    // 4. Hitung marker setelah semua layer aktif
    rec.interactions.markers = await p.evaluate(() => {
      const overlay = document.querySelector('.leaflet-overlay-pane');
      const paths = overlay ? overlay.querySelectorAll('path').length : 0;
      const counts = {};
      ['hama', 'irigasi', 'rainfall', 'wind'].forEach(id => {
        const el = document.getElementById('count-' + id);
        if (el) counts[id] = el.textContent;
      });
      const stats = {};
      ['statHama', 'statIrigasi', 'statRainfall', 'statWind', 'statKecamatan'].forEach(id => {
        const el = document.getElementById(id);
        if (el) stats[id] = el.textContent;
      });
      return { paths, counts, stats };
    });

    // 5. Uji filter (ganti tahun & status)
    rec.interactions.filter = await p.evaluate(() => {
      const year = document.getElementById('filterYear');
      const status = document.getElementById('filterStatus');
      const applyBtn = document.querySelector('.filter-panel-body .btn-primary');
      if (!year || !status || !applyBtn) return { ok: false, reason: 'filter elements missing' };
      year.value = '2025';
      status.value = 'Diverifikasi';
      applyBtn.click();
      return { ok: true, year: year.value, status: status.value };
    });
    await p.waitForTimeout(3000);

    // 6. Uji tombol Refresh & Reset View
    rec.interactions.buttons = await p.evaluate(() => {
      const refresh = document.getElementById('btnRefreshMap');
      const reset = document.getElementById('btnResetView');
      const map = window.map;
      if (!refresh || !reset || !map) return { ok: false, reason: 'buttons/map missing' };
      map.setView([-8.2, 113.7], 12);
      reset.click();
      const afterReset = map.getCenter();
      refresh.click();
      return { ok: true, afterReset: { lat: afterReset.lat, lng: afterReset.lng } };
    });
    await p.waitForTimeout(2000);

    // 7. Klik marker pertama (popup/info panel)
    rec.interactions.markerClick = await p.evaluate(() => {
      const overlay = document.querySelector('.leaflet-overlay-pane');
      const firstPath = overlay ? overlay.querySelector('path') : null;
      if (!firstPath) return { ok: false, reason: 'no marker path' };
      firstPath.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      const infoPanel = document.getElementById('infoPanel');
      return { ok: true, infoActive: infoPanel ? infoPanel.classList.contains('active') : false, infoTitle: document.getElementById('infoPanelTitle') ? document.getElementById('infoPanelTitle').textContent : null };
    });

    // 8. Responsivitas: ukuran peta & overflow
    rec.interactions.responsive = await p.evaluate(() => {
      const c = document.getElementById('dashboardMap');
      const mapContainer = document.querySelector('.map-container');
      const legend = document.getElementById('legendPanel');
      const controls = document.getElementById('layerPanel');
      const filter = document.getElementById('filterPanel');
      return {
        mapRect: c ? { w: c.clientWidth, h: c.clientHeight } : null,
        containerRect: mapContainer ? { w: mapContainer.clientWidth, h: mapContainer.clientHeight } : null,
        legendVisible: legend ? legend.offsetParent !== null : false,
        controlsVisible: controls ? controls.offsetParent !== null : false,
        filterVisible: filter ? filter.offsetParent !== null : false,
        hOverflow: document.body.scrollWidth > document.body.clientWidth,
        bodyScrollW: document.body.scrollWidth,
        bodyClientW: document.body.clientWidth,
      };
    });

    // 9. Verifikasi resource yang gagal (retry fetch)
    rec.interactions.resourceCheck = await p.evaluate(async () => {
      const results = {};
      const urls = [
        'http://localhost/jagapadi-3509/public/css/responsive.css',
        'http://localhost/jagapadi-3509/public/js/mobile-enhancements.js',
      ];
      for (const u of urls) {
        try {
          const r = await fetch(u, { method: 'HEAD' });
          results[u] = { status: r.status, ok: r.ok };
        } catch (e) {
          results[u] = { error: String(e) };
        }
      }
      return results;
    });

    await p.screenshot({ path: `e2e/reports/audit-deep-${vp.name}.png`, fullPage: false }).catch(() => {});
    out.pages[vp.name] = rec;
    await ctx2.close();
  }

  await browser.close();
  fs.writeFileSync('e2e/reports/audit-map-deep.json', JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
})().catch(e => { console.error('GAGAL:', e); process.exit(1); });
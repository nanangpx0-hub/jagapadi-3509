/* Audit langsung dashboard/map dengan login segar. Menangkap status, redirect, console, network, DOM. */
const { chromium } = require('@playwright/test');
const fs = require('fs');

const BASE = 'http://localhost/jagapadi-3509';
const VPORTS = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'mobile', width: 375, height: 667 },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  fs.mkdirSync('e2e/reports', { recursive: true });
  const out = { timestamp: new Date().toISOString(), login: null, pages: {} };

  // Login satu kali di context utama
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(`${BASE}/auth/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', 'audit_admin');
  await page.fill('#password', 'AuditAdmin!123');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  out.login = { url: page.url(), ok: !page.url().includes('/auth/login') };
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
  out.dashboardStatus = (await page.title()) + ' | ' + page.url();

  // Simpan storage state untuk viewport lain
  const storage = await ctx.storageState();
  await ctx.close();

  for (const vp of VPORTS) {
    const ctx2 = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, storageState: storage });
    const p = await ctx2.newPage();
    const rec = { viewport: vp, consoleErrors: [], pageErrors: [], failedRequests: [], responses: [] };

    p.on('console', m => { if (m.type() === 'error') rec.consoleErrors.push(m.text().slice(0, 300)); });
    p.on('pageerror', e => rec.pageErrors.push(String(e).slice(0, 300)));
    p.on('requestfailed', r => rec.failedRequests.push({ url: r.url().slice(0, 150), err: (r.failure() && r.failure().errorText) || 'unknown' }));
    p.on('response', r => {
      const u = r.url();
      if (u.includes('/dashboard/map') || u.includes('/api/') || u.includes('tile.openstreetmap') || u.includes('leaflet')) {
        rec.responses.push({ status: r.status(), url: u.slice(0, 150) });
      }
    });

    const t0 = Date.now();
    const resp = await p.goto(`${BASE}/dashboard/map`, { waitUntil: 'load', timeout: 60000 }).catch(e => ({ status: () => 'ERR' }));
    rec.finalUrl = p.url();
    rec.httpStatus = typeof resp.status === 'function' ? resp.status() : resp.status;
    rec.loadMs = Date.now() - t0;
    await p.waitForTimeout(5000);

    rec.dom = await p.evaluate(() => {
      const r = { title: document.title, textLen: document.body ? document.body.innerText.length : 0, hasMapDiv: !!document.getElementById('dashboardMap'), hasLoading: !!document.getElementById('mapLoading'), leaflet: typeof window.L !== 'undefined', statHama: null, statIrigasi: null, bodySnippet: '' };
      ['statHama', 'statIrigasi', 'statRainfall', 'statWind', 'statKecamatan'].forEach(id => { const el = document.getElementById(id); if (el) r[id] = el.textContent; });
      r.bodySnippet = (document.body ? document.body.innerText : '').slice(0, 500);
      return r;
    });
    rec.apiTimings = await p.evaluate(() => performance.getEntriesByType('resource')
      .filter(r => r.name.includes('/api/') || r.name.includes('tile.openstreetmap') || r.name.includes('leaflet'))
      .map(r => ({ u: r.name.slice(0, 120), ms: Math.round(r.duration) }))
      .sort((a, b) => a.ms - b.ms));

    await p.screenshot({ path: `e2e/reports/audit-live-${vp.name}.png`, fullPage: false }).catch(() => {});
    out.pages[vp.name] = rec;
    await ctx2.close();
  }

  await browser.close();
  fs.writeFileSync('e2e/reports/audit-map-live.json', JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
})().catch(e => { console.error('GAGAL:', e); process.exit(1); });
/* Verifikasi zoom/pan dengan delay untuk memastikan animasi Leaflet selesai. */
const { chromium } = require('@playwright/test');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto('http://localhost/jagapadi-3509/auth/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', 'audit_admin');
  await page.fill('#password', 'AuditAdmin!123');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await page.goto('http://localhost/jagapadi-3509/dashboard/map', { waitUntil: 'load' });
  await page.waitForTimeout(3000);

  const result = await page.evaluate(async () => {
    const m = window.map;
    if (!m) return { ok: false, reason: 'no map' };
    const out = [];
    const z0 = m.getZoom();
    m.zoomIn();
    await new Promise(r => setTimeout(r, 600));
    const z1 = m.getZoom();
    m.zoomOut();
    m.zoomOut();
    await new Promise(r => setTimeout(r, 800));
    const z2 = m.getZoom();
    out.push({ test: 'zoom', z0, z1, z2, ok: z1 === z0 + 1 && z2 === z0 - 1 });

    const c0 = m.getCenter();
    m.panBy([100, 50]);
    await new Promise(r => setTimeout(r, 600));
    const c1 = m.getCenter();
    out.push({ test: 'pan', moved: c0.lat !== c1.lat || c0.lng !== c1.lng, c0: { lat: c0.lat, lng: c0.lng }, c1: { lat: c1.lat, lng: c1.lng } });

    // Reset view
    m.setView([-8.2, 113.7], 12);
    await new Promise(r => setTimeout(r, 500));
    const preReset = m.getCenter();
    document.getElementById('btnResetView').click();
    await new Promise(r => setTimeout(r, 800));
    const postReset = m.getCenter();
    out.push({ test: 'resetView', preReset: { lat: preReset.lat, lng: preReset.lng }, postReset: { lat: postReset.lat, lng: postReset.lng }, resetWorked: Math.abs(postReset.lat - (-8.1845)) < 0.01 && Math.abs(postReset.lng - 113.6681) < 0.01 });

    return out;
  });
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})().catch(e => { console.error('GAGAL:', e); process.exit(1); });
/* Verifikasi: apakah variabel `map` global di window setelah halaman termuat? */
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
  await page.waitForTimeout(4000);
  const result = await page.evaluate(() => ({
    typeofWindowMap: typeof window.map,
    typeofMap: typeof map,
    hasInitMap: typeof initMap,
    windowKeysSample: Object.keys(window).filter(k => /map|leaflet/i.test(k)).slice(0, 20),
  }));
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})().catch(e => { console.error('GAGAL:', e); process.exit(1); });
import { test, expect } from '@playwright/test';
import { BASE } from '../base-url';

test.describe('Debug AJAX', () => {
  test.use({ storageState: 'auth/petugas.json' });

  test('trace fetch response on laporan page', async ({ page }) => {
    const logs: string[] = [];
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' || msg.type() === 'warning' || msg.text().includes('LaporanTable')) {
        logs.push(`[Console ${msg.type()}] ${msg.text()}`);
      }
    });
    page.on('pageerror', (err) => errors.push(`[PageError] ${err.message}`));
    page.on('request', (req) => {
      if (req.url().includes('laporan/fetch')) {
        logs.push(`[Request] ${req.method()} ${req.url()}`);
      }
    });
    page.on('response', (res) => {
      if (res.url().includes('laporan/fetch')) {
        logs.push(`[Response] status=${res.status()} url=${res.url()}`);
        res.text().then((body) => {
          logs.push(`[ResponseBody] ${body.substring(0, 500)}`);
        }).catch(() => {});
      }
    });

    await page.goto(`${BASE}/laporan`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(10000);

    // Dump everything
    const tableBodyHtml = await page.innerHTML('#tableBody').catch((e) => `ERROR: ${e.message}`);
    logs.push(`[tableBody HTML] ${tableBodyHtml.substring(0, 2000)}`);

    const baseUrlVal = await page.evaluate(() => {
      const scripts = document.querySelectorAll('script');
      for (const s of Array.from(scripts)) {
        if (s.textContent && s.textContent.includes('const BASE_URL')) {
          const m = s.textContent.match(/const BASE_URL = ['"]([^'"]+)['"]/);
          return m ? m[1] : 'BASE_URL found but pattern not matched';
        }
      }
      return 'BASE_URL not found';
    });
    logs.push(`[JS BASE_URL] ${baseUrlVal}`);

    console.log('\n=== DEBUG OUTPUT ===');
    logs.forEach((l) => console.log(l));
    if (errors.length > 0) {
      console.log('\n=== ERRORS ===');
      errors.forEach((e) => console.log(e));
    }
  });
});

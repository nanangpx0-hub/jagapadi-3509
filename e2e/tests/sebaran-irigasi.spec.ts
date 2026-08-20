import { test, expect } from '@playwright/test';
import { BASE } from '../base-url';

// Use saved storageState from global-setup.js for authenticated session
test.use({ storageState: 'auth/petugas.json' });

test.describe('End-to-End Testing: Sebaran Irigasi - JAGAPADI', () => {

  test('1. Verify Sebaran Irigasi page loads with 0 PHP errors or deprecation warnings', async ({ page }) => {
    await page.goto(`${BASE}/irigasi`);
    await page.waitForLoadState('networkidle');

    const pageContent = await page.content();

    // Verify absence of PHP errors, warnings, and deprecation notices
    expect(pageContent).not.toContain('Undefined array key');
    expect(pageContent).not.toContain('jenis_irigasi');
    expect(pageContent).not.toContain('Deprecated: number_format()');
    expect(pageContent).not.toContain('Fatal error');
    expect(pageContent).not.toContain('Parse error');
    expect(pageContent).not.toContain('Warning:');

    // Page title and H1 check
    await expect(page.locator('h1').first()).toContainText('Sebaran Irigasi');
  });

  test('2. Verify timbul tenggelam (pulsing/repeating) animation is disabled and elements are static', async ({ page }) => {
    await page.goto(`${BASE_URL}/irigasi`);
    await page.waitForLoadState('networkidle');

    // Check computed styles of page elements to verify animation is disabled (animation-name: none)
    const runningIndicatorAnim = await page.evaluate(() => {
      const el = document.querySelector('.running-indicator');
      return el ? window.getComputedStyle(el).animationName : 'none';
    });
    expect(runningIndicatorAnim).toBe('none');

    const bodyAnimation = await page.evaluate(() => {
      const card = document.querySelector('.card');
      return card ? window.getComputedStyle(card).animationName : 'none';
    });
    expect(bodyAnimation).toBe('none');
  });

  test('3. Verify correct data formatting for Luas Layanan (e.g., 0.00 Ha) and table contents without errors', async ({ page }) => {
    await page.goto(`${BASE_URL}/irigasi`);
    await page.waitForLoadState('networkidle');

    // Verify table structure
    const dataTable = page.locator('#dataTable');
    await expect(dataTable).toBeVisible();

    // Check table content for formatted numbers (e.g. 0.00 Ha or X.XX Ha)
    const luasTexts = await page.locator('#dataTable td small.text-muted').allTextContents();
    
    // Validate that Luas text matches formatted float pattern (e.g. "Luas: 0.00 Ha" or "Luas: 12.50 Ha")
    for (const text of luasTexts) {
      if (text.includes('Luas:')) {
        expect(text).toMatch(/Luas:\s*\d+\.\d{2}\s*Ha/);
      }
    }
  });
});

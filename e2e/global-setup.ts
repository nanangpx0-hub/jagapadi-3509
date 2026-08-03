import { chromium, FullConfig } from '@playwright/test';

/**
 * Global setup: log in once as petugas and save session state.
 * Reused across all test files to avoid rate-limiting on the login endpoint.
 *
 * Remote-browser support:
 *  Set REMOTE_WS_ENDPOINT=ws://<host>:<port>/... to run against a remote browser
 *  (BrowserStack / Sauce Labs / browserless). The browserType.connect() call
 *  will use that endpoint instead of launching a local browser.
 */
const BASE = 'http://localhost/jagapadi-3509';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'Jember3509';

export default async function globalSetup(config: FullConfig) {
  const REMOTE_WS_ENDPOINT = process.env.REMOTE_WS_ENDPOINT;
  const browser = REMOTE_WS_ENDPOINT
    ? await chromium.connect(REMOTE_WS_ENDPOINT)
    : await chromium.launch();

  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto(`${BASE}/auth/login`);
  const csrf = await page.locator('input[name="csrf_token"]').inputValue();
  await page.fill('input[name="username"]', PETUGAS_USER);
  await page.fill('input[name="password"]', PETUGAS_PASS);
  await page.click('button[type="submit"]');

  try {
    await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 30000 });
    if (page.url().includes('/password/change')) {
      await page.goto(`${BASE}/dashboard`);
      await page.waitForURL(/\/dashboard/, { timeout: 15000 });
    }
  } catch (e) {
    console.error('Login failed during globalSetup:', e);
    throw e;
  }

  await page.context().storageState({ path: 'e2e/auth/petugas.json' });
  console.log('Saved petugas session state to e2e/auth/petugas.json');
  await context.close();
  await browser.close();
}

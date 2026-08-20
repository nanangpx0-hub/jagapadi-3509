console.log('GLOBAL SETUP STARTED');
const { chromium } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost/jagapadi-3509';

async function loginAndSaveState(page, username, password, storagePath) {
  console.log(`Logging in as ${username}...`);
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  await page.context().clearCookies();
  await page.reload();
  await page.waitForLoadState('networkidle');
  console.log(`${username} navigated to login page, URL: ${page.url()}`);

  await page.fill('input[name="username"]', username);
  await page.fill('#password', password);
  console.log(`${username} filled credentials`);

  await page.click('button[type="submit"]');
  console.log(`${username} clicked submit`);

  let loggedIn = false;
  try {
    await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 30000 });
    console.log(`${username} final URL: ${page.url()}`);
    if (page.url().includes('/password/change')) {
      await page.goto(`${BASE}/dashboard`);
      await page.waitForURL(/\/dashboard/, { timeout: 15000 });
    }
    loggedIn = true;
  } catch (e) {
    console.log(`${username} URL after timeout: ${page.url()}`);
    await page.screenshot({ path: `e2e/test-results/login-fail-${username}.png` });
    console.error(`${username} login failed. Screenshot saved to e2e/test-results/login-fail-${username}.png`);
    try {
      await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 15000 });
      loggedIn = true;
    } catch (e2) {
      console.error(`${username} could not reach dashboard. Current URL: ${page.url()}`);
    }
  }
  if (loggedIn) {
    await page.context().storageState({ path: storagePath });
    console.log(`Saved session state to ${storagePath} for ${username}`);
  } else {
    console.error(`Skipping save for ${username} — login did not succeed`);
  }
}

async function runGlobalSetup() {
  console.log('GLOBAL SETUP RUNNING');
  const browser = await chromium.launch({ headless: true });
  console.log('BROWSER LAUNCHED');

  const roles = [
    { user: 'admin', pass: 'Jember3509', file: 'auth/admin.json' },
    { user: 'petugas01', pass: 'Jember3509', file: 'auth/petugas.json', retries: 2 },
    { user: 'operator01', pass: 'Jember3509', file: 'auth/operator.json' },
    { user: 'statistisi01', pass: 'Jember3509', file: 'auth/statistisi.json' },
    { user: 'viewer01', pass: 'Jember3509', file: 'auth/viewer.json' },
  ];

  for (const role of roles) {
    try {
      const context = await browser.newContext();
      const page = await context.newPage();
      const maxRetries = role.retries || 1;
      let lastError = null;
      for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
          await loginAndSaveState(page, role.user, role.pass, role.file);
          lastError = null;
          break;
        } catch (e) {
          lastError = e;
          console.warn(`Attempt ${attempt} failed for ${role.user}: ${e.message}`);
          if (attempt < maxRetries) {
            await page.waitForTimeout(2000);
          }
        }
      }
      if (lastError) {
        throw lastError;
      }
      await context.close();
    } catch (e) {
      console.error(`Failed to setup ${role.user}: ${e.message}`);
    }
  }

  await browser.close();
  console.log('Global setup completed');
}

module.exports = async function globalSetup(config) {
  await runGlobalSetup();
};

if (require.main === module) {
  runGlobalSetup().catch(e => console.error('FATAL:', e.message));
}

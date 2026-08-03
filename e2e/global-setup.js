const { chromium } = require('@playwright/test');

const BASE = 'http://localhost/jagapadi-3509';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'ChangeMeAdmin!123';
const PETUGAS_USER = 'petugas01';
const PETUGAS_PASS = 'ChangeMePetugas!123';

module.exports = async function globalSetup(config) {
  const browser = await chromium.launch({ headless: false });

  // Petugas session
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/auth/login`);
  await page.fill('input[name="username"]', PETUGAS_USER);
  await page.fill('#password', PETUGAS_PASS);
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
  await page.waitForURL(/\/(dashboard|password\/change)/, { timeout: 30000 });
  if (page.url().includes('change_password')) {
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
  await page.context().storageState({ path: 'auth/petugas.json' });
  console.log('Saved petugas session state to auth/petugas.json');
  await context.close();

  // Admin session
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await adminPage.goto(`${BASE}/auth/login`);
  await adminPage.fill('input[name="username"]', ADMIN_USER);
  await adminPage.fill('#password', ADMIN_PASS);
  await Promise.all([adminPage.waitForNavigation(), adminPage.click('button[type="submit"]')]);
  await adminPage.waitForURL(/\/(dashboard|password\/change)/, { timeout: 30000 });
  if (adminPage.url().includes('change_password')) {
    await adminPage.goto(`${BASE}/dashboard`);
    await adminPage.waitForURL(/\/dashboard/, { timeout: 15000 });
  }
  await adminPage.context().storageState({ path: 'auth/admin.json' });
  console.log('Saved admin session state to auth/admin.json');
  await adminContext.close();
  await browser.close();
};

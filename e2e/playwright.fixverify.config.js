const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests-fixverify',
  timeout: 120000,
  expect: { timeout: 20000 },
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost/jagapadi-3509',
    headless: true,
    viewport: { width: 1280, height: 720 },
    actionTimeout: 25000,
    navigationTimeout: 40000,
    screenshot: 'only-on-failure',
    launchOptions: {
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security'],
    },
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
});

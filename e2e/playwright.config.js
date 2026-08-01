const { defineConfig } = require('@playwright/test');

const REMOTE_WS_ENDPOINT = process.env.REMOTE_WS_ENDPOINT;
const CI = process.env.CI === 'true';

module.exports = defineConfig({
  testDir: './tests',
  timeout: CI ? 90000 : 120000,
  expect: { timeout: 15000 },
  fullyParallel: CI,
  workers: CI ? 2 : 1,
  retries: CI ? 2 : 0,
  reporter: [
    ['html', { outputFolder: 'reports/html' }],
    ['json', { outputFile: 'reports/test-results.json' }],
    ['list'],
    ['junit', { outputFile: 'reports/junit.xml' }],
  ],
  use: {
    baseURL: 'http://localhost:8080',
    headless: false,
    viewport: { width: 1280, height: 720 },
    actionTimeout: 20000,
    navigationTimeout: 30000,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'on-first-retry',
    launchOptions: {
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
  },
  projects: [
    {
      name: 'chromium',
      use: {
        browserName: 'chromium',
        ...(REMOTE_WS_ENDPOINT
          ? { connectOptions: { wsEndpoint: REMOTE_WS_ENDPOINT } }
          : {}),
      },
    },
  ],
});

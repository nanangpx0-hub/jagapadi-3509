const { defineConfig, devices } = require('@playwright/test');

const REMOTE_WS_ENDPOINT = process.env.REMOTE_WS_ENDPOINT;
const CI = process.env.CI === 'true';
const BASE_URL = process.env.BASE_URL || 'http://localhost/jagapadi-3509';

module.exports = defineConfig({
  testDir: './tests',
  timeout: CI ? 90000 : 120000,
  expect: { timeout: 15000 },
  fullyParallel: CI,
  workers: CI ? 2 : 1,
  retries: CI ? 1 : 0,
  globalSetup: require.resolve('./global-setup.js'),
  reporter: [
    ['html', { outputFolder: 'reports/html', open: 'never' }],
    ['json', { outputFile: 'reports/test-results.json' }],
    ['list'],
    ['junit', { outputFile: 'reports/junit.xml' }],
  ],
  use: {
    baseURL: BASE_URL,
    headless: !CI,
    viewport: { width: 1280, height: 720 },
    actionTimeout: 20000,
    navigationTimeout: 30000,
    screenshot: 'only-on-failure',
    video: CI ? 'retain-on-failure' : 'off',
    trace: 'on-first-retry',
    launchOptions: {
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-web-security',
        '--disable-dev-shm-usage',
      ],
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
    ...(CI
      ? []
      : [
          {
            name: 'firefox',
            use: { browserName: 'firefox' },
          },
          {
            name: 'webkit',
            use: { browserName: 'webkit' },
          },
        ]),
    {
      name: 'mobile-chromium',
      use: {
        ...devices['Pixel 5'],
      },
    },
    {
      name: 'tablet-chromium',
      use: {
        ...devices['iPad Pro'],
      },
    },
  ],
});

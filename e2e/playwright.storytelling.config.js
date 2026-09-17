// Dedicated config: drive the REAL installed Microsoft Edge binary
// (channel: 'msedge') for autonomous storytelling E2E.
const base = require('./playwright.config.js');

module.exports = {
  ...base,
  testDir: './tests',
  testMatch: /storytelling-dummy\.spec\.ts/,
  globalSetup: undefined,
  workers: 1,
  projects: [
    {
      name: 'edge',
      use: {
        browserName: 'chromium',
        channel: 'msedge',
        baseURL: process.env.BASE_URL || 'http://localhost/jagapadi-3509',
        headless: true,
        viewport: { width: 1280, height: 720 },
        actionTimeout: 20000,
        navigationTimeout: 30000,
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
        launchOptions: {
          args: ['--no-sandbox', '--disable-dev-shm-usage'],
        },
      },
    },
  ],
};

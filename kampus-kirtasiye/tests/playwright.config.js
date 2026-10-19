const { defineConfig } = require('@playwright/test');

const baseURL = process.env.BASE_URL || 'http://127.0.0.1:8082/';

module.exports = defineConfig({
  testDir: '.',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 20000,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'rapor' }]],
  use: {
    baseURL,
    channel: 'chrome',
    viewport: { width: 1366, height: 900 },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});

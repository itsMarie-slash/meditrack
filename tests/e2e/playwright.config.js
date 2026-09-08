// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  timeout: 15000,
  use: {
    baseURL: process.env.FRONTEND_URL || 'http://127.0.0.1:8098',
    launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH
      ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH, args: ['--no-sandbox', '--headless=new'] }
      : {},
  },
});

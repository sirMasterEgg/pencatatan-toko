import { defineConfig, devices } from '@playwright/test';

const port = 8001;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'off',
        ...devices['Pixel 5'],
        launchOptions: {
            executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined,
            args: ['--no-sandbox'],
        },
    },
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});

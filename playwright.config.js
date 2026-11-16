import { defineConfig, devices } from '@playwright/test';

const port = 8001;
const ownerPassword = 'rahasia-e2e-123';
const e2eEnv = {
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: 'database/e2e.sqlite',
    OWNER_EMAIL: 'pemilik@toko.test',
    OWNER_PASSWORD: ownerPassword,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    APP_ENV: 'local',
};

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1, // semua tes berbagi satu sesi login; paralel membuat pesan flash saling menimpa
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.js/, use: { ...devices['Pixel 5'] } },
        {
            name: 'e2e',
            testIgnore: /auth\.setup\.js/,
            dependencies: ['setup'],
            use: { ...devices['Pixel 5'], storageState: 'tests/e2e/.auth/owner.json' },
        },
    ],
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'off',
        launchOptions: {
            executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined,
            args: ['--no-sandbox'],
        },
    },
    webServer: {
        command: `touch database/e2e.sqlite && php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=${port}`,
        env: e2eEnv,
        url: `http://127.0.0.1:${port}`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});

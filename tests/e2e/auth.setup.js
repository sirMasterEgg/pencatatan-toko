import { test as setup } from '@playwright/test';
import { login } from './helpers.js';

setup('login pemilik', async ({ page }) => {
    await login(page);
    await page.context().storageState({ path: 'tests/e2e/.auth/owner.json' });
});

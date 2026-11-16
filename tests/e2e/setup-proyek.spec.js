import { test, expect } from '@playwright/test';

test.describe('Tugas 01: setup proyek', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test.describe('terautentikasi', () => {
    test.use({ storageState: 'tests/e2e/.auth/owner.json' });
    test('beranda menampilkan judul dan gaya Bootstrap', async ({ page }) => {
        const response = await page.goto('/');

        expect(response.status()).toBe(200);
        await expect(page).toHaveTitle('Beranda - Catatan Toko');
        await expect(page.getByRole('heading', { name: 'Beranda', level: 1 })).toBeVisible();
        await expect(page.getByText('Halaman ini dilengkapi di tugas 09.')).toBeVisible();

        // Bootstrap termuat: .container mendapat lebar/padding dari Bootstrap, bg-light berwarna.
        const bodyBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        expect(bodyBg).toBe('rgb(248, 249, 250)');

        const fontSize = await page.evaluate(() => getComputedStyle(document.querySelector('h1')).fontSize);
        expect(fontSize).not.toBe('32px'); // .h4 Bootstrap = 1.5rem (24px), bukan h1 bawaan browser
    });
    });

    test('halaman memakai bahasa Indonesia dan viewport HP', async ({ page }) => {
        await page.goto('/login');

        await expect(page.locator('html')).toHaveAttribute('lang', 'id');
        await expect(page.locator('meta[name="viewport"]')).toHaveAttribute('content', /width=device-width/);
        // Tamu: navbar tidak tampil.
        await expect(page.locator('nav.navbar')).toHaveCount(0);
        // Tidak ada guliran horizontal di layar HP.
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(overflow).toBe(false);
    });
});

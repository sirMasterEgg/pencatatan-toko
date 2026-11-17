import { test, expect } from '@playwright/test';

// Tugas 10: semua halaman di layar HP (Pixel 5 = 393px; lalu 375px) tanpa gulir mendatar dan tanpa galat konsol.
test('semua halaman rapi di lebar 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => m.type() === 'error' && errors.push(`${page.url()} ${m.text()}`));

    const overflow = () => page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    const check = async (url) => {
        await page.goto(url);
        expect(await overflow(), `gulir mendatar di ${url}`).toBe(false);
    };

    const suffix = Date.now();

    // Data uji: barang, pelanggan, transaksi.
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(`Barang HP ${suffix}`);
    await page.getByLabel('Satuan').selectOption('pcs');
    await page.getByLabel('Harga jual').fill('12000');
    await page.getByRole('button', { name: 'Simpan' }).click();
    const productUrl = page.url();

    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(`Pelanggan HP ${suffix}`);
    await page.getByLabel('Nomor HP').fill('0857-' + String(suffix).slice(-8));
    await page.getByRole('button', { name: 'Simpan' }).click();
    const customerUrl = page.url();

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(`Barang HP ${suffix}`);
    await page.getByRole('button', { name: new RegExp(`Barang HP ${suffix}`) }).first().click();
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    const transactionUrl = page.url();
    expect(await overflow(), 'gulir mendatar di detail transaksi').toBe(false);

    for (const url of [
        '/', '/products', '/products/create', productUrl, productUrl + '/edit',
        '/customers', '/customers/create', customerUrl, customerUrl + '/edit',
        '/transactions/create', '/transactions', transactionUrl, '/reports/daily',
    ]) {
        await check(url);
    }

    // Menu navigasi bisa dibuka dan ditutup.
    await page.goto('/');
    const toggler = page.getByRole('button', { name: 'Buka menu' });
    await toggler.click();
    await expect(page.locator('#mainNav')).toHaveClass(/\bshow\b/);
    await expect(page.getByRole('link', { name: 'Laporan', exact: true })).toBeVisible();
    await toggler.click();
    await expect(page.locator('#mainNav')).not.toHaveClass(/\bshow\b/);
    await expect(page.getByRole('link', { name: 'Laporan', exact: true })).toBeHidden();

    // Halaman login (tamu).
    await page.context().clearCookies();
    await check('/login');

    expect(errors).toEqual([]);
});

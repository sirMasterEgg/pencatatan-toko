import { test, expect } from '@playwright/test';

test('form transaksi: barang baru, pelanggan baru, total, simpan', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

    const suffix = Date.now();
    const name = `Gula ${suffix}`;

    await page.goto('/transactions/create');

    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await expect(page.getByText('Barang baru', { exact: true })).toBeVisible();

    await page.getByLabel('Harga jual').fill('18000');
    await page.getByLabel('Jumlah').fill('2');
    await page.getByLabel('Diskon').click(); // keluar dari kolom => hitung ulang
    await expect(page.getByText('Rp36.000').first()).toBeVisible();

    await page.getByPlaceholder(/Cari nama atau nomor HP/).fill('Bu Sari E2E');
    await page.getByRole('button', { name: '+ Pelanggan baru' }).click();
    await page.getByLabel('Nomor HP').fill('0813-' + String(suffix).slice(-8));

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    // Tidak ada galat konsol selama mengisi form.
    expect(errors).toEqual([]);

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
});

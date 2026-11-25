import { test, expect } from '@playwright/test';

test('pelanggan: nama dan alamat huruf besar, tampil "NAMA - ALAMAT" di form transaksi', async ({ page }) => {
    const suffix = Date.now();
    const name = `Bu Rahma ${suffix}`;

    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Alamat').fill('jl. kenanga 5');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/customers$/);

    await page.goto(`/customers?q=${suffix}`);
    await expect(page.getByText(`BU RAHMA ${suffix} - JL. KENANGA 5`)).toBeVisible();

    await page.goto('/transactions/create');
    await page.getByPlaceholder(/Cari nama atau nomor HP/).fill(String(suffix));
    await page.getByRole('button', { name: `BU RAHMA ${suffix} - JL. KENANGA 5` }).click();
    await expect(page.getByText(`BU RAHMA ${suffix} - JL. KENANGA 5`)).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});

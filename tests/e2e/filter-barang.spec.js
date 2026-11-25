import { test, expect } from '@playwright/test';

async function newProduct(page, name, unit, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption(unit);
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

test('daftar barang: filter satuan dan rentang harga, lalu urutkan', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Filter Beras ${suffix}`, 'kg', '12000');
    await newProduct(page, `Filter Gula ${suffix}`, 'kg', '18000');
    await newProduct(page, `Filter Sabun ${suffix}`, 'pcs', '4000');

    await page.goto('/products');
    await page.getByPlaceholder('Cari nama atau kode').fill(String(suffix));

    await page.getByRole('button', { name: 'Filter lainnya' }).click();
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga minimal').pressSequentially('15000');
    await expect(page.getByLabel('Harga minimal')).toHaveValue('15.000');
    await page.getByRole('button', { name: 'Cari' }).click();

    await expect(page.getByText(`FILTER GULA ${suffix}`)).toBeVisible();
    await expect(page.getByText(`FILTER BERAS ${suffix}`)).toHaveCount(0);
    await expect(page.getByText(`FILTER SABUN ${suffix}`)).toHaveCount(0);
    // Panel filter tetap terbuka karena ada filter yang dipakai.
    await expect(page.getByLabel('Satuan')).toHaveValue('kg');

    await page.getByRole('link', { name: 'Reset' }).click();
    await page.getByPlaceholder('Cari nama atau kode').fill(String(suffix));
    await page.getByLabel('Urutkan').selectOption('price_desc');
    await page.getByRole('button', { name: 'Cari' }).click();

    const names = await page.locator('.list-group-item .fw-semibold').allTextContents();
    expect(names).toEqual([`FILTER GULA ${suffix}`, `FILTER BERAS ${suffix}`, `FILTER SABUN ${suffix}`]);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});

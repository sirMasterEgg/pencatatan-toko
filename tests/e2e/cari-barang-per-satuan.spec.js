import { test, expect } from '@playwright/test';

async function newProduct(page, name, unit, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption(unit);
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

test('form transaksi: hasil cari barang dikelompokkan per satuan lalu urut nama', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Grup Pasir ${suffix}`, 'kg', '18000');
    await newProduct(page, `Grup Merah ${suffix}`, 'kg', '20000');
    await newProduct(page, `Grup Sachet ${suffix}`, 'pcs', '1000');

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(String(suffix));

    const list = page.locator('.list-group').first();
    await expect(list.getByRole('button', { name: new RegExp(`GRUP SACHET ${suffix}`) })).toBeVisible();
    const lines = (await list.locator('.list-group-item').allInnerTexts()).map((t) => t.split('\n')[0].trim());
    expect(lines).toEqual(['pcs', `GRUP SACHET ${suffix}`, 'kg', `GRUP MERAH ${suffix}`, `GRUP PASIR ${suffix}`]);

    await list.getByRole('button', { name: new RegExp(`GRUP MERAH ${suffix}`) }).click();
    await expect(page.getByLabel('Harga satuan')).toHaveValue('20.000');
});

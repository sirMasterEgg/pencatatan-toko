import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function newProduct(page, name, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('pcs');
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

test('harga massal: pilih barang, pratinjau, simpan', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Massal A ${suffix}`, '10000');
    await newProduct(page, `Massal B ${suffix}`, '2500');

    await page.goto('/products');
    await page.getByRole('link', { name: 'Ubah harga massal' }).click();
    await page.waitForURL(/\/products\/bulk-price$/);

    await page.getByPlaceholder('Cari nama atau kode').fill(String(suffix));
    await page.getByRole('button', { name: 'Cari' }).click();
    await page.getByLabel(/Pilih semua \(2\)/).check();
    await expect(page.locator("input[name='product_ids[]']:checked")).toHaveCount(2);

    await page.getByLabel('Perubahan harga jual').pressSequentially('1500');
    await expect(page.getByLabel('Perubahan harga jual')).toHaveValue('1.500');
    await noOverflow(page);
    await page.getByRole('button', { name: 'Lihat pratinjau' }).click();

    await expect(page.getByRole('heading', { name: 'Pratinjau perubahan harga' })).toBeVisible();
    await expect(page.getByText('Harga jual naik Rp1.500.')).toBeVisible();
    await expect(page.getByText('Rp11.500')).toBeVisible();
    await expect(page.getByText('Rp4.000')).toBeVisible();
    await noOverflow(page);

    await page.getByRole('button', { name: 'Simpan perubahan harga (2 barang)' }).click();
    await page.waitForURL(/\/products$/);
    await expect(page.getByText('Harga 2 barang berhasil diubah.')).toBeVisible();

    await page.goto(`/products?q=${suffix}`);
    await expect(page.getByText('Rp11.500')).toBeVisible();
    await expect(page.getByText('Rp4.000')).toBeVisible();
});

test('harga massal: penurunan di bawah Rp0 ditolak', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Murah ${suffix}`, '300');

    await page.goto(`/products/bulk-price?q=${suffix}`);
    await page.getByLabel(new RegExp(`Murah ${suffix}`, 'i')).check();
    await page.getByLabel('Arah perubahan').selectOption('down');
    await page.getByLabel('Perubahan harga jual').fill('500');
    await page.getByRole('button', { name: 'Lihat pratinjau' }).click();

    await expect(page.getByText(new RegExp(`Harga jual MURAH ${suffix} akan menjadi di bawah Rp0\\.`))).toBeVisible();
    await expect(page.getByLabel(new RegExp(`Murah ${suffix}`, 'i'))).toBeChecked();
});

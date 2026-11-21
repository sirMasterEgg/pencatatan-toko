import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function newProduct(page, name, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

async function newCustomer(page, name) {
    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/customers$/);
    await page.goto(`/customers?q=${encodeURIComponent(name)}`);
    await page.getByRole('link', { name }).click();
    await page.waitForURL(/\/customers\/\d+$/);
}

test('harga pelanggan: atur dan hentikan harga khusus di halaman pelanggan', async ({ page }) => {
    const suffix = Date.now();
    const product = `Gula Pelanggan ${suffix}`;
    await newProduct(page, product, '18000');
    await newCustomer(page, `Bu Harga ${suffix}`);

    await expect(page.getByText('Belum ada harga khusus.')).toBeVisible();
    await page.getByLabel('Barang').selectOption({ label: `${product.toUpperCase()} (kg) - Rp18.000` });
    await page.getByLabel('Harga pelanggan').pressSequentially('17000');
    await expect(page.getByLabel('Harga pelanggan')).toHaveValue('17.000');
    await page.getByRole('button', { name: 'Simpan harga khusus' }).click();

    await expect(page.getByText('Harga khusus berhasil disimpan.')).toBeVisible();
    await expect(page.getByText('Harga umum: Rp18.000')).toBeVisible();
    await expect(page.getByText('Riwayat harga khusus')).toBeVisible();
    await noOverflow(page);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Hentikan' }).click();
    await expect(page.getByText('Harga khusus dihentikan.')).toBeVisible();
    await expect(page.getByText('Belum ada harga khusus.')).toBeVisible();
    await expect(page.getByRole('cell', { name: 'Dihentikan', exact: true })).toBeVisible();
});

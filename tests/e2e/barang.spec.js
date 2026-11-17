import { test, expect } from '@playwright/test';

test('barang: tambah, lihat detail, tambah harga, ubah nama', async ({ page }) => {
    const name = `Gula E2E ${Date.now()}`;

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('15000');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();

    // Tugas 11: setelah simpan kembali ke daftar barang.
    await page.waitForURL(/\/products$/);
    await expect(page.getByText('Barang berhasil ditambahkan.')).toBeVisible();

    await page.goto(`/products?q=${encodeURIComponent(name)}`);
    await page.getByRole('link', { name }).click();
    await expect(page.getByRole('heading', { name, level: 1 })).toBeVisible();
    await expect(page.getByText('Rp15.000').first()).toBeVisible();

    await page.getByLabel('Harga jual').fill('17500');
    await page.getByRole('button', { name: 'Simpan harga' }).click();
    await expect(page.getByText('Harga baru berhasil disimpan.')).toBeVisible();
    await expect(page.getByText('Rp17.500').first()).toBeVisible();

    await page.getByRole('link', { name: 'Ubah' }).click();
    await page.getByLabel('Nama').fill(`${name} Baru`);
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Barang berhasil diperbarui.')).toBeVisible();

    await page.goto('/products');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});

test('barang: simpan dan tambah lagi membuka form kosong', async ({ page }) => {
    const name = `Kopi E2E ${Date.now()}`;

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('pcs');
    await page.getByLabel('Harga jual').fill('5000');
    await page.getByRole('button', { name: 'Simpan dan tambah lagi' }).click();

    await page.waitForURL(/\/products\/create$/);
    await expect(page.getByText('Barang berhasil ditambahkan.')).toBeVisible();
    await expect(page.getByLabel('Nama')).toHaveValue('');
    await expect(page.getByLabel('Harga jual')).toHaveValue('');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.goto(`/products?q=${encodeURIComponent(name)}`);
    await expect(page.getByRole('link', { name })).toBeVisible();
});

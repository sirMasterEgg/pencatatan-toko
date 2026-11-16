import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test('barang: tambah, lihat detail, tambah harga, ubah nama', async ({ page }) => {
    const name = `Gula E2E ${Date.now()}`;
    await login(page);

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('15000');
    await page.getByRole('button', { name: 'Simpan' }).click();

    await expect(page.getByText('Barang berhasil ditambahkan.')).toBeVisible();
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

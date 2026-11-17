import { test, expect } from '@playwright/test';

test('pelanggan: tambah, cari, ubah, hapus', async ({ page }) => {
    const name = `Bu Sari ${Date.now()}`;

    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Nomor HP').fill('0812-0000-' + String(Date.now()).slice(-4));
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();

    // Tugas 11: setelah simpan kembali ke daftar pelanggan.
    await page.waitForURL(/\/customers$/);
    await expect(page.getByText('Pelanggan berhasil ditambahkan.')).toBeVisible();

    await page.goto('/customers');
    await page.getByPlaceholder('Cari nama atau nomor HP').fill(name);
    await page.getByPlaceholder('Cari nama atau nomor HP').press('Enter');
    await expect(page.getByRole('link', { name })).toBeVisible();
    await page.getByRole('link', { name }).click();

    await page.getByRole('link', { name: 'Ubah' }).click();
    await page.getByLabel('Alamat').fill('Jl. Mawar 1');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Pelanggan berhasil diperbarui.')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Hapus' }).click();
    await expect(page.getByText('Pelanggan berhasil dihapus.')).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});

test('pelanggan: simpan dan tambah lagi membuka form kosong', async ({ page }) => {
    const name = `Pak Budi ${Date.now()}`;

    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByRole('button', { name: 'Simpan dan tambah lagi' }).click();

    await page.waitForURL(/\/customers\/create$/);
    await expect(page.getByText('Pelanggan berhasil ditambahkan.')).toBeVisible();
    await expect(page.getByLabel('Nama')).toHaveValue('');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.goto(`/customers?q=${encodeURIComponent(name)}`);
    await expect(page.getByRole('link', { name })).toBeVisible();
});

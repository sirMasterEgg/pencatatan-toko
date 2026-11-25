import { test, expect } from '@playwright/test';

test('bayar sebagian: isi nominal dibayar, lihat kurangnya, lalu perbarui di detail', async ({ page }) => {
    const name = `Sebagian ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').fill('20000');
    await page.getByLabel('Jumlah').fill('5');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp100.000').first()).toBeVisible();

    await expect(page.getByLabel('Nominal dibayar')).toHaveCount(0);
    await page.getByLabel('Status bayar').selectOption('partial');
    await page.getByLabel('Nominal dibayar').pressSequentially('40000');
    await expect(page.getByLabel('Nominal dibayar')).toHaveValue('40.000');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Kurang: Rp60.000')).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('Rp40.000').first()).toBeVisible();
    await expect(page.getByText('Rp60.000').first()).toBeVisible();

    // Pembayaran berikutnya: isi total yang sudah dibayar sampai sekarang.
    await page.getByLabel('Nominal dibayar').fill('70000');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();
    await expect(page.getByText('Rp30.000').first()).toBeVisible();

    // Lunas: baris "Kurang" hilang.
    await page.getByLabel('Status bayar', { exact: true }).selectOption('paid');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();
    await expect(page.locator('dt', { hasText: 'Kurang' })).toHaveCount(0);
});

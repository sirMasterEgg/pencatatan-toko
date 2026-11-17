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

    // Tugas 08: detail, status bayar, daftar/filter, batal.
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByText(name).first()).toBeVisible();

    await page.getByLabel('Status bayar', { exact: true }).selectOption('unpaid');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();

    await page.goto('/transactions?payment_status=unpaid');
    await expect(page.getByRole('link', { name: /TRX-\d{8}-\d{3}/ }).first()).toBeVisible();
    const overflowList = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflowList).toBe(false);

    await page.getByRole('link', { name: /TRX-\d{8}-\d{3}/ }).first().click();
    page.once('dialog', (d) => d.accept());
    await page.getByPlaceholder('Alasan pembatalan (opsional)').fill('Uji e2e');
    await page.getByRole('button', { name: 'Batalkan transaksi' }).click();
    await expect(page.getByText('Transaksi dibatalkan.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan transaksi' })).toHaveCount(0);
});

test('form transaksi: jumlah desimal dengan koma untuk satuan kg', async ({ page }) => {
    const name = `Beras ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('12000');
    await page.getByLabel('Jumlah').fill('1,5');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp18.000').first()).toBeVisible();

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('1,5 kg')).toBeVisible();
});

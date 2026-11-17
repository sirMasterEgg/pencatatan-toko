import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function newDraft(page, name) {
    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('10000');
    await page.getByLabel('Jumlah').fill('2');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp20.000').first()).toBeVisible();
    await noOverflow(page);

    await page.getByRole('button', { name: 'Simpan draf' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(/Draf TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByText('Ini draf.')).toBeVisible();
    return page.url();
}

test('draf: simpan, ubah, lalu jadikan transaksi; kunci setelah dibayar', async ({ page }) => {
    const draftUrl = await newDraft(page, `Draf ${Date.now()}`);

    await page.goto('/transactions?status=draft');
    await expect(page.locator('.badge', { hasText: 'Draf' }).first()).toBeVisible();

    await page.goto(draftUrl);
    await page.getByRole('link', { name: 'Ubah transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+\/edit$/);
    await expect(page.getByRole('heading', { name: /Ubah draf TRX-/ })).toBeVisible();
    await expect(page.getByLabel('Jumlah')).toHaveValue('2');
    await expect(page.getByLabel('Harga satuan')).toHaveValue('10.000');
    await noOverflow(page);

    await page.getByLabel('Jumlah').fill('2,5');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp25.000').first()).toBeVisible();
    await page.getByLabel('Status bayar').selectOption('unpaid');
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByText('Ini draf.')).toHaveCount(0);
    await expect(page.getByText('2,5 kg')).toBeVisible();

    // Dikonfirmasi tetapi belum bayar: masih bisa diubah, tanpa tombol draf.
    await page.getByRole('link', { name: 'Ubah transaksi' }).click();
    await expect(page.getByRole('button', { name: 'Simpan draf' })).toHaveCount(0);
    await page.getByLabel('Jumlah').fill('3');
    await page.getByLabel('Diskon').click();
    await page.getByRole('button', { name: 'Simpan perubahan' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil diperbarui\./)).toBeVisible();
    await expect(page.getByText('Rp30.000').first()).toBeVisible();

    // Setelah dibayar: terkunci.
    await page.getByLabel('Status bayar', { exact: true }).selectOption('paid');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ubah transaksi' })).toHaveCount(0);

    await page.goto(page.url() + '/edit');
    await expect(page.getByText('Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.')).toBeVisible();
});

test('draf: hapus draf', async ({ page }) => {
    await newDraft(page, `Hapus ${Date.now()}`);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Hapus draf' }).click();
    await page.waitForURL(/\/transactions$/);
    await expect(page.getByText(/Draf TRX-\d{8}-\d{3} berhasil dihapus\./)).toBeVisible();
});

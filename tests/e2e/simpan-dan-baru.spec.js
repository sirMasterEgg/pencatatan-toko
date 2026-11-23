import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function fillNewItem(page, name, price) {
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').fill(price);
    await page.getByLabel('Diskon').click();
}

test('simpan & transaksi baru: menyimpan lalu membuka form kosong', async ({ page }) => {
    const suffix = Date.now();

    await page.goto('/transactions/create');
    await fillNewItem(page, `Sabun ${suffix}`, '4000');
    await expect(page.getByText('Rp4.000').first()).toBeVisible();
    await noOverflow(page);

    await page.getByRole('button', { name: 'Simpan & transaksi baru' }).click();

    // Alamatnya tetap /transactions/create, jadi tunggu pesan sukses, bukan perubahan URL.
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByPlaceholder('Ketik nama barang')).toHaveValue('');
    const lastLink = page.getByRole('link', { name: /^Lihat TRX-\d{8}-\d{3}$/ });
    const firstNumber = await lastLink.textContent();

    // Transaksi kedua langsung dari form yang sama.
    await fillNewItem(page, `Sikat ${suffix}`, '7000');
    await page.getByRole('button', { name: 'Simpan & transaksi baru' }).click();
    await expect(lastLink).not.toHaveText(firstNumber);
    await expect(page.getByPlaceholder('Ketik nama barang')).toHaveValue('');

    // Tautan ke transaksi terakhir membuka transaksi kedua.
    await lastLink.click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(`SIKAT ${suffix}`)).toBeVisible();
    await expect(page.getByText('Rp7.000').first()).toBeVisible();
});

test('simpan & transaksi baru: menjadikan draf transaksi lalu membuka form kosong', async ({ page }) => {
    const suffix = Date.now();

    await page.goto('/transactions/create');
    await fillNewItem(page, `Draf Baru ${suffix}`, '5000');
    await page.getByRole('button', { name: 'Simpan draf' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    const draftUrl = page.url();

    await page.getByRole('link', { name: 'Ubah transaksi' }).click();
    await page.getByRole('button', { name: 'Simpan & transaksi baru' }).click();
    await page.waitForURL(/\/transactions\/create$/);
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();

    await page.goto(draftUrl);
    await expect(page.getByText('Ini draf.')).toHaveCount(0);
});

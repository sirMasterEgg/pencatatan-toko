import { test, expect } from '@playwright/test';

// Mengisi baris barang baru ke-`row` (mulai 0). Menunggu sampai baris itu benar-benar tampil
// sebelum mengisi harga, agar tidak mengenai baris lain.
async function newItemRow(page, row, name, price) {
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await expect(page.getByLabel('Harga jual')).toHaveCount(row + 1);
    await page.getByLabel('Harga jual').nth(row).fill(price);
}

test('jumlah dan diskon: kosong saat difokus pertama kali, kembali ke bawaan jika ditinggal kosong', async ({ page }) => {
    const suffix = Date.now();

    await page.goto('/transactions/create');
    await newItemRow(page, 0, `Roti ${suffix}`, '5000');

    const qty = page.getByLabel('Jumlah').nth(0);
    const disc = page.getByLabel('Diskon').nth(0);
    await expect(qty).toHaveValue('1');
    await expect(disc).toHaveValue('0');

    // Fokus pertama: kosong, langsung ketik tanpa menghapus.
    await qty.click();
    await expect(qty).toHaveValue('');
    await page.keyboard.type('3');
    await disc.click();
    await expect(disc).toHaveValue('');
    await page.keyboard.type('1000');
    await expect(disc).toHaveValue('1.000');
    await qty.click();
    await expect(page.getByText('Subtotal: Rp14.000')).toBeVisible();

    // Fokus berikutnya tidak mengosongkan lagi.
    await expect(qty).toHaveValue('3');
    await disc.click();
    await expect(disc).toHaveValue('1.000');

    // Baris kedua: difokus lalu ditinggal kosong, kembali ke 1 dan 0.
    await page.getByRole('button', { name: '+ Tambah barang' }).click();
    await newItemRow(page, 1, `Susu ${suffix}`, '7000');
    const qty2 = page.getByLabel('Jumlah').nth(1);
    const disc2 = page.getByLabel('Diskon').nth(1);
    await qty2.click();
    await expect(qty2).toHaveValue('');
    await disc2.click();
    await expect(qty2).toHaveValue('1');
    await expect(disc2).toHaveValue('');
    await qty2.click();
    await expect(disc2).toHaveValue('0');
    await expect(page.getByText('Subtotal: Rp7.000')).toBeVisible();

    // Setelah form digambar ulang, kolom yang sudah pernah difokus tidak dikosongkan lagi.
    await page.getByRole('button', { name: '+ Tambah barang' }).click();
    await expect(page.getByPlaceholder('Ketik nama barang')).toHaveCount(1);
    await qty2.click();
    await expect(qty2).toHaveValue('1');

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('Rp21.000').first()).toBeVisible();
});

test('jumlah: ketik harga lalu langsung ketuk Jumlah dan ketik', async ({ page }) => {
    const name = `Cepat ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').click();
    await page.keyboard.type('5000');

    // Kursor pindah dari Harga jual ke Jumlah memicu pembaruan Livewire.
    // Angka yang diketik tidak boleh tergabung dengan 1 bawaan (misalnya menjadi 13).
    await page.getByLabel('Jumlah').click();
    await page.keyboard.type('3');
    await page.getByLabel('Diskon').click();
    await expect(page.getByLabel('Jumlah')).toHaveValue('3');
    await expect(page.getByText('Subtotal: Rp15.000')).toBeVisible();
});

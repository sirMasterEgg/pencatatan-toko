import { test, expect } from '@playwright/test';

test.use({ timezoneId: 'Asia/Jakarta' });

const asInputValue = (date) => {
    const pad = (n) => String(n).padStart(2, '0');
    const local = new Date(date.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));

    return `${local.getFullYear()}-${pad(local.getMonth() + 1)}-${pad(local.getDate())}T${pad(local.getHours())}:${pad(local.getMinutes())}`;
};

test('tanggal transaksi berjalan sendiri sampai diubah manual', async ({ page }) => {
    const start = new Date();
    start.setSeconds(0, 0);
    await page.clock.setFixedTime(start);

    await page.goto('/transactions/create');
    const field = page.getByLabel('Tanggal');
    await expect(page.getByText('Mengikuti waktu sekarang.')).toBeVisible();

    // Jam perangkat maju 5 menit: kolom ikut berjalan.
    const later = new Date(start.getTime() + 5 * 60 * 1000);
    await page.clock.setFixedTime(later);
    await expect(field).toHaveValue(asInputValue(later));

    // Diubah manual: berhenti berjalan dan muncul tombol "Pakai waktu sekarang".
    const manual = new Date(start.getTime() - 26 * 60 * 60 * 1000);
    await field.fill(asInputValue(manual));
    await expect(page.getByRole('button', { name: 'Pakai waktu sekarang' })).toBeVisible();
    await page.clock.setFixedTime(new Date(start.getTime() + 10 * 60 * 1000));
    await page.waitForTimeout(1500);
    await expect(field).toHaveValue(asInputValue(manual));

    // Kembali ke waktu sekarang: berjalan lagi.
    await page.getByRole('button', { name: 'Pakai waktu sekarang' }).click();
    await expect(page.getByText('Mengikuti waktu sekarang.')).toBeVisible();
    const evenLater = new Date(start.getTime() + 15 * 60 * 1000);
    await page.clock.setFixedTime(evenLater);
    await expect(field).toHaveValue(asInputValue(evenLater));
});

test('tanggal manual tersimpan sesuai isian', async ({ page }) => {
    const name = `Jam ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').fill('1000');

    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
    yesterday.setMinutes(5);
    await page.getByLabel('Tanggal').fill(asInputValue(yesterday));
    await expect(page.getByRole('button', { name: 'Pakai waktu sekarang' })).toBeVisible();

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);

    const [date, time] = asInputValue(yesterday).split('T');
    const [y, m, d] = date.split('-');
    await expect(page.getByText(`${d}/${m}/${y} ${time}`)).toBeVisible();
});

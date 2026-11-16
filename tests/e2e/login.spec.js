import { test, expect } from '@playwright/test';
import { login, owner } from './helpers.js';

test.describe('Tugas 03: login', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('tamu dialihkan ke halaman masuk', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: 'Masuk' })).toBeVisible();
    });

    test('kata sandi salah menampilkan galat', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill(owner.email);
        await page.getByLabel('Kata sandi').fill('salah-sekali');
        await page.getByRole('button', { name: 'Masuk' }).click();

        await expect(page.getByText('Email atau kata sandi salah.')).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('login berhasil menampilkan menu, lalu keluar berfungsi', async ({ page }) => {
        await login(page);
        await expect(page.getByRole('heading', { name: 'Beranda', level: 1 })).toBeVisible();

        await page.getByRole('button', { name: 'Buka menu' }).click();
        await expect(page.getByRole('link', { name: 'Transaksi' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Barang' })).toBeVisible();

        await page.getByRole('button', { name: 'Keluar' }).click();
        await expect(page).toHaveURL(/\/login$/);
    });
});

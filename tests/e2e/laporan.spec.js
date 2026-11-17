import { test, expect } from '@playwright/test';

test('laporan harian: berpindah tanggal dan tampil rapi di HP', async ({ page }) => {
    await page.goto('/reports/daily');
    await expect(page.getByRole('heading', { name: 'Laporan harian' })).toBeVisible();
    await expect(page.getByText('Omzet', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Berikutnya ›' })).toHaveCount(0);

    await page.getByRole('link', { name: '‹ Sebelumnya' }).click();
    await expect(page).toHaveURL(/date=\d{4}-\d{2}-\d{2}/);
    await expect(page.getByRole('link', { name: 'Berikutnya ›' })).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.goto('/');
    await expect(page.getByText(/Hari ini, \d{2}\/\d{2}\/\d{4}/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Lihat laporan lengkap' })).toBeVisible();
});

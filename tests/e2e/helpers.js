export const owner = { email: 'pemilik@toko.test', password: 'rahasia-e2e-123' };

export async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(owner.email);
    await page.getByLabel('Kata sandi').fill(owner.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL('/');
}

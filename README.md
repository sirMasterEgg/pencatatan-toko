# Catatan Toko

Aplikasi pencatatan penjualan untuk satu toko kecil, dipakai pemilik lewat HP. Pemilik langsung mencatat transaksi, dan barang serta harga yang belum ada dibuat di layar transaksi yang sama.

## Kebutuhan

- PHP 8.3 atau lebih baru
- Composer
- Node.js 22 atau lebih baru

## Cara memasang

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Isi `OWNER_EMAIL` dan `OWNER_PASSWORD` di file `.env`, lalu:

```bash
touch database/database.sqlite
php artisan migrate --seed
npm run build
```

## Cara menjalankan

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000` dan masuk dengan email dan kata sandi pemilik dari `.env`.

## Cara menjalankan tes

```bash
php artisan test            # tes PHPUnit (unit dan feature)
npx playwright install chromium
npm run test:e2e            # tes e2e Playwright (butuh aset sudah dibangun)
```

## Fitur tahap 1

- Login satu pengguna (pemilik toko)
- Barang dan riwayat harga
- Pelanggan
- Transaksi penjualan beserta itemnya, pembatalan, dan perubahan status bayar
- Beranda dan laporan harian

## Belum ada

- Stok, pembelian, stok opname
- Unggah foto nota, pembacaan chat WhatsApp, antrean review
- Gabung barang
- Ekspor CSV, cetak struk
- Pendaftaran pengguna, lupa kata sandi, peran dan hak akses
- Mode offline, PWA
- API JSON

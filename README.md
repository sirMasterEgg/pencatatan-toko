# Catatan Toko

Aplikasi pencatatan penjualan untuk satu toko kecil, dipakai pemilik lewat HP. Prinsipnya transaction-first: pemilik langsung mencatat transaksi, dan barang serta harga yang belum ada dibuat di layar transaksi yang sama.

Status: **tahap 1 selesai** (Tugas 01 sampai 21 di `docs/instruksi/`).

## Tech stack

- PHP 8.3 atau lebih baru, Laravel 13
- Blade dan Bootstrap 5.3 (lewat npm dan Vite, tanpa Sass dan tanpa CDN)
- Livewire 4 gaya class-based, hanya untuk form transaksi
- SQLite (bisa diganti MySQL lewat `.env`)
- PHPUnit untuk tes unit dan feature, Playwright untuk tes e2e

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

Isi `OWNER_EMAIL` dan `OWNER_PASSWORD` di file `.env` (kata sandi tidak boleh kosong, kalau kosong seeder akan berhenti dengan galat). Lalu:

```bash
touch database/database.sqlite
php artisan migrate --seed
npm run build
```

Seeder membuat satu akun pemilik dari `OWNER_NAME`, `OWNER_EMAIL`, dan `OWNER_PASSWORD`. Tidak ada pendaftaran pengguna.

## Cara menjalankan

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000` dan masuk dengan email dan kata sandi pemilik. Untuk pengembangan aset, jalankan `npm run dev`.

## Cara menjalankan tes

```bash
php artisan test              # PHPUnit: tes unit dan feature
```

Tes e2e Playwright menjalankan server sendiri di port 8001 dengan database terpisah (`database/e2e.sqlite`, tidak masuk git) dan akun uji sendiri, jadi tidak menyentuh data lokal Anda. Aset harus sudah dibangun:

```bash
npm run build
npx playwright install chromium    # sekali saja
npm run test:e2e
```

Tes e2e berjalan dengan satu worker dan emulasi layar HP, karena semua tes berbagi satu sesi login. Jika Chromium sudah terpasang di tempat lain, atur `PLAYWRIGHT_CHROMIUM_PATH` ke berkas peramban itu.

## Fitur tahap 1

- **Login** satu pengguna (pemilik toko), dengan pembatasan percobaan masuk
- **Barang dan riwayat harga**: tambah, ubah, cari, tandai sudah diperiksa. Harga adalah riwayat: perubahan harga menambah baris baru, baris lama tidak diubah. Barang tidak bisa dihapus, hanya dinonaktifkan
- **Pelanggan**: tambah, ubah, cari, hapus (jika belum punya transaksi). Nomor HP dinormalkan ke format `62...` dan tidak boleh ganda
- **Tambah beruntun**: form tambah barang dan tambah pelanggan punya tombol "Simpan" (kembali ke daftar) dan "Simpan dan tambah lagi" (membuka form kosong lagi)
- **Jumlah desimal**: kg, gram, liter, dan meter boleh desimal (contoh `0,5`). Koma dan titik sama-sama tanda desimal. Satuan lain wajib bilangan bulat
- **Titik ribuan**: kolom harga, harga modal, dan diskon langsung diberi titik saat diketik (`15000` menjadi `15.000`)
- **Nama barang huruf besar**: nama barang selalu disimpan dalam huruf besar
- **Draf dan ubah transaksi**: transaksi bisa disimpan sebagai draf (tidak masuk laporan, bisa dihapus). Transaksi yang belum dibayar bisa diubah. Setelah dibayar sebagian atau lunas, isinya terkunci
- **Harga pelanggan**: harga tetap per pelanggan per barang, diatur di halaman pelanggan atau dari form transaksi ("Jadikan harga pelanggan"). Urutan harga di nota: harga khusus nota, lalu harga pelanggan, lalu harga umum
- **Ubah harga massal**: pilih banyak barang, naikkan atau turunkan harganya sekian rupiah, lihat pratinjau, lalu simpan. Bisa dijadwalkan dan bisa ikut mengubah harga pelanggan
- **Simpan & transaksi baru**: menyimpan transaksi lalu langsung membuka form kosong untuk pembeli berikutnya, dengan tautan ke transaksi yang baru disimpan
- **Jumlah dan diskon cepat diisi**: di form transaksi, kolom Jumlah (1) dan Diskon (0) langsung kosong saat pertama kali disentuh, dan kembali ke nilai bawaan jika ditinggal kosong
- **Transaksi baru**: form Livewire untuk mencari barang, membuat barang baru beserta harganya, memilih atau membuat pelanggan, lalu menyimpan semuanya sekaligus dalam satu transaksi database. Barang dari form berstatus "Perlu dirapikan"
- **Daftar dan detail transaksi**: filter tanggal, status, status bayar, dan pencarian. Transaksi bisa dibatalkan dan status bayarnya bisa diubah. Isi transaksi yang sudah disimpan tidak bisa diubah
- **Beranda dan laporan harian**: omzet, jumlah transaksi, rata-rata nota, piutang, rekap per saluran, dan barang terlaris. Hanya transaksi yang dikonfirmasi yang dihitung

## Belum ada

- Stok, pembelian, stok opname
- Unggah foto nota, pembacaan chat WhatsApp, antrean review
- Gabung barang
- Ekspor CSV, cetak struk
- Pendaftaran pengguna, lupa kata sandi, peran dan hak akses
- Mode offline, PWA
- API JSON
- Hapus transaksi yang sudah dikonfirmasi, grafik

## Struktur proyek

```
app/Enums/             ProductStatus, TransactionStatus, PaymentStatus, Channel
app/Http/Controllers/  satu controller per bagian (Auth/LoginController, Product, Customer, Transaction, laporan, beranda)
app/Livewire/          TransactionForm
app/Models/            Product, ProductPrice, Customer, Transaction, TransactionItem, User
app/Services/          ProductService, CustomerService, TransactionService, DailyReportService
app/Support/           Format (uang dan jumlah), Phone (normalisasi nomor HP)
config/shop.php        daftar satuan dan data akun pemilik
resources/views/       layouts, auth, products, customers, transactions, reports, livewire
tests/Feature/         tes PHPUnit per bagian, termasuk FullFlowTest
tests/Unit/            tes Format dan Phone
tests/e2e/             tes Playwright
docs/instruksi/        file tugas dan aturan proyek
```

Penulisan data transaksi hanya lewat `TransactionService`, dan pembuatan barang serta harga hanya lewat `ProductService`.

## Rute

Semua rute selain `/login` wajib login.

| Bagian | Rute |
|---|---|
| Login | `GET/POST /login`, `POST /logout` |
| Beranda | `GET /` |
| Barang | `/products` (daftar, tambah, detail, ubah), `POST /products/{id}/prices`, `POST /products/{id}/mark-reviewed`, `GET /products/bulk-price`, `POST /products/bulk-price/preview`, `POST /products/bulk-price` |
| Pelanggan | `/customers` (resource lengkap), `POST /customers/{id}/prices`, `POST /customers/{id}/prices/{product}/stop` |
| Transaksi | `GET /transactions`, `GET /transactions/create`, `GET /transactions/{id}`, `GET /transactions/{id}/edit`, `DELETE /transactions/{id}` (hanya draf), `POST /transactions/{id}/cancel`, `POST /transactions/{id}/payment-status` |
| Laporan | `GET /reports/daily` |

Daftar lengkap ada di `php artisan route:list`.

## Konvensi

- Kode berbahasa Inggris, semua teks di layar berbahasa Indonesia
- Uang disimpan sebagai bilangan bulat rupiah dan ditampilkan lewat `App\Support\Format::rupiah()`
- Zona waktu `Asia/Jakarta`, format tanggal di layar `d/m/Y H:i`
- Tampilan dirancang untuk layar HP lebih dulu (lebar 375px)

Aturan lengkap dan kamus istilah ada di `docs/instruksi/00-aturan-umum.md` dan `docs/instruksi/CLAUDE.md`.

## Catatan

- Belum ada pipeline CI untuk tes. Tes dijalankan secara lokal.
- Workflow di `.github/workflows/` adalah bawaan scaffold Laravel dan tidak dipakai untuk menguji proyek ini.

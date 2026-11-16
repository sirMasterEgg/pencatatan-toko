# CLAUDE.md

Aplikasi pencatatan penjualan untuk satu toko kecil, dipakai pemilik lewat HP. Prinsipnya transaction-first: pengguna langsung mencatat transaksi, dan barang serta harga yang belum ada dibuat di layar transaksi yang sama.

## Cara kerja di proyek ini

- Pekerjaan dibagi menjadi file tugas di `docs/instruksi/`, bernomor `01` sampai `10`. Kerjakan satu file tugas dalam satu waktu, sesuai urutan.
- Sebelum mengerjakan tugas, baca `docs/instruksi/00-aturan-umum.md`. File itu memuat kamus istilah lengkap dan daftar semua rute.
- Kerjakan persis yang tertulis di file tugas. Jangan menambah fitur, paket, tabel, atau kolom.
- Jika instruksi tidak bisa dijalankan atau saling bertentangan, berhenti dan laporkan. Jangan mengarang jalan keluar.
- Setelah selesai, jalankan semua perintah di bagian "Kriteria selesai" pada file tugas, lalu tulis laporan sesuai format di bawah.
- Jika tes gagal, perbaiki kodenya. Jangan menghapus atau melemahkan tes.

## Perintah

```bash
php artisan test                      # jalankan semua tes
php artisan test --filter=NamaTes     # jalankan satu tes
npm run build                         # bangun aset
npm run dev                           # mode pengembangan aset
php artisan serve                     # jalankan aplikasi
php artisan migrate:fresh --seed      # ulang database (OWNER_PASSWORD di .env harus terisi)
php artisan route:list                # daftar rute
php artisan make:livewire Nama --class  # SELALU pakai --class
```

## Tech stack

- PHP 8.3 atau lebih baru, Laravel 13.
- Blade dan Bootstrap 5.3 lewat npm dan Vite. Tanpa Sass, tanpa CDN.
- Livewire 4 gaya class-based, hanya untuk form transaksi.
- SQLite. Kode harus tetap jalan di MySQL.
- PHPUnit.
- Paket yang boleh dipasang hanya `livewire/livewire`, `bootstrap`, `@popperjs/core`.

## Jebakan versi

Laravel 13:

- Tidak ada `app/Http/Kernel.php`. Middleware diatur di `bootstrap/app.php`.
- Model memakai atribut `#[Fillable([...])]` dan method `casts()`. Tiru `app/Models/User.php`.
- Proyek baru membawa Tailwind. Proyek ini sudah menggantinya dengan Bootstrap. Jangan memakai kelas Tailwind.

Livewire 4:

- `make:livewire` tanpa `--class` membuat komponen satu file berawalan emoji petir. Jangan pakai format itu.
- Komponen ditanam di view Blade biasa dengan `<livewire:nama-komponen />`. Jangan memakai `Route::livewire()` dan jangan menjalankan `livewire:layout`.
- `wire:model` tidak mengirim ke server sampai ada aksi. Pakai `wire:model.live` untuk langsung, dan `wire:model.live.blur` untuk saat kursor keluar kolom.
- Setiap elemen di dalam `@foreach` wajib punya `wire:key`.
- Alpine sudah dibawa Livewire. Jangan memasangnya lagi.

## Konvensi kode

- Kode berbahasa Inggris: kelas, tabel, kolom, variabel, rute.
- Semua teks di layar berbahasa Indonesia: label, tombol, pesan galat, pesan sukses.
- Uang disimpan sebagai bilangan bulat rupiah. Jangan memakai `float` atau `decimal` untuk uang.
- Tampilkan uang dengan `App\Support\Format::rupiah()` dan jumlah barang dengan `Format::quantity()`.
- Normalkan nomor HP dengan `App\Support\Phone::normalize()` sebelum disimpan atau dicari.
- Zona waktu `Asia/Jakarta`. Format tanggal di layar `d/m/Y H:i`.
- Nilai status memakai enum di `app/Enums`, masing-masing punya `label()`.
- Controller tipis: validasi, panggil service atau model, kembalikan view atau redirect.
- Semua view memakai `@extends('layouts.app')` dan `@section('content')`.
- Tampilan dirancang untuk layar HP lebih dulu (lebar 375px).
- Jangan menulis SQL khusus satu database. Jangan memakai `whereDate` atau `DATE()`.

## Aturan bisnis yang tidak boleh dilanggar

1. Setiap item transaksi terhubung ke satu barang. Tidak ada item teks bebas.
2. Harga adalah riwayat. Perubahan harga menambah baris di `product_prices`. Baris lama tidak pernah diubah atau dihapus.
3. Harga yang berlaku adalah baris dengan `effective_from` terbaru yang tidak melewati waktu sekarang (`Product::currentPrice`).
4. Item transaksi menyimpan salinan `unit_price`, `list_price`, dan `unit`.
5. Barang dari form transaksi berstatus `needs_review`. Barang dari halaman barang berstatus `active`.
6. Harga yang diubah di form transaksi bawaannya hanya untuk nota itu. Riwayat harga bertambah hanya jika pengguna memilih "Jadikan harga baru".
7. Simpan transaksi bersifat utuh, di dalam satu `DB::transaction`.
8. Transaksi `confirmed` tidak bisa diubah isinya. Hanya bisa dibatalkan atau diubah status bayarnya.
9. Laporan hanya menghitung transaksi `confirmed`.
10. Barang yang sudah dipakai di transaksi tidak bisa dihapus dan satuannya tidak bisa diubah.
11. Nomor HP pelanggan unik setelah dinormalkan.

## Batas tanggung jawab kelas

| Kelas | Satu-satunya tempat untuk |
|---|---|
| `App\Services\TransactionService` | membuat `Transaction` dan `TransactionItem`, membatalkan, mengubah status bayar, rumus subtotal |
| `App\Services\ProductService` | membuat `Product` dan `ProductPrice` |
| `App\Services\CustomerService` | mencari atau membuat `Customer` dari form transaksi |
| `App\Support\Format` | `number_format` |
| `App\Livewire\TransactionForm` | keadaan dan tampilan form transaksi. Tidak menyimpan data sendiri |

## Struktur folder

```
app/Enums/            ProductStatus, TransactionStatus, PaymentStatus, Channel
app/Http/Controllers/ satu controller per bagian
app/Livewire/         TransactionForm
app/Models/           Product, ProductPrice, Customer, Transaction, TransactionItem, User
app/Services/         ProductService, CustomerService, TransactionService, DailyReportService
app/Support/          Format, Phone
config/shop.php       daftar satuan dan data akun pemilik
resources/views/      layouts, auth, products, customers, transactions, reports, livewire
tests/Feature/        satu file tes per bagian
docs/instruksi/       file tugas
```

## Kamus singkat

| Indonesia | Kode |
|---|---|
| Barang | `Product` / `products` |
| Riwayat harga | `ProductPrice` / `product_prices` |
| Pelanggan | `Customer` / `customers` |
| Transaksi | `Transaction` / `transactions` |
| Item transaksi | `TransactionItem` / `transaction_items` |
| Harga saat itu | `unit_price` |
| Harga daftar | `list_price` |
| Perlu dirapikan | `needs_review` |

Kamus lengkap ada di `docs/instruksi/00-aturan-umum.md`.

## Di luar lingkup

Jangan membuat hal berikut walaupun terlihat berguna: stok dan pembelian, unggah foto nota, pembacaan chat WhatsApp, antrean review, gabung barang, ekspor, cetak struk, grafik, pendaftaran pengguna, lupa kata sandi, hak akses, mode offline, API JSON, ubah atau hapus transaksi.

## Larangan

- Jangan mengerjakan tugas berikutnya sebelum diminta.
- Jangan mengubah migrasi dari tugas sebelumnya, kecuali file tugas memintanya.
- Jangan memasang starter kit, Breeze, Jetstream, Sass, jQuery, Alpine, atau pustaka grafik.
- Jangan membuat komponen Livewire selain `TransactionForm`.
- Jangan menaruh logika simpan transaksi di controller atau komponen Livewire.
- Jangan menulis kata sandi atau rahasia di dalam kode.

## Format laporan selesai

```
Tugas: <nomor dan judul>
File dibuat: <daftar>
File diubah: <daftar>
Hasil php artisan test: <jumlah lulus dan gagal>
Hasil npm run build: <berhasil atau gagal>
Kriteria selesai: <tiap butir, terpenuhi atau tidak>
Hal yang tidak bisa dikerjakan atau menyimpang dari instruksi: <daftar, atau "tidak ada">
```

Jika ada langkah yang tidak benar-benar diperiksa, misalnya tampilan di browser, tulis apa adanya.

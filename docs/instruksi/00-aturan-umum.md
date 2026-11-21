# Aturan Umum

Baca file ini sampai habis sebelum mengerjakan file tugas mana pun. Aturan di sini berlaku untuk semua tugas.

## 1. Peran Anda

Anda adalah programmer Laravel. Anda mengerjakan satu file tugas dalam satu waktu. Kerjakan persis yang tertulis. Jangan menambah fitur, paket, tabel, atau kolom yang tidak diminta.

Jika instruksi tidak bisa dijalankan (perintah gagal, file tidak ada, atau dua instruksi bertentangan), berhenti dan laporkan masalahnya. Jangan mengarang jalan keluar sendiri.

## 2. Tentang aplikasi

Aplikasi pencatatan penjualan untuk satu toko kecil. Pemilik toko memindahkan catatan dari nota kertas dan chat WhatsApp ke aplikasi ini lewat HP.

Prinsip utamanya disebut transaction-first: pengguna langsung mencatat transaksi. Barang dan harga yang belum ada dibuat pada saat transaksi dicatat, di layar yang sama.

## 3. Lingkup

Yang dibuat:

- Login satu pengguna.
- Barang dan riwayat harganya.
- Pelanggan.
- Transaksi penjualan dan itemnya.
- Laporan harian.
- Draf transaksi, dan mengubah transaksi yang belum dibayar (sejak tugas 15 dan 16).
- Harga pelanggan, yaitu harga tetap per pelanggan per barang (sejak tugas 17 dan 18).
- Ubah harga massal dalam rupiah untuk banyak barang sekaligus (sejak tugas 19).

Yang TIDAK dibuat. Jangan membuatnya walaupun terlihat berguna:

- Stok, pembelian, stok opname.
- Unggah foto nota, pembacaan chat WhatsApp, antrean review.
- Gabung barang.
- Ekspor CSV, cetak struk.
- Pendaftaran pengguna, lupa kata sandi, peran dan hak akses.
- Mode offline, PWA.
- API JSON.
- Menghapus transaksi yang sudah dikonfirmasi atau dibatalkan. Yang boleh dihapus hanya draf.
- Harga pelanggan dalam bentuk potongan atau persen, dan perubahan harga massal dalam persen.

## 4. Tech stack

| Bagian | Pilihan |
|---|---|
| PHP | 8.3 atau lebih baru |
| Framework | Laravel 13 |
| Tampilan | Blade |
| UI kit | Bootstrap 5.3, dipasang lewat npm dan Vite |
| Interaksi form | Livewire 4, gaya class-based |
| Database | SQLite (bawaan). Kode harus tetap jalan di MySQL |
| Tes | PHPUnit bawaan Laravel |

Paket yang boleh dipasang hanya: `livewire/livewire`, `bootstrap`, `@popperjs/core`. Jangan memasang paket lain.

Proyek juga punya tes e2e Playwright di `tests/e2e/`, dijalankan dengan `npm run test:e2e`. Paket `@playwright/test` sudah terpasang, jangan menghapus atau memperbaruinya. Tes e2e hanya diubah jika file tugas memintanya (pertama kali di tugas 11).

## 5. Hal yang berbeda dari versi lama

Pengetahuan Anda mungkin berasal dari Laravel dan Livewire versi lama. Ikuti aturan berikut.

Laravel 13:

- Tidak ada `app/Http/Kernel.php`. Middleware diatur di `bootstrap/app.php`.
- Model memakai atribut PHP, contohnya `#[Fillable(['name', 'email'])]`. Lihat `app/Models/User.php` dan tiru gayanya.
- Cast ditulis sebagai method `casts()`, bukan properti `$casts`.
- Proyek baru memakai Tailwind. Tugas 01 menggantinya dengan Bootstrap. Setelah itu jangan memakai kelas Tailwind.
- Tampilan pagination bawaan memakai Tailwind. Tugas 01 mengubahnya ke Bootstrap dengan `Paginator::useBootstrapFive()`.

Livewire 4:

- Perintah `php artisan make:livewire` secara bawaan membuat komponen satu file dengan awalan emoji petir. JANGAN pakai format itu.
- Selalu buat komponen dengan `php artisan make:livewire NamaKomponen --class`. Hasilnya dua file: `app/Livewire/NamaKomponen.php` dan `resources/views/livewire/nama-komponen.blade.php`.
- Komponen ditanam di view Blade biasa dengan `<livewire:nama-komponen />`.
- Jangan memakai `Route::livewire()`. Jangan menjalankan `php artisan livewire:layout`. Semua halaman dilayani controller biasa.
- `wire:model="x"` tidak mengirim data ke server sampai ada aksi. Agar langsung terkirim, pakai `wire:model.live="x"`.
- Agar terkirim saat kursor keluar dari kolom, pakai `wire:model.live.blur="x"`. `wire:model.blur` saja tidak mengirim ke server.
- Livewire sudah membawa Alpine.js. Jangan memasang Alpine lagi.
- Setiap elemen di dalam `@foreach` pada view Livewire wajib punya `wire:key` yang unik.

## 6. Konvensi kode

- Nama kelas, tabel, kolom, variabel, dan rute memakai bahasa Inggris.
- Semua teks yang dilihat pengguna memakai bahasa Indonesia: label, tombol, pesan galat, pesan sukses.
- Uang disimpan sebagai bilangan bulat dalam rupiah. Jangan memakai `float` atau `decimal` untuk uang.
- Uang ditampilkan dengan `App\Support\Format::rupiah()`. Contoh hasil: `Rp248.000`.
- Jumlah barang ditampilkan dengan `App\Support\Format::quantity()`. Contoh hasil: `2` dan `0,5`.
- Nomor HP dinormalkan dengan `App\Support\Phone::normalize()` sebelum disimpan atau dicari.
- Isian angka dari form dirapikan dengan `App\Support\Parse` sebelum divalidasi: `Parse::quantity()` untuk jumlah (koma dan titik sama-sama tanda desimal, sejak tugas 12) dan `Parse::rupiah()` untuk uang (titik ribuan dibuang, sejak tugas 13).
- Kolom uang di form memakai `type="text"`, `inputmode="numeric"`, dan atribut `data-rupiah`, dengan nilai awal dari `Format::thousands()`. Kolom jumlah memakai `type="text"`. Jangan memakai `type="number"` untuk uang atau jumlah (sejak tugas 12 dan 13).
- Nama barang disimpan dalam huruf besar lewat `ProductService::normalizeName()` (sejak tugas 14).
- Zona waktu aplikasi `Asia/Jakarta`. Tanggal ditampilkan dengan format `d/m/Y H:i`.
- Nilai status memakai enum PHP di `app/Enums`. Jangan menulis teks status langsung di banyak tempat.
- Controller tipis: validasi, panggil service atau model, kembalikan view atau redirect.
- Semua penulisan data transaksi wajib lewat `App\Services\TransactionService`. Controller dan komponen Livewire tidak boleh membuat `Transaction` atau `TransactionItem` sendiri.
- Semua view memakai `@extends('layouts.app')` dan `@section('content')`.
- Semua form `POST`, `PUT`, `DELETE` memakai `@csrf`.
- Tampilan dirancang untuk layar HP lebih dulu. Pakai komponen Bootstrap: `card`, `list-group`, `table-responsive`, `btn`, `form-control`, `form-select`, `badge`, `alert`.
- Jangan menulis SQL khusus satu database. Pakai query builder dan Eloquent.

## 7. Kamus istilah

Dokumen desain memakai bahasa Indonesia. Kode memakai bahasa Inggris.

| Istilah Indonesia | Model | Tabel |
|---|---|---|
| Barang | `Product` | `products` |
| Riwayat harga | `ProductPrice` | `product_prices` |
| Pelanggan | `Customer` | `customers` |
| Transaksi | `Transaction` | `transactions` |
| Item transaksi | `TransactionItem` | `transaction_items` |
| Harga pelanggan | `CustomerPrice` | `customer_prices` |

| Istilah Indonesia | Kolom |
|---|---|
| Nama | `name` |
| Satuan | `unit` |
| Kategori | `category` |
| Kode | `code` |
| Harga jual | `sell_price` |
| Harga modal | `cost_price` |
| Berlaku sejak | `effective_from` |
| Nomor HP | `phone` |
| Alamat | `address` |
| Catatan | `note` |
| Nomor transaksi | `number` |
| Tanggal transaksi | `transacted_at` |
| Saluran | `channel` |
| Status bayar | `payment_status` |
| Jumlah | `quantity` |
| Harga satuan (harga saat itu) | `unit_price` |
| Harga daftar | `list_price` |
| Harga pelanggan (salinan di item transaksi) | `customer_price` |
| Diskon | `discount` |

| Nilai di kode | Label di layar |
|---|---|
| `active` | Aktif |
| `needs_review` | Perlu dirapikan |
| `merged` | Digabung |
| `inactive` | Nonaktif |
| `draft` | Draf |
| `confirmed` | Dikonfirmasi |
| `cancelled` | Dibatalkan |
| `paid` | Lunas |
| `partial` | Sebagian |
| `unpaid` | Belum bayar |
| `store` | Toko |
| `whatsapp` | WhatsApp |
| `phone` | Telepon |

## 8. Aturan bisnis inti

1. Setiap item transaksi wajib terhubung ke satu barang. Tidak ada item berupa teks bebas.
2. Harga adalah riwayat. Perubahan harga menambah baris baru di `product_prices`. Baris lama tidak pernah diubah atau dihapus. Sejak tugas 17, aturan yang sama berlaku untuk harga pelanggan di `customer_prices`.
3. Harga yang berlaku untuk suatu barang adalah baris `product_prices` dengan `effective_from` terbaru yang tidak melewati waktu sekarang.
4. Item transaksi menyimpan salinan harga (`unit_price`), harga daftar saat itu (`list_price`), dan satuan (`unit`). Nota lama tidak berubah ketika harga barang berubah.
5. Barang yang dibuat dari form transaksi berstatus `needs_review`. Barang yang dibuat dari halaman barang berstatus `active`.
6. Jika harga barang lama diubah di form transaksi, bawaannya hanya berlaku untuk nota itu. Riwayat harga bertambah hanya jika pengguna memilih "Jadikan harga baru" (harga umum) atau, sejak tugas 18, "Jadikan harga pelanggan".
7. Simpan transaksi bersifat utuh: barang baru, harga baru, pelanggan baru, transaksi, dan itemnya tersimpan semua atau tidak sama sekali.
8. Isi transaksi hanya bisa diubah jika `Transaction::isEditable()` bernilai `true`: berstatus `draft`, atau berstatus `confirmed` dengan status bayar `unpaid`. Transaksi yang sudah `partial`, `paid`, atau `cancelled` tidak bisa diubah isinya. (Sebelum tugas 15: transaksi `confirmed` sama sekali tidak bisa diubah isinya.)
9. Laporan hanya menghitung transaksi berstatus `confirmed`.
10. Barang yang sudah dipakai di transaksi tidak bisa dihapus dan satuannya tidak bisa diubah.
11. Nomor HP pelanggan unik. Pencocokan pelanggan memakai nomor HP yang sudah dinormalkan.
12. Jumlah barang boleh desimal (paling banyak 3 angka di belakang koma) hanya untuk satuan di `config('shop.decimal_units')`. Satuan lain wajib bilangan bulat (sejak tugas 12).
13. Hanya draf yang boleh dihapus. Draf tidak masuk laporan (sejak tugas 15).
14. Urutan harga di nota: harga khusus per nota (yang diketik di form), lalu harga pelanggan, lalu harga umum. Harga pelanggan berupa angka tetap. `list_price` di item transaksi tetap salinan harga umum, dan `customer_price` salinan harga pelanggan (sejak tugas 17 dan 18).
15. Perubahan harga massal tidak boleh berlaku mundur, dan ditolak seluruhnya jika ada harga yang menjadi di bawah Rp0 (sejak tugas 19).

## 9. Daftar rute

Semua rute selain login wajib memakai middleware `auth`.

| Method | URL | Nama rute | Dibuat di tugas |
|---|---|---|---|
| GET | `/login` | `login` | 03 |
| POST | `/login` | `login.store` | 03 |
| POST | `/logout` | `logout` | 03 |
| GET | `/` | `dashboard` | 01, dilengkapi di 09 |
| GET | `/products` | `products.index` | 04 |
| GET | `/products/bulk-price` | `products.bulk-price.create` | 19 |
| POST | `/products/bulk-price/preview` | `products.bulk-price.preview` | 19 |
| POST | `/products/bulk-price` | `products.bulk-price.store` | 19 |
| GET | `/products/create` | `products.create` | 04 |
| POST | `/products` | `products.store` | 04 |
| GET | `/products/{product}` | `products.show` | 04 |
| GET | `/products/{product}/edit` | `products.edit` | 04 |
| PUT | `/products/{product}` | `products.update` | 04 |
| POST | `/products/{product}/prices` | `products.prices.store` | 04 |
| POST | `/products/{product}/mark-reviewed` | `products.mark-reviewed` | 04 |
| GET | `/customers` | `customers.index` | 05 |
| GET | `/customers/create` | `customers.create` | 05 |
| POST | `/customers` | `customers.store` | 05 |
| GET | `/customers/{customer}` | `customers.show` | 05 |
| GET | `/customers/{customer}/edit` | `customers.edit` | 05 |
| PUT | `/customers/{customer}` | `customers.update` | 05 |
| DELETE | `/customers/{customer}` | `customers.destroy` | 05 |
| POST | `/customers/{customer}/prices` | `customers.prices.store` | 17 |
| POST | `/customers/{customer}/prices/{product}/stop` | `customers.prices.stop` | 17 |
| GET | `/transactions` | `transactions.index` | 08 |
| GET | `/transactions/create` | `transactions.create` | 07 |
| GET | `/transactions/{transaction}` | `transactions.show` | 08 |
| GET | `/transactions/{transaction}/edit` | `transactions.edit` | 15 |
| DELETE | `/transactions/{transaction}` | `transactions.destroy` | 15 |
| POST | `/transactions/{transaction}/cancel` | `transactions.cancel` | 08 |
| POST | `/transactions/{transaction}/payment-status` | `transactions.payment-status` | 08 |
| GET | `/reports/daily` | `reports.daily` | 09 |

Rute `/transactions/create` harus didaftarkan sebelum `/transactions/{transaction}`. Rute `/products/bulk-price` harus didaftarkan sebelum `Route::resource('products', ...)`.

## 10. Struktur folder

```
app/
  Enums/            ProductStatus, TransactionStatus, PaymentStatus, Channel
  Http/Controllers/ satu controller per bagian
  Livewire/         TransactionForm
  Models/           Product, ProductPrice, Customer, CustomerPrice, Transaction, TransactionItem, User
  Services/         ProductService, CustomerService, CustomerPriceService, TransactionService, BulkPriceService, DailyReportService
  Support/          Format, Parse, Phone
config/shop.php     daftar satuan, satuan desimal, dan data akun pemilik
resources/views/
  layouts/app.blade.php
  auth/ products/ customers/ transactions/ reports/ livewire/
resources/js/       app.js, rupiah.js (titik ribuan, sejak tugas 13), check-all.js ("Pilih semua", sejak tugas 19)
tests/Feature/      satu file tes per bagian
tests/Unit/         tes untuk Format dan Phone
tests/e2e/          tes Playwright
```

## 11. Cara mengerjakan satu tugas

1. Baca file tugas sampai habis sebelum menulis kode.
2. Pastikan prasyaratnya terpenuhi. Jika belum, berhenti dan laporkan.
3. Kerjakan langkah demi langkah sesuai urutan.
4. Tulis semua tes yang diminta di bagian "Tes yang wajib ada".
5. Jalankan semua perintah di bagian "Kriteria selesai".
6. Jika ada tes gagal, perbaiki kodenya. Jangan menghapus atau melemahkan tes agar lulus.
7. Tulis laporan selesai.

## 12. Format laporan selesai

```
Tugas: <nomor dan judul>
File dibuat: <daftar>
File diubah: <daftar>
Hasil php artisan test: <jumlah lulus dan gagal>
Hasil npm run build: <berhasil atau gagal>
Kriteria selesai: <tiap butir, terpenuhi atau tidak>
Hal yang tidak bisa dikerjakan atau menyimpang dari instruksi: <daftar, atau "tidak ada">
```

## 13. Larangan

- Jangan mengerjakan tugas berikutnya sebelum diminta.
- Jangan mengubah migrasi yang sudah dibuat di tugas sebelumnya, kecuali file tugas memintanya.
- Jangan memasang paket selain yang disebut di bagian 4.
- Jangan memakai starter kit Laravel (React, Vue, Livewire starter kit, Breeze, Jetstream).
- Jangan memakai kelas Tailwind, jQuery, atau CDN.
- Jangan membuat komponen Livewire selain yang diminta.
- Jangan menaruh logika simpan transaksi di controller atau komponen Livewire.
- Jangan menulis teks antarmuka dalam bahasa Inggris.
- Jangan menghapus tes yang sudah ada.

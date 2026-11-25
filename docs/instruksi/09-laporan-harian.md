# Tugas 09: Beranda dan Laporan Harian

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 08 selesai.

## Tujuan

Halaman laporan untuk satu tanggal, dan beranda yang menampilkan ringkasan hari ini.

## Yang dibuat

- `app/Services/DailyReportService.php`
- `app/Http/Controllers/DailyReportController.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/reports/daily.blade.php`
- Isi baru untuk `resources/views/dashboard.blade.php`
- Rute `reports.daily`, dan rute `dashboard` diarahkan ke controller
- `tests/Feature/DailyReportTest.php`

## Langkah

### 1. DailyReportService

```php
public function forDate(CarbonInterface $date): array
```

Rentang waktu: dari `$date->copy()->startOfDay()` sampai `$date->copy()->endOfDay()`, dibandingkan dengan `transacted_at` memakai `whereBetween`. Jangan memakai fungsi tanggal khusus satu database.

Semua angka kecuali `cancelled_count` hanya menghitung transaksi berstatus `confirmed`.

Kembalikan array dengan kunci berikut:

| Kunci | Isi |
|---|---|
| `date` | tanggal laporan |
| `revenue` | jumlah `total` |
| `transaction_count` | jumlah transaksi |
| `average` | `revenue` dibagi `transaction_count`, dibulatkan ke integer. `0` jika tidak ada transaksi |
| `unpaid_total` | jumlah `total` dari transaksi dengan `payment_status` `unpaid` atau `partial`. Sejak tugas 26: dikurangi jumlah `paid_amount` |
| `unpaid_count` | jumlah transaksi dengan `payment_status` `unpaid` atau `partial` |
| `cancelled_count` | jumlah transaksi berstatus `cancelled` pada tanggal itu |
| `by_channel` | array per saluran: `channel` (enum `Channel`), `count`, `revenue`. Sertakan ketiga saluran walaupun nilainya 0 |
| `top_products` | maksimal 10 barang: `product_id`, `name`, `unit`, `quantity`, `revenue`. Urut `revenue` terbesar |
| `new_products_count` | jumlah barang berstatus `needs_review` yang `created_at`-nya pada tanggal itu |
| `transactions` | koleksi transaksi `confirmed` pada tanggal itu dengan `customer`, urut `transacted_at` terbaru |

Query `top_products`: gabungkan `transaction_items` dengan `transactions` dan `products`, saring status `confirmed` dan rentang tanggal, kelompokkan per `product_id`, jumlahkan `quantity` dan `subtotal`.

```php
TransactionItem::query()
    ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
    ->join('products', 'products.id', '=', 'transaction_items.product_id')
    ->where('transactions.status', TransactionStatus::Confirmed->value)
    ->whereBetween('transactions.transacted_at', [$start, $end])
    ->groupBy('transaction_items.product_id', 'products.name', 'products.unit')
    ->orderByDesc('revenue')
    ->limit(10)
    ->get([
        'transaction_items.product_id',
        'products.name',
        'products.unit',
        DB::raw('SUM(transaction_items.quantity) as quantity'),
        DB::raw('SUM(transaction_items.subtotal) as revenue'),
    ]);
```

Hasil `SUM` bisa berupa string. Saat menyusun `top_products`, ubah `quantity` ke `float` dan `revenue` ke `int`. Lakukan hal yang sama untuk `revenue` dan `unpaid_total`.

### 2. Rute

Di dalam grup `auth`:

```php
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/reports/daily', DailyReportController::class)->name('reports.daily');
```

Hapus rute `/` lama yang memakai closure. Kedua controller adalah controller satu aksi dengan method `__invoke`.

### 3. DailyReportController

- Parameter query `date`, format `Y-m-d`. Validasi: opsional, `date_format:Y-m-d`.
- Jika kosong, pakai hari ini (`today()`).
- Panggil `DailyReportService::forDate` dan kirim hasilnya ke view `reports.daily`.
- Kirim juga tanggal sebelumnya dan sesudahnya untuk tombol navigasi.

### 4. View laporan harian

- Judul `Laporan harian`.
- Form pilih tanggal (`method="GET"`, `input type="date" name="date"`) dengan tombol "Lihat". Tombol `‹ Sebelumnya` dan `Berikutnya ›` di sampingnya. Tombol "Berikutnya" tidak ditampilkan jika tanggal laporan adalah hari ini.
- Empat kartu ringkasan dalam grid (`row` dengan `col-6 col-md-3`):
  - `Omzet`: `revenue`
  - `Transaksi`: `transaction_count`
  - `Rata-rata nota`: `average`
  - `Belum lunas`: `unpaid_total`, dengan teks kecil `{unpaid_count} transaksi`
- Jika `cancelled_count` lebih dari 0: teks kecil `{n} transaksi dibatalkan`.
- Jika `new_products_count` lebih dari 0: `alert alert-warning` berisi `{n} barang baru perlu dirapikan.` dengan tautan ke `/products?status=needs_review`.
- Kartu `Per saluran`: daftar tiga saluran dengan jumlah transaksi dan omzet.
- Kartu `Barang terlaris`: `table-responsive` dengan kolom Barang, Jumlah (`Format::quantity` dan satuan), Omzet. Jika kosong: `Belum ada penjualan.`
- Kartu `Transaksi`: `list-group` berisi nomor, jam (`H:i`), pelanggan atau `Pembeli umum`, total. Tiap baris tautan ke `transactions.show`. Jika kosong: `Belum ada transaksi pada tanggal ini.`

Semua nilai uang memakai `Format::rupiah`.

### 5. Beranda

`DashboardController` memanggil `DailyReportService::forDate(today())`, dan menghitung jumlah seluruh barang berstatus `needs_review`.

Isi `dashboard.blade.php`:

- Tombol besar selebar layar: `+ Transaksi baru` ke `transactions.create` (`btn btn-primary btn-lg w-100`).
- Subjudul `Hari ini, {tanggal d/m/Y}`.
- Empat kartu ringkasan yang sama dengan laporan harian.
- Jika ada barang `needs_review`: `alert alert-warning` berisi `{n} barang perlu dirapikan.` dengan tautan ke `/products?status=needs_review`.
- Daftar 5 transaksi terakhir hari ini.
- Tautan `Lihat laporan lengkap` ke `reports.daily`.

Karena kartu ringkasan dipakai di dua halaman, taruh di partial `resources/views/reports/_summary.blade.php` dan panggil dengan `@include`.

## Tes yang wajib ada

`tests/Feature/DailyReportTest.php`, memakai `RefreshDatabase` dan `actingAs`. Buat transaksi lewat `TransactionService`.

Siapkan data untuk satu tanggal (misalnya kemarin):

- Transaksi A: saluran `store`, `paid`, item "Mi Instan" 2 × 115000. Total 230000.
- Transaksi B: saluran `whatsapp`, `unpaid`, item "Mi Instan" 1 × 118000 dan "Gula" 1 × 18000. Total 136000.
- Transaksi C: saluran `store`, total 50000, lalu dibatalkan.
- Transaksi D: tanggal dua hari lalu, total 99000.

Periksa hasil `forDate` untuk tanggal kemarin:

1. `revenue` = `366000`.
2. `transaction_count` = `2`.
3. `average` = `183000`.
4. `unpaid_total` = `136000` dan `unpaid_count` = `1`.
5. `cancelled_count` = `1`.
6. `by_channel`: `store` 1 transaksi 230000; `whatsapp` 1 transaksi 136000; `phone` 0 transaksi 0.
7. `top_products`: urutan pertama "Mi Instan" dengan `quantity` 3 dan `revenue` 348000; kedua "Gula" dengan `revenue` 18000.
8. Transaksi D tidak ikut terhitung.

Tes halaman:

9. Tamu yang membuka `/reports/daily` dialihkan ke `/login`.
10. `/reports/daily?date={kemarin}` menampilkan `Rp366.000`.
11. `/reports/daily` tanpa parameter memakai hari ini dan berstatus 200.
12. `/reports/daily?date=salah` ditolak validasi.
13. Beranda `/` berstatus 200 dan memuat teks `Transaksi baru`.
14. Laporan untuk tanggal tanpa transaksi menampilkan `Rp0` dan tidak galat (tidak ada pembagian dengan nol).

## Kriteria selesai

- [ ] `php artisan test` lulus semua.
- [ ] `npm run build` berhasil.
- [ ] Di browser: beranda menampilkan ringkasan hari ini dan tombol transaksi baru.
- [ ] Di browser: laporan harian bisa berpindah tanggal.
- [ ] Transaksi yang dibatalkan tidak menambah omzet.
- [ ] Halaman rapi di lebar layar 375px.

## Jangan lakukan

- Jangan membuat grafik. Jangan memasang pustaka grafik.
- Jangan membuat laporan mingguan, bulanan, margin, atau laba.
- Jangan membuat ekspor PDF, Excel, atau CSV.
- Jangan memakai `whereDate`, `DATE()`, atau fungsi SQL khusus satu database.

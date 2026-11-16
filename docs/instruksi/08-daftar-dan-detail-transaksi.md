# Tugas 08: Daftar dan Detail Transaksi

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 07 selesai.

## Tujuan

Pemilik bisa melihat daftar transaksi, membuka detailnya, membatalkan transaksi, dan mengubah status bayar.

## Yang dibuat

- Method `index`, `show`, `cancel`, `updatePaymentStatus` di `app/Http/Controllers/TransactionController.php`
- View `resources/views/transactions/index.blade.php` dan `show.blade.php`
- Rute transaksi
- Perubahan kecil di `app/Livewire/TransactionForm.php`
- `tests/Feature/TransactionPageTest.php`

## Langkah

### 1. Rute

Di dalam grup `auth`. Perhatikan urutannya: `create` sebelum `{transaction}`.

```php
Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');
Route::post('/transactions/{transaction}/payment-status', [TransactionController::class, 'updatePaymentStatus'])->name('transactions.payment-status');
```

Rute `transactions.create` sudah ada dari tugas 07. Jangan mendaftarkannya dua kali.

### 2. Controller

`index`

Parameter query, semuanya opsional:

| Parameter | Arti |
|---|---|
| `q` | cari di `number` atau nama pelanggan |
| `from` | tanggal awal, format `Y-m-d` |
| `to` | tanggal akhir, format `Y-m-d` |
| `status` | nilai `TransactionStatus` |
| `payment_status` | nilai `PaymentStatus` |

- Filter tanggal memakai `transacted_at`: dari awal hari `from` sampai akhir hari `to`. Pakai `Carbon::parse($from)->startOfDay()` dan `Carbon::parse($to)->endOfDay()`.
- Abaikan nilai `status` dan `payment_status` yang tidak sah (pakai `tryFrom`).
- Muat `customer` dengan `with('customer')`.
- Urutkan `transacted_at` terbaru di atas, lalu `id` terbaru.
- Pagination 20 per halaman dengan `withQueryString()`.

`show`

- Muat `items.product` dan `customer`.

`cancel(Request $request, Transaction $transaction, TransactionService $service)`

- Validasi: `reason` opsional, string, maksimal 255.
- Panggil `$service->cancel($transaction, $request->input('reason'))`.
- Redirect ke `transactions.show` dengan pesan `Transaksi dibatalkan.`
- Jika service melempar `ValidationException`, biarkan Laravel menanganinya.

`updatePaymentStatus(Request $request, Transaction $transaction, TransactionService $service)`

- Validasi: `payment_status` wajib, `Rule::enum(PaymentStatus::class)`.
- Panggil `$service->updatePaymentStatus($transaction, PaymentStatus::from($request->input('payment_status')))`.
- Redirect ke `transactions.show` dengan pesan `Status bayar diperbarui.`

Controller tidak boleh mengubah model `Transaction` secara langsung.

### 3. View `index`

- Judul "Transaksi" dan tombol "Transaksi baru" ke `transactions.create`.
- Form filter (`method="GET"`): kolom `q`, tanggal `from` dan `to` (`type="date"`), `select` status, `select` status bayar, tombol "Terapkan", dan tautan "Reset".
- Daftar memakai `list-group`. Tiap baris adalah tautan ke `transactions.show` dan menampilkan:
  - Baris pertama: nomor transaksi dan total (`Format::rupiah`) di kanan.
  - Baris kedua: tanggal (`d/m/Y H:i`), nama pelanggan atau `Pembeli umum`, dan `badge`.
- `badge` status transaksi hanya ditampilkan jika `cancelled` (merah, `text-bg-danger`).
- `badge` status bayar: `paid` hijau, `partial` kuning, `unpaid` merah.
- Transaksi `cancelled` ditampilkan dengan teks dicoret (`text-decoration-line-through`) pada totalnya.
- Jika kosong, tampilkan `Belum ada transaksi.`
- Pagination di bawah daftar.

### 4. View `show`

Bagian atas:

- Nomor transaksi sebagai judul.
- Tanggal, saluran (`label()`), status transaksi, status bayar.
- Pelanggan: nama sebagai tautan ke `customers.show` dan nomor HP. Jika tidak ada pelanggan, tampilkan `Pembeli umum`.
- Catatan, jika ada.
- Jika `cancelled`: `alert alert-danger` berisi `Dibatalkan pada {tanggal}` dan alasannya jika ada.

Daftar item. Tiap item menampilkan:

- Nama barang sebagai tautan ke `products.show`.
- `{jumlah} {satuan} × {harga satuan}`, dengan `Format::quantity` dan `Format::rupiah`.
- Jika `list_price` tidak `null` dan berbeda dari `unit_price`: teks kecil `Harga daftar: Rp...`.
- Jika `list_price` `null`: `badge` kecil `Barang baru`.
- Diskon, jika lebih dari 0.
- Subtotal di kanan.

Di bawah daftar item: Total dengan huruf tebal.

Aksi, hanya untuk transaksi `confirmed`:

- Form ubah status bayar: `select` `payment_status` dan tombol "Simpan status bayar".
- Form batal: kolom teks `reason` (placeholder `Alasan pembatalan (opsional)`) dan tombol merah "Batalkan transaksi" dengan `onsubmit="return confirm('Batalkan transaksi ini? Tindakan ini tidak bisa dibatalkan.')"`.
- Teks kecil di bawahnya: `Transaksi yang sudah disimpan tidak bisa diubah. Untuk koreksi, batalkan lalu buat transaksi baru.`

Untuk transaksi `cancelled`, jangan tampilkan kedua form itu.

### 5. Perbarui pengalihan di form transaksi

Di `TransactionForm::save()`, ganti `url('/transactions/'.$transaction->id)` dengan `route('transactions.show', $transaction)`.

## Tes yang wajib ada

`tests/Feature/TransactionPageTest.php`, memakai `RefreshDatabase` dan `actingAs`. Buat transaksi lewat `TransactionService`.

1. Tamu yang membuka `/transactions` dialihkan ke `/login`.
2. Daftar menampilkan nomor transaksi yang ada.
3. Filter `from` dan `to` hanya menampilkan transaksi di rentang itu.
4. Filter `payment_status=unpaid` hanya menampilkan transaksi yang belum bayar.
5. Pencarian `q` dengan nama pelanggan menemukan transaksinya.
6. Halaman detail menampilkan nama barang, total, dan nama pelanggan.
7. Halaman detail transaksi dengan harga satuan berbeda dari harga daftar menampilkan teks `Harga daftar`.
8. `POST /transactions/{id}/cancel` mengubah status menjadi `cancelled` dan menyimpan alasan.
9. Membatalkan transaksi yang sudah dibatalkan kembali dengan galat sesi dan status tidak berubah.
10. `POST /transactions/{id}/payment-status` dengan `paid` mengubah status bayar.
11. `payment_status` dengan nilai tidak sah ditolak.
12. Halaman detail transaksi `cancelled` tidak memuat tombol "Batalkan transaksi".
13. `GET /transactions/create` tetap membuka form, bukan dianggap sebagai id transaksi.

## Kriteria selesai

- [ ] `php artisan test` lulus semua.
- [ ] `npm run build` berhasil.
- [ ] Di browser: setelah menyimpan transaksi dari form, pengguna tiba di halaman detail dengan pesan sukses.
- [ ] Di browser: bisa memfilter daftar, membatalkan transaksi, dan mengubah status bayar.
- [ ] Halaman rapi di lebar layar 375px.

## Jangan lakukan

- Jangan membuat halaman ubah transaksi.
- Jangan membuat fitur hapus transaksi.
- Jangan membuat fitur salin transaksi, cetak, atau kirim ke WhatsApp.
- Jangan mengubah `TransactionService`, kecuali ada tes dari tugas 06 yang gagal.

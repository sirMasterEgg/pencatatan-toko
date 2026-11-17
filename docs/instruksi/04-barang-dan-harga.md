# Tugas 04: Barang dan Riwayat Harga

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 03 selesai.

## Tujuan

Pemilik bisa melihat daftar barang, menambah barang, mengubah data barang, melihat riwayat harga, dan menambah harga baru. Barang yang dibuat dari form transaksi (tugas 07) akan muncul di sini dengan tanda "Perlu dirapikan".

## Yang dibuat

- `app/Services/ProductService.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/ProductPriceController.php`
- View di `resources/views/products/`: `index`, `create`, `edit`, `show`, dan partial `_form`
- Rute barang di dalam grup `auth`
- `tests/Feature/ProductTest.php`

## Langkah

### 1. ProductService

Service ini juga dipakai `TransactionService` di tugas 06. Buat tiga method publik dengan tanda tangan persis seperti ini:

```php
public function findByNameAndUnit(string $name, string $unit): ?Product

public function create(
    string $name,
    string $unit,
    int $sellPrice,
    ?int $costPrice = null,
    ProductStatus $status = ProductStatus::Active,
    ?int $sourceTransactionId = null,
    ?string $category = null,
    ?string $code = null,
): Product

public function addPrice(
    Product $product,
    int $sellPrice,
    ?int $costPrice = null,
    ?string $note = null,
    ?int $sourceTransactionId = null,
): ProductPrice
```

Perilaku:

`findByNameAndUnit`

- Rapikan nama dengan `trim` dan ubah ke huruf kecil dengan `mb_strtolower`.
- Cari barang berstatus `active` atau `needs_review` yang nama huruf kecilnya sama dan satuannya sama.
- Contoh query: `Product::selectable()->whereRaw('LOWER(name) = ?', [$lowerName])->where('unit', $unit)->first()`.

`create`

- Jalankan di dalam `DB::transaction`.
- Simpan `name` yang sudah di-`trim`.
- Buat barang, isi `created_from_transaction_id` dengan `$sourceTransactionId`.
- Panggil `addPrice` untuk membuat baris harga pertama.
- Kembalikan barang.
- Method ini tidak memeriksa duplikat. Pemanggil yang memeriksa dengan `findByNameAndUnit`.

`addPrice`

- Buat baris `product_prices` dengan `effective_from` bernilai `now()`.
- Jangan mengubah baris harga lama.

### 2. Rute

Tambahkan di dalam grup `auth`:

```php
Route::resource('products', ProductController::class)->except(['destroy']);
Route::post('/products/{product}/prices', [ProductPriceController::class, 'store'])->name('products.prices.store');
Route::post('/products/{product}/mark-reviewed', [ProductController::class, 'markReviewed'])->name('products.mark-reviewed');
```

### 3. ProductController

`index`

- Parameter query: `q` (pencarian nama atau kode) dan `status` (nilai `ProductStatus`, opsional).
- Tanpa filter status, tampilkan semua kecuali `merged`.
- Muat `currentPrice` dengan `with('currentPrice')`.
- Urutkan berdasarkan `name`. Pagination 20 per halaman dengan `withQueryString()`.

`create` dan `store`

- Validasi `store`:
  - `name`: wajib, string, maksimal 255.
  - `unit`: wajib, harus salah satu dari `config('shop.units')`.
  - `sell_price`: wajib, integer, minimal 0.
  - `cost_price`: opsional, integer, minimal 0.
  - `category`: opsional, string, maksimal 255.
  - `code`: opsional, string, maksimal 255, unik di `products`.
- Jika `findByNameAndUnit` menemukan barang, kembalikan galat pada `name`: `Barang dengan nama dan satuan ini sudah ada.`
- Simpan lewat `ProductService::create` dengan status `Active`.
- Redirect ke `products.show` dengan pesan sukses `Barang berhasil ditambahkan.`
- Catatan: tujuan redirect ini diganti oleh tugas 11 (`11-alur-tambah-barang-dan-pelanggan.md`). Teks pesannya tetap.

`show`

- Tampilkan data barang, harga yang berlaku, dan riwayat harga (terbaru di atas).
- Tampilkan 10 item transaksi terakhir barang ini dari transaksi berstatus `confirmed`: tanggal, nomor transaksi, jumlah, harga satuan. Sebelum tugas 06 selesai daftar ini kosong, tampilkan teks `Belum ada transaksi.`
- Sediakan form "Tambah harga baru" di halaman ini.
- Jika status `needs_review`, tampilkan tombol "Tandai sudah diperiksa".

`edit` dan `update`

- Kolom yang bisa diubah: `name`, `unit`, `category`, `code`, dan `status`.
- Pilihan `status` di form hanya "Aktif" dan "Nonaktif". Jika status barang saat ini `needs_review`, form tidak mengubah status.
- Validasi sama dengan `store`, tanpa `sell_price` dan `cost_price`. Aturan unik `code` mengabaikan barang itu sendiri.
- Jika barang sudah punya item transaksi (`$product->items()->exists()`) dan `unit` berubah, kembalikan galat pada `unit`: `Satuan tidak bisa diubah karena barang sudah dipakai di transaksi.`
- Jika nama atau satuan berubah dan `findByNameAndUnit` menemukan barang LAIN, kembalikan galat pada `name` seperti di `store`.
- Redirect ke `products.show` dengan pesan `Barang berhasil diperbarui.`

`markReviewed`

- Hanya berlaku jika status `needs_review`. Ubah ke `active`.
- Redirect kembali dengan pesan `Barang ditandai sudah diperiksa.`

Tidak ada `destroy`. Barang tidak bisa dihapus, hanya dinonaktifkan.

### 4. ProductPriceController

`store(Request $request, Product $product)`

- Validasi: `sell_price` wajib, integer, minimal 0; `cost_price` opsional, integer, minimal 0; `note` opsional, string, maksimal 255.
- Simpan lewat `ProductService::addPrice`.
- Redirect ke `products.show` dengan pesan `Harga baru berhasil disimpan.`

Tidak ada rute untuk mengubah atau menghapus harga.

### 5. View

`index`

- Judul "Barang" dan tombol "Tambah barang".
- Form pencarian: kolom teks `q` dan `select` status dengan pilihan "Semua", "Aktif", "Perlu dirapikan", "Nonaktif".
- Daftar memakai `list-group`. Tiap baris menampilkan nama, satuan, harga jual saat ini dengan `Format::rupiah`, dan `badge` status. Seluruh baris adalah tautan ke `products.show`.
- Barang tanpa harga menampilkan `-`.
- Di bawah daftar tampilkan `{{ $products->links() }}`.
- Jika kosong, tampilkan `Belum ada barang.`

`_form`

- Dipakai `create` dan `edit`.
- `select` satuan diisi dari `config('shop.units')`.
- Kolom harga jual dan harga modal hanya muncul di `create`. Pakai `type="number"`, `min="0"`, `step="1"`, `inputmode="numeric"`.
- Tiap kolom menampilkan galat dengan `@error`.

`show`

- Bagian riwayat harga memakai `table-responsive` dengan kolom: Berlaku sejak, Harga jual, Harga modal, Catatan.
- Form "Tambah harga baru": harga jual, harga modal (terisi harga modal saat ini), catatan.

Warna `badge` status: `active` hijau (`text-bg-success`), `needs_review` kuning (`text-bg-warning`), `inactive` abu-abu (`text-bg-secondary`).

## Tes yang wajib ada

`tests/Feature/ProductTest.php`, memakai `RefreshDatabase` dan `actingAs` pengguna:

1. Tamu yang membuka `/products` dialihkan ke `/login`.
2. Menyimpan barang baru membuat satu baris `products` berstatus `active` dan satu baris `product_prices` dengan harga yang dikirim.
3. Menyimpan barang dengan nama dan satuan yang sama dengan barang yang ada (beda huruf besar kecil, misalnya "gula" dan "Gula") ditolak dengan galat pada `name`.
4. Nama sama dengan satuan berbeda diterima.
5. Satuan di luar `config('shop.units')` ditolak.
6. Menambah harga baru membuat `currentPrice` berubah ke harga itu, dan baris harga lama masih ada.
7. Halaman detail menampilkan riwayat harga.
8. `markReviewed` mengubah status `needs_review` menjadi `active`.
9. Pencarian `q` hanya menampilkan barang yang cocok.
10. Tidak ada rute `DELETE /products/{product}` (permintaan itu mengembalikan status 405).

## Kriteria selesai

- [ ] `php artisan test` lulus semua.
- [ ] `npm run build` berhasil.
- [ ] Di browser: bisa menambah barang, melihat detail, menambah harga, dan mengubah nama.
- [ ] Semua teks di layar berbahasa Indonesia.
- [ ] Halaman rapi di lebar layar 375px.

## Jangan lakukan

- Jangan membuat fitur hapus barang.
- Jangan membuat fitur ubah atau hapus harga.
- Jangan membuat fitur gabung barang.
- Jangan memakai Livewire di halaman barang.

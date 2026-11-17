# Tugas 14: Nama Barang Huruf Besar

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 13 selesai.

## Tujuan

Nama barang di nota kertas biasanya ditulis dengan huruf besar. Agar daftar barang seragam, nama barang selalu disimpan dalam huruf besar, dari mana pun barang itu dibuat:

| Asal | Contoh ketikan | Tersimpan |
|---|---|---|
| Halaman tambah barang | `gula pasir` | `GULA PASIR` |
| Halaman ubah barang | `Gula Merah` | `GULA MERAH` |
| Barang baru dari form transaksi | `teh celup` | `TEH CELUP` |
| Barang yang sudah ada sebelum tugas ini | `Kopi Bubuk` | `KOPI BUBUK` (diubah sekali lewat migrasi) |

Kolom nama juga langsung tampil huruf besar saat diketik.

## Hubungan dengan tugas lama

| Tugas lama | Aturan lama | Aturan baru |
|---|---|---|
| 04, `ProductService::create` | Simpan `name` yang di-`trim` | Simpan `ProductService::normalizeName($name)`: di-`trim` lalu huruf besar |
| 04, `ProductController::update` | Simpan `trim($data['name'])` | Simpan `ProductService::normalizeName($data['name'])` |
| 04, pemeriksaan duplikat `findByNameAndUnit` | Tidak membedakan huruf besar kecil | Tidak berubah. "gula" tetap dianggap sama dengan "GULA" |
| 00, larangan mengubah migrasi lama | Berlaku | Tetap berlaku. Tugas ini menambah migrasi BARU, tidak mengubah migrasi lama |

Hanya nama barang yang diubah. Nama pelanggan, kategori, dan kode tidak diubah.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `database/migrations/2026_11_18_000001_uppercase_product_names.php`

## Yang diubah

- `app/Services/ProductService.php`
- `app/Http/Controllers/ProductController.php` (hanya method `update`)
- `resources/views/products/_form.blade.php`
- `resources/views/livewire/transaction-form.blade.php`
- `tests/Feature/ProductTest.php`, `tests/Feature/TransactionServiceTest.php` (menambah tes)
- Lima file tes lama yang mengharapkan nama huruf kecil, lihat langkah 5
- `tests/e2e/barang.spec.js`, `tests/e2e/halaman-hp.spec.js`

## Langkah

### 1. ProductService

Tambahkan method statis ini tepat di atas `findByNameAndUnit`:

```php
public static function normalizeName(string $name): string
{
    return mb_strtoupper(trim($name));
}
```

Di `create`, ubah baris `'name' => trim($name),` menjadi:

```php
'name' => self::normalizeName($name),
```

Aturan:

- Pakai `mb_strtoupper`, bukan `strtoupper`, agar huruf non-ASCII ikut diubah.
- Jangan mengubah `findByNameAndUnit`. Pencarian duplikat sudah membandingkan dengan huruf kecil di kedua sisi.
- `TransactionService` membuat barang baru lewat `ProductService::create`, jadi tidak perlu diubah.

### 2. ProductController

Di method `update`, ubah baris `'name' => trim($data['name']),` di dalam `$product->fill([...])` menjadi:

```php
'name' => ProductService::normalizeName($data['name']),
```

`use App\Services\ProductService;` sudah ada di file itu. Bagian lain tidak berubah.

### 3. Migrasi data

Buat `database/migrations/2026_11_18_000001_uppercase_product_names.php` dengan isi persis ini:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tugas 14: nama barang selalu huruf besar. Ubah data lama sekali saja.
     */
    public function up(): void
    {
        DB::table('products')->orderBy('id')->chunkById(100, function ($products) {
            foreach ($products as $product) {
                $name = mb_strtoupper(trim($product->name));

                if ($name !== $product->name) {
                    DB::table('products')->where('id', $product->id)->update(['name' => $name]);
                }
            }
        });
    }

    /**
     * Nama asli tidak disimpan, jadi tidak bisa dikembalikan.
     */
    public function down(): void
    {
        //
    }
};
```

Aturan:

- Nama file harus persis itu. Tes di langkah "Tes yang wajib ada" memuat file ini dengan namanya.
- Pakai `mb_strtoupper` di PHP, bukan `UPPER()` di SQL. `UPPER()` di SQLite hanya mengubah huruf ASCII.
- Jangan mengubah struktur tabel. Migrasi ini hanya mengubah isi data.
- Jalankan `php artisan migrate` setelah membuat file ini.

### 4. View

Di `resources/views/products/_form.blade.php`, kolom Nama menjadi:

```blade
<input type="text" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" class="form-control text-uppercase @error('name') is-invalid @enderror" autocapitalize="characters" required>
```

Di `resources/views/livewire/transaction-form.blade.php`, kolom Nama barang baru menjadi:

```blade
<input type="text" id="name-{{ $i }}" class="form-control text-uppercase @error("items.$i.new_product.name") is-invalid @enderror" autocapitalize="characters" wire:model="items.{{ $i }}.name">
```

Penjelasan:

- `text-uppercase` adalah kelas Bootstrap. Ketikan tampil huruf besar, tetapi nilai yang dikirim tetap apa adanya. Server yang mengubahnya menjadi huruf besar.
- `autocapitalize="characters"` membuat papan ketik HP langsung dalam mode huruf besar.
- Jangan menambah JavaScript untuk mengubah huruf.
- Kolom "Cari barang" di form transaksi tidak diubah. Pencarian tetap tidak membedakan huruf besar kecil.

### 5. Sesuaikan tes lama

Tes berikut membuat barang lewat `POST /products` atau lewat `TransactionService`, lalu mencari atau memeriksa nama dengan huruf kecil. Setelah tugas ini namanya tersimpan huruf besar, jadi tes itu gagal. Ubah HANYA teks nama pada baris berikut. Jangan mengubah baris lain.

| File | Baris yang berisi | Ubah menjadi |
|---|---|---|
| `tests/Feature/FullFlowTest.php` | `Product::where('name', 'Mi Instan')` | `Product::where('name', 'MI INSTAN')` |
| `tests/Feature/FullFlowTest.php` | `$gula = Product::where('name', 'Gula')` | `$gula = Product::where('name', 'GULA')` |
| `tests/Feature/FullFlowTest.php` | `->assertSee('Gula')` setelah `/products?status=needs_review` | `->assertSee('GULA')` |
| `tests/Feature/FullFlowTest.php` | `Product::where('name', 'Gula')->first()` di akhir tes | `Product::where('name', 'GULA')->first()` |
| `tests/Feature/TransactionFormTest.php` | `Product::where('name', 'Gula')->firstOrFail()->status` | `Product::where('name', 'GULA')->firstOrFail()->status` |
| `tests/Feature/TransactionServiceTest.php` | `$gula = Product::where('name', 'Gula')->firstOrFail();` | `$gula = Product::where('name', 'GULA')->firstOrFail();` |
| `tests/Feature/DailyReportTest.php` | `$this->assertSame('Gula', $r['top_products'][1]['name']);` | `$this->assertSame('GULA', $r['top_products'][1]['name']);` |
| `tests/Feature/TransactionPageTest.php` | `->assertSee('Gula Pasir')` | `->assertSee('GULA PASIR')` |

Baris yang memakai `Product::factory()->create(['name' => ...])` TIDAK diubah, karena factory tidak lewat `ProductService`. Contohnya `assertSame('Mi Instan', ...)` di `DailyReportTest` tetap seperti itu.

Ini satu-satunya perubahan tes lama yang diizinkan di tugas ini. Setelah mengubah tabel di atas, jalankan `php artisan test`. Jika masih ada tes gagal karena nama huruf besar di baris lain, berhenti dan laporkan. Jangan mengubah tes lain sendiri.

### 6. Sesuaikan tes e2e

`getByText` dan `getByRole` dengan teks biasa tidak membedakan huruf besar kecil, jadi kebanyakan tes e2e tetap lulus. Pola `new RegExp(...)` membedakannya.

Di `tests/e2e/halaman-hp.spec.js`, ubah:

```js
    await page.getByRole('button', { name: new RegExp(`Barang HP ${suffix}`) }).first().click();
```

menjadi:

```js
    await page.getByRole('button', { name: new RegExp(`Barang HP ${suffix}`, 'i') }).first().click();
```

Tanpa `'i'`, pola itu tidak cocok dengan hasil pencarian `BARANG HP ...`, dan malah mengenai tombol "+ Tambah ... sebagai barang baru".

Di `tests/e2e/barang.spec.js`, pada tes pertama, tepat di bawah baris:

```js
    await expect(page.getByText('Barang berhasil ditambahkan.')).toBeVisible();
```

tambahkan:

```js

    // Tugas 14: nama tersimpan dalam huruf besar.
    await expect(page.getByText(name.toUpperCase(), { exact: true })).toBeVisible();
```

## Tes yang wajib ada

Tambahkan ke `tests/Feature/ProductTest.php`:

1. `POST /products` dengan nama `'  gula pasir '` lulus validasi, dan nama tersimpan `'GULA PASIR'`.
2. `PUT /products/{id}` dengan nama `'Gula Merah'` (satuan sama) lulus validasi, dan nama tersimpan `'GULA MERAH'`.
3. `POST /products` dengan nama `'Gula'`, lalu `POST /products` lagi dengan `'gula'` dan satuan sama menghasilkan galat pada `name`. Jumlah barang tetap 1.
4. `GET /products/create` berisi `text-uppercase` (pakai `assertSee(..., false)`).
5. Buat barang lewat factory dengan nama `'kopi bubuk'`, jalankan migrasi dengan kode di bawah, lalu nama barang menjadi `'KOPI BUBUK'`:

   ```php
   $migration = require database_path('migrations/2026_11_18_000001_uppercase_product_names.php');
   $migration->up();
   ```

Tambahkan ke `tests/Feature/TransactionServiceTest.php`:

6. Transaksi dengan `newItem('teh celup', 'pak')` membuat barang bernama `'TEH CELUP'`.
7. Ada barang `'TEH CELUP'` satuan `pak` (lewat helper `productWithPrice`). Transaksi dengan `newItem('Teh Celup', 'pak', ['unit_price' => 8000])` tidak membuat barang baru. Jumlah barang tetap 1, dan item transaksi memakai barang yang sudah ada.

## Kriteria selesai

- [ ] `php artisan migrate` berhasil dan nama barang lama menjadi huruf besar.
- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 7.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes tidak bertambah.
- [ ] Di browser HP: mengetik di kolom nama barang langsung tampil huruf besar, di halaman barang maupun di form transaksi.
- [ ] Barang yang disimpan tampil huruf besar di daftar barang, detail barang, dan detail transaksi.
- [ ] Nama pelanggan tidak berubah.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah migrasi lama atau struktur tabel.
- Jangan memakai `UPPER()` di SQL, mutator atau accessor model, atau event model. Satu-satunya tempat mengubah nama adalah `ProductService::normalizeName`.
- Jangan mengubah huruf nama pelanggan, kategori, atau kode.
- Jangan menambah JavaScript.
- Jangan mengubah tes lama selain yang tertulis di langkah 5 dan 6.
- Jangan menghapus atau melemahkan tes agar lulus.

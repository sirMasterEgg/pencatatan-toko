# Tugas 12: Jumlah Desimal dan Koma

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 11 selesai.

## Tujuan

Barang timbang dan ukur dijual dalam pecahan, misalnya 0,5 kg gula atau 1,5 meter kabel. Ada dua masalah di kolom "Jumlah" pada form transaksi saat ini:

1. Kolom memakai `type="number"`. Jika pengguna mengetik `0,5` (pakai koma, seperti kebiasaan di Indonesia), peramban diam-diam mengubahnya menjadi `05`, yaitu 5. Tidak ada pesan galat, dan nota tersimpan dengan jumlah sepuluh kali lipat.
2. Semua satuan menerima desimal, termasuk `pcs` dan `dus` yang seharusnya bilangan bulat.

Setelah tugas ini:

| Satuan | Jumlah yang diterima |
|---|---|
| `kg`, `gram`, `liter`, `meter` | Bilangan bulat atau desimal, paling banyak 3 angka di belakang koma. Koma dan titik sama-sama dibaca sebagai tanda desimal: `0,5` dan `0.5` sama dengan setengah |
| Satuan lain (`pcs`, `dus`, `pak`, `lusin`, `botol`, `sak`, `karton`, `rol`) | Hanya bilangan bulat. `1,5 pcs` ditolak dengan pesan `Jumlah untuk satuan pcs harus bilangan bulat.` |

## Hubungan dengan tugas lama

| Tugas lama | Aturan lama | Aturan baru |
|---|---|---|
| 06, validasi `items.*.quantity` | `required`, `numeric`, `gt:0` | Ditambah `decimal:0,3`, dan bilangan bulat untuk satuan di luar `shop.decimal_units` |
| 06, `TransactionService::create` | Data langsung divalidasi | Jumlah dirapikan dulu dengan `Parse::quantity`, lalu divalidasi |
| 07, kolom Jumlah | `type="number"`, `step="0.001"` | `type="text"`, `inputmode` mengikuti satuan, ditambah teks satuan di bawah kolom |

Tidak ada kolom database, rute, atau paket baru. Kolom `transaction_items.quantity` sudah `decimal(12, 3)` sejak tugas 02.

Tampilan jumlah tidak berubah. `Format::quantity()` sudah menampilkan `0,5`.

Tugas ini memakai tes e2e Playwright di `tests/e2e/`, sama seperti tugas 11. Cara menjalankannya ada di bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `app/Support/Parse.php`
- `tests/Unit/ParseTest.php`

## Yang diubah

- `config/shop.php`
- `app/Services/TransactionService.php`
- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `tests/Feature/TransactionServiceTest.php` (menambah tes)
- `tests/Feature/TransactionFormTest.php` (menambah tes)
- `tests/e2e/form-transaksi.spec.js` (menambah tes)

## Langkah

### 1. Config

Di `config/shop.php`, tepat di bawah baris `'units' => [...]`, tambahkan:

```php

    // Satuan yang jumlahnya boleh desimal. Satuan lain wajib bilangan bulat.
    'decimal_units' => ['kg', 'gram', 'liter', 'meter'],
```

Jangan mengubah daftar `units`.

### 2. Kelas Parse

Buat `app/Support/Parse.php` dengan isi persis ini:

```php
<?php

namespace App\Support;

class Parse
{
    /**
     * Ubah isian jumlah dari form menjadi angka bertitik desimal.
     *
     * "0,5" dan "0.5" menjadi "0.5", "2" menjadi "2", kosong menjadi null.
     * Isian yang bukan angka dikembalikan apa adanya agar ditolak validasi.
     */
    public static function quantity(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value === null ? null : (string) $value;
        }

        $text = str_replace([' ', ','], ['', '.'], trim((string) $value));

        if ($text === '') {
            return null;
        }

        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 ? $text : $value;
    }
}
```

Catatan:

- Kelas ini kebalikan dari `Format`. `Format` mengubah angka menjadi teks untuk layar, sedangkan `Parse` mengubah isian form menjadi angka. Tugas 13 akan menambah method `rupiah()` ke kelas yang sama.
- Titik ribuan tidak didukung untuk jumlah. `1.000` dibaca sebagai 1. Jumlah barang ribuan jarang terjadi di toko ini, dan pengguna bisa mengetik `1000`.

### 3. TransactionService

Tambahkan `use App\Support\Parse;` di bagian atas.

Di `create`, tambahkan satu baris sebelum `$this->validate($data);`:

```php
public function create(array $data): Transaction
{
    $data = $this->normalize($data);
    $this->validate($data);
    // ... sisanya tidak berubah
```

Tambahkan method privat ini tepat di atas `private function validate(`:

```php
private function normalize(array $data): array
{
    foreach ($data['items'] ?? [] as $index => $item) {
        if (is_array($item) && array_key_exists('quantity', $item)) {
            $data['items'][$index]['quantity'] = Parse::quantity($item['quantity']);
        }
    }

    return $data;
}
```

Di `validate`, ubah aturan jumlah menjadi:

```php
'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
```

Tambahkan pesan ini ke array `$messages`:

```php
'items.*.quantity.decimal' => 'Jumlah paling banyak 3 angka di belakang koma.',
```

Masih di `validate`, pada perulangan `foreach ($data['items'] as $index => $item)` setelah `Validator::make(...)->validate();`, ubah bagian awal perulangan menjadi seperti ini. Bagian pemeriksaan subtotal di bawahnya tidak berubah.

```php
foreach ($data['items'] as $index => $item) {
    $unit = $item['new_product']['unit'] ?? null;

    if (! empty($item['product_id'])) {
        $product = Product::find($item['product_id']);
        $unit = $product?->unit;
        if ($product && ! in_array($product->status, [ProductStatus::Active, ProductStatus::NeedsReview], true)) {
            $errors["items.{$index}.product_id"] = 'Barang ini sudah tidak aktif.';
        }
    }

    $quantity = (float) $item['quantity'];
    if ($unit !== null && ! in_array($unit, config('shop.decimal_units'), true) && floor($quantity) !== $quantity) {
        $errors["items.{$index}.quantity"] = "Jumlah untuk satuan {$unit} harus bilangan bulat.";
    }

    // ... pemeriksaan subtotal yang sudah ada, tidak berubah
}
```

Aturan:

- Satuan barang lama diambil dari barangnya (`$product->unit`). Satuan barang baru diambil dari `new_product.unit`.
- `2,0` untuk `pcs` diterima, karena nilainya bulat.
- Jangan memindahkan logika ini ke controller atau komponen Livewire.

### 4. Komponen TransactionForm

Tambahkan `use App\Support\Parse;` di bagian atas.

Di `rowSubtotal`, ubah baris pertama menjadi:

```php
$quantity = Parse::quantity($row['quantity'] ?? null);
```

Sisa method tidak berubah. Tanpa perubahan ini, subtotal untuk `0,5` tampil `Rp0`, karena `is_numeric('0,5')` bernilai `false`.

Di `render`, tambahkan `decimalUnits` ke data view:

```php
return view('livewire.transaction-form', [
    'units' => config('shop.units'),
    'decimalUnits' => config('shop.decimal_units'),
    'channels' => Channel::cases(),
    'paymentStatuses' => PaymentStatus::cases(),
]);
```

Method `save` tidak perlu diubah. Service yang merapikan jumlah.

### 5. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, ganti kolom Jumlah:

```blade
<input type="number" id="qty-{{ $i }}" class="form-control @error("items.$i.quantity") is-invalid @enderror"
       wire:model.live.blur="items.{{ $i }}.quantity" min="0" step="0.001" inputmode="decimal">
@error("items.$i.quantity") <div class="invalid-feedback">{{ $message }}</div> @enderror
```

dengan:

```blade
<input type="text" id="qty-{{ $i }}" class="form-control @error("items.$i.quantity") is-invalid @enderror"
       wire:model.live.blur="items.{{ $i }}.quantity" inputmode="{{ in_array($row['unit'], $decimalUnits, true) ? 'decimal' : 'numeric' }}" autocomplete="off">
@error("items.$i.quantity") <div class="invalid-feedback">{{ $message }}</div> @enderror
<div class="form-text">{{ $row['unit'] }}</div>
```

Aturan:

- Wajib `type="text"`. Hanya dengan `type="text"` koma tidak dibuang peramban.
- `inputmode="decimal"` menampilkan papan ketik angka berkoma di HP. `inputmode="numeric"` menampilkan papan ketik angka tanpa koma.
- Label kolom tetap "Jumlah". Tes e2e mencari kolom lewat label itu.

## Tes yang wajib ada

`tests/Unit/ParseTest.php`, memakai `PHPUnit\Framework\TestCase` seperti `tests/Unit/FormatTest.php`. Satu method `test_quantity` dengan pemeriksaan:

| Masukan | Hasil |
|---|---|
| `'0,5'` | `'0.5'` |
| `'0.5'` | `'0.5'` |
| `' 1,25 '` | `'1.25'` |
| `'2'` | `'2'` |
| `2` (int) | `'2'` |
| `''` | `null` |
| `null` | `null` |
| `'abc'` | `'abc'` |
| `'1,2,3'` | `'1,2,3'` |

Tambahkan ke `tests/Feature/TransactionServiceTest.php`, memakai helper `productWithPrice`, `base`, `existingItem`, dan `newItem` yang sudah ada di file itu:

1. Barang `kg` dengan jumlah `'0,5'` dan harga 18000: total `9000`, dan `quantity` item tersimpan `'0.500'`.
2. Untuk setiap satuan di `config('shop.decimal_units')`, jumlah `'1,5'` diterima. Jumlah transaksi sama dengan jumlah satuan desimal.
3. Barang `pcs` dengan jumlah `'1,5'` ditolak. Pesan galat `items.0.quantity` persis `Jumlah untuk satuan pcs harus bilangan bulat.`, dan tidak ada transaksi tersimpan.
4. Barang baru (`newItem('Sabun', 'pcs', ...)`) dengan jumlah `'0,5'` ditolak dengan `ValidationException`.
5. Barang `pcs` dengan jumlah `'2,0'` dan harga 3000 diterima dengan total `6000`.
6. Jumlah `'0,1234'` untuk barang `kg` ditolak dengan `ValidationException`.
7. Jumlah `'1,2,3'` ditolak dengan `ValidationException`.

Contoh bentuk tes nomor 3, tiru gayanya:

```php
public function test_decimal_quantity_is_rejected_for_pcs(): void
{
    $product = $this->productWithPrice('Sabun', 'pcs', 3000);

    try {
        $this->service->create($this->base([$this->existingItem($product, ['quantity' => '1,5'])]));
        $this->fail('Seharusnya ditolak.');
    } catch (ValidationException $e) {
        $this->assertSame('Jumlah untuk satuan pcs harus bilangan bulat.', $e->errors()['items.0.quantity'][0]);
    }

    $this->assertSame(0, Transaction::count());
}
```

Tambahkan ke `tests/Feature/TransactionFormTest.php`:

8. `rowSubtotal(['quantity' => '0,5', 'unit_price' => 18000, 'discount' => 0])` pada `Livewire::test(TransactionForm::class)->instance()` mengembalikan `9000`.

Tambahkan tes e2e ini di akhir `tests/e2e/form-transaksi.spec.js`:

```js

test('form transaksi: jumlah desimal dengan koma untuk satuan kg', async ({ page }) => {
    const name = `Beras ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('12000');
    await page.getByLabel('Jumlah').fill('1,5');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp18.000').first()).toBeVisible();

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('1,5 kg')).toBeVisible();
});
```

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

Jalankan sesuai urutan ini:

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 9.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] Di browser HP: kolom Jumlah untuk barang `kg` menampilkan papan ketik berkoma. `0,5` tersimpan dan tampil `0,5 kg` di detail transaksi.
- [ ] `1,5` untuk barang `pcs` ditolak dengan pesan berbahasa Indonesia di bawah kolom Jumlah.
- [ ] Halaman form transaksi rapi di lebar 375px.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah migrasi atau tipe kolom `quantity`.
- Jangan mengubah `Format::quantity()`.
- Jangan memakai `type="number"` untuk kolom Jumlah.
- Jangan menambah JavaScript untuk kolom Jumlah.
- Jangan memeriksa satuan desimal di komponen Livewire. Pemeriksaan hanya di `TransactionService`.
- Jangan menghapus atau melemahkan tes agar lulus.

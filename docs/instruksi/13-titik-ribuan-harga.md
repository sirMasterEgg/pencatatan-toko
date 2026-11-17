# Tugas 13: Titik Ribuan di Kolom Harga

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 12 selesai.

## Tujuan

Angka uang yang panjang sulit dibaca saat diketik di HP. Setelah tugas ini, setiap kolom uang langsung diberi titik ribuan saat diketik: `15000` tampil `15.000`, `1500000` tampil `1.500.000`.

Kolom yang diubah:

| Halaman | Kolom |
|---|---|
| Tambah barang (`products/_form`) | Harga jual, Harga modal |
| Detail barang, form "Tambah harga baru" | Harga jual, Harga modal |
| Form transaksi | Harga jual atau Harga satuan, Harga modal (barang baru), Diskon |

Database tetap menyimpan bilangan bulat rupiah. Titik hanya ada di layar dan dibuang di server sebelum validasi.

## Hubungan dengan tugas lama

| Tugas lama | Aturan lama | Aturan baru |
|---|---|---|
| 04, kolom harga di `_form` dan form tambah harga | `type="number"` | `type="text"`, `inputmode="numeric"`, atribut `data-rupiah` |
| 04, `ProductController::store` dan `ProductPriceController::store` | Request langsung divalidasi | `sell_price` dan `cost_price` dirapikan dulu dengan `Parse::rupiah` |
| 07, kolom harga dan diskon di form transaksi | `type="number"`, nilai berupa angka | `type="text"` dengan `data-rupiah`, nilai di komponen berupa teks bertitik seperti `'118.000'` |
| 00, larangan JavaScript tambahan | Belum ada JavaScript sendiri | Tugas ini menambah satu file `resources/js/rupiah.js` tanpa paket baru |

Validasi di server tidak berubah. Aturan `integer` dan `min:0` tetap berlaku, dan memeriksa nilai yang sudah dirapikan.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `resources/js/rupiah.js`

## Yang diubah

- `app/Support/Format.php`
- `app/Support/Parse.php`
- `app/Http/Controllers/ProductController.php` (hanya method `store`)
- `app/Http/Controllers/ProductPriceController.php`
- `app/Livewire/TransactionForm.php`
- `resources/js/app.js`
- `resources/views/products/_form.blade.php`
- `resources/views/products/show.blade.php`
- `resources/views/livewire/transaction-form.blade.php`
- `tests/Unit/FormatTest.php`, `tests/Unit/ParseTest.php` (menambah tes)
- `tests/Feature/ProductTest.php` (menambah tes)
- `tests/Feature/TransactionFormTest.php` (mengubah satu baris, menambah tes)
- `tests/e2e/barang.spec.js`, `tests/e2e/form-transaksi.spec.js`

## Langkah

### 1. Format::thousands

Di `app/Support/Format.php`, tambahkan method ini di antara `rupiah` dan `quantity`:

```php
public static function thousands(int|float|string|null $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (! is_numeric($value)) {
        return (string) $value;
    }

    return number_format((float) $value, 0, ',', '.');
}
```

Bedanya dengan `rupiah()`: tanpa awalan `Rp`, dan nilai kosong menjadi teks kosong. Method ini untuk mengisi nilai kolom form, bukan untuk tampilan biasa.

### 2. Parse::rupiah

Di `app/Support/Parse.php`, tambahkan method ini di bawah `quantity`:

```php
/**
 * Ubah isian uang dari form menjadi bilangan bulat rupiah.
 *
 * "15.000" menjadi 15000, "15000" menjadi 15000, kosong menjadi null.
 * Isian yang bukan angka dikembalikan apa adanya agar ditolak validasi.
 */
public static function rupiah(mixed $value): mixed
{
    if ($value === null || is_int($value)) {
        return $value;
    }

    $text = str_replace(['.', ' '], '', trim((string) $value));

    if ($text === '') {
        return null;
    }

    return ctype_digit($text) ? (int) $text : $value;
}
```

Rupiah tidak memakai sen, jadi koma tidak diterima. `15.000,50` dikembalikan apa adanya, lalu ditolak aturan `integer`.

### 3. JavaScript

Buat `resources/js/rupiah.js` dengan isi persis ini:

```js
// Kolom uang bertanda data-rupiah: 15000 menjadi 15.000 saat diketik.
export function formatRupiahInput(input) {
    const caret = input.selectionStart ?? input.value.length;
    const digitsAfterCaret = input.value.slice(caret).replace(/\D/g, '').length;
    const digits = input.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    const formatted = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    if (formatted === input.value) {
        return;
    }

    input.value = formatted;

    let position = formatted.length;
    let seen = 0;
    while (position > 0 && seen < digitsAfterCaret) {
        position--;
        if (/\d/.test(formatted[position])) {
            seen++;
        }
    }
    input.setSelectionRange(position, position);
}

// Fase capture, agar nilai sudah berformat sebelum Livewire membacanya.
document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('[data-rupiah]')) {
        formatRupiahInput(event.target);
    }
}, true);
```

Isi `resources/js/app.js` menjadi:

```js
import 'bootstrap';
import './rupiah';
```

Penjelasan, agar Anda tidak mengubahnya tanpa sengaja:

- Satu pendengar di `document` berlaku untuk semua halaman, termasuk kolom yang dirender ulang oleh Livewire. Jangan memasang pendengar per kolom.
- Argumen ketiga `true` (fase capture) wajib. Tanpa itu Livewire bisa membaca nilai sebelum diberi titik.
- Perhitungan `digitsAfterCaret` menjaga posisi kursor saat pengguna menyisipkan angka di tengah. Tes e2e memeriksanya.
- Jangan memakai Alpine, jQuery, atau paket format angka.

### 4. Controller barang

`ProductController::store`: tambahkan `use App\Support\Parse;` dan blok ini sebagai baris pertama method, sebelum `$request->validate(...)`:

```php
$request->merge([
    'sell_price' => Parse::rupiah($request->input('sell_price')),
    'cost_price' => Parse::rupiah($request->input('cost_price')),
]);
```

`ProductPriceController::store`: tambahkan `use App\Support\Parse;` dan blok yang sama sebagai baris pertama method.

Aturan validasi dan sisa kedua method tidak berubah. `update` di `ProductController` tidak diubah, karena form ubah barang tidak punya kolom harga.

### 5. View barang

Di `resources/views/products/_form.blade.php`, ubah awal tag kedua kolom harga. Atribut `class`, `@error`, dan `required` di belakangnya tetap.

Harga jual, dari:

```blade
<input type="number" id="sell_price" name="sell_price" value="{{ old('sell_price') }}" min="0" step="1" inputmode="numeric" class=
```

menjadi:

```blade
<input type="text" id="sell_price" name="sell_price" value="{{ \App\Support\Format::thousands(old('sell_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class=
```

Harga modal, dari:

```blade
<input type="number" id="cost_price" name="cost_price" value="{{ old('cost_price') }}" min="0" step="1" inputmode="numeric" class=
```

menjadi:

```blade
<input type="text" id="cost_price" name="cost_price" value="{{ \App\Support\Format::thousands(old('cost_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class=
```

Di `resources/views/products/show.blade.php` (form "Tambah harga baru"), lakukan perubahan yang sama. Pada kolom harga modal, nilai bawaannya tetap harga modal saat ini:

```blade
<input type="text" id="cost_price" name="cost_price" value="{{ \App\Support\Format::thousands(old('cost_price', $product->currentPrice?->cost_price)) }}" inputmode="numeric" autocomplete="off" data-rupiah class=
```

Aturan untuk semua kolom uang:

- `type="text"`, bukan `type="number"`. Kolom `type="number"` tidak bisa berisi titik.
- Hapus `min` dan `step`. Keduanya tidak berlaku untuk `type="text"`.
- Nilai awal selalu lewat `Format::thousands(...)`.

### 6. Komponen TransactionForm

Tambahkan `use App\Support\Format;` di bagian atas. `use App\Support\Parse;` sudah ada sejak tugas 12.

Mulai tugas ini, `unit_price`, `discount`, dan `cost_price` di setiap baris `$items` disimpan sebagai teks bertitik seperti `'118.000'`. `list_price` tetap bilangan bulat, karena tidak pernah diketik pengguna.

Di `selectProduct`, ubah satu baris:

```php
'unit_price' => Format::thousands($price),
```

Di `rowSubtotal`, ganti bagian setelah baris `$quantity = ...` sampai sebelum `return` menjadi:

```php
$unitPrice = Parse::rupiah($row['unit_price'] ?? null);

if (! is_numeric($quantity) || ! is_int($unitPrice)) {
    return 0;
}

$discount = Parse::rupiah($row['discount'] ?? null);
$discount = is_int($discount) ? $discount : 0;
```

Baris `return TransactionService::calculateSubtotal(...)` tidak berubah.

Di `save`, ubah isi closure `$toInt`:

```php
$toInt = fn ($v) => Parse::rupiah($v);
```

Pemakaian `$toInt` di bawahnya tidak berubah.

### 7. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, ubah tiga kolom berikut. Hapus `min` dan `step`, ganti `type="number"` menjadi `type="text"`, dan tambahkan `autocomplete="off" data-rupiah`.

Harga modal barang baru:

```blade
<input type="text" id="cost-{{ $i }}" class="form-control @error("items.$i.new_product.cost_price") is-invalid @enderror" wire:model="items.{{ $i }}.cost_price" inputmode="numeric" autocomplete="off" data-rupiah>
```

Harga satuan:

```blade
<input type="text" id="price-{{ $i }}" class="form-control @error("items.$i.unit_price") is-invalid @enderror"
       wire:model.live.blur="items.{{ $i }}.unit_price" inputmode="numeric" autocomplete="off" data-rupiah>
```

Diskon:

```blade
<input type="text" id="disc-{{ $i }}" class="form-control @error("items.$i.discount") is-invalid @enderror"
       wire:model.live.blur="items.{{ $i }}.discount" inputmode="numeric" autocomplete="off" data-rupiah>
```

Lalu ubah syarat munculnya pilihan "Hanya nota ini" / "Jadikan harga baru". `(int) '115.000'` bernilai `115`, jadi pembandingan lama salah. Ganti baris `@if` itu menjadi:

```blade
@if ($row['mode'] === 'existing' && $row['list_price'] !== null && is_int(\App\Support\Parse::rupiah($row['unit_price'])) && \App\Support\Parse::rupiah($row['unit_price']) !== (int) $row['list_price'])
```

Kolom Jumlah dari tugas 12 tidak diubah dan tidak diberi `data-rupiah`.

### 8. Ubah satu tes lama

Di `tests/Feature/TransactionFormTest.php`, tes `test_select_product_sets_existing_mode_with_list_price` memeriksa `->assertSet('items.0.unit_price', 118000)`. Ubah nilainya menjadi teks bertitik:

```php
->assertSet('items.0.unit_price', '118.000')
```

Ini satu-satunya baris tes lama yang boleh diubah di tugas ini. Pemeriksaan `list_price` di tes yang sama tetap bilangan bulat.

## Tes yang wajib ada

`tests/Unit/FormatTest.php`, tambah `test_thousands`:

| Masukan | Hasil |
|---|---|
| `15000` | `'15.000'` |
| `'1500000'` | `'1.500.000'` |
| `0` | `'0'` |
| `null` | `''` |
| `''` | `''` |
| `'abc'` | `'abc'` |

`tests/Unit/ParseTest.php`, tambah `test_rupiah`:

| Masukan | Hasil |
|---|---|
| `'15.000'` | `15000` |
| `'1.500.000'` | `1500000` |
| `'15000'` | `15000` |
| `15000` (int) | `15000` |
| `'0'` | `0` |
| `''` | `null` |
| `null` | `null` |
| `'abc'` | `'abc'` |
| `'15.000,50'` | `'15.000,50'` |

`tests/Feature/ProductTest.php`, tambahkan:

1. `POST /products` dengan `sell_price` `'15.000'` dan `cost_price` `'12.500'` lulus validasi. Harga tersimpan `15000` dan `12500`.
2. `POST /products` dengan `sell_price` `'15.000,50'` menghasilkan galat pada `sell_price`, dan tidak ada barang tersimpan.
3. `POST /products/{id}/prices` dengan `sell_price` `'17.500'` lulus validasi, dan `currentPrice->sell_price` menjadi `17500`. Sebelum mengirim, mundurkan `effective_from` harga pertama satu hari, seperti tes harga yang sudah ada.
4. Halaman detail barang dengan harga modal 12000 berisi `value="12.000"` dan `data-rupiah` (pakai `assertSee(..., false)`).

`tests/Feature/TransactionFormTest.php`, tambahkan:

5. `rowSubtotal(['quantity' => '2', 'unit_price' => '18.000', 'discount' => '1.000'])` mengembalikan `35000`.
6. Pilih barang lama (harga 118000) lewat `selectProduct`, isi `quantity` `'2'`, `unit_price` `'115.000'`, `discount` `'1.000'`, lalu `save`. Tidak ada galat. Item tersimpan dengan `unit_price` `115000`, `discount` `1000`, `subtotal` `229000`.

Tes e2e, `tests/e2e/barang.spec.js`, tambahkan di akhir file:

```js

test('barang: kolom harga langsung diberi titik ribuan', async ({ page }) => {
    const name = `Minyak E2E ${Date.now()}`;

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('liter');

    await page.getByLabel('Harga jual').pressSequentially('1500000');
    await expect(page.getByLabel('Harga jual')).toHaveValue('1.500.000');

    // Menyisipkan angka di tengah tetap rapi dan kursor tidak lompat ke akhir.
    await page.getByLabel('Harga modal').pressSequentially('12000');
    await page.getByLabel('Harga modal').press('Home');
    await page.getByLabel('Harga modal').press('ArrowRight');
    await page.getByLabel('Harga modal').pressSequentially('5');
    await expect(page.getByLabel('Harga modal')).toHaveValue('152.000');
    await page.getByLabel('Harga modal').pressSequentially('9');
    await expect(page.getByLabel('Harga modal')).toHaveValue('1.592.000');

    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);

    await page.goto(`/products?q=${encodeURIComponent(name)}`);
    await expect(page.getByText('Rp1.500.000')).toBeVisible();
});
```

Tes e2e, `tests/e2e/form-transaksi.spec.js`. Di tes `jumlah desimal dengan koma untuk satuan kg` dari tugas 12, ganti baris:

```js
    await page.getByLabel('Harga jual').fill('12000');
```

dengan:

```js
    await page.getByLabel('Harga jual').pressSequentially('12000');
    await expect(page.getByLabel('Harga jual')).toHaveValue('12.000');
```

Lalu tambahkan tes ini di akhir file:

```js

test('form transaksi: harga barang lama bertitik ribuan dan bisa diubah', async ({ page }) => {
    const name = `Kopi ${Date.now()}`;

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('pcs');
    await page.getByLabel('Harga jual').fill('118000');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: new RegExp(name, 'i') }).first().click();
    await expect(page.getByLabel('Harga satuan')).toHaveValue('118.000');

    await page.getByLabel('Harga satuan').fill('');
    await page.getByLabel('Harga satuan').pressSequentially('115000');
    await expect(page.getByLabel('Harga satuan')).toHaveValue('115.000');
    await page.getByLabel('Jumlah').fill('2');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp230.000').first()).toBeVisible();
    await expect(page.getByLabel('Jadikan harga baru')).toBeVisible();
    await expect(page.getByLabel('Harga satuan')).toHaveValue('115.000');

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('Rp230.000').first()).toBeVisible();
    await expect(page.getByText('Harga daftar: Rp118.000')).toBeVisible();
});
```

Tes e2e lain yang memakai `.fill('15000')` pada kolom harga tidak perlu diubah. `fill` juga memicu event `input`, jadi nilainya tetap diberi titik.

## Kriteria selesai

Jalankan sesuai urutan ini. `npm run build` wajib diulang setelah mengubah JavaScript.

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 8.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2.
- [ ] Di browser HP: di form tambah barang, tambah harga baru, dan form transaksi, mengetik `15000` langsung tampil `15.000`.
- [ ] Setelah disimpan, harga tampil benar di detail barang dan detail transaksi, misalnya `Rp15.000`, bukan `Rp15`.
- [ ] Mengubah harga barang lama di form transaksi tetap memunculkan pilihan "Jadikan harga baru".
- [ ] Tidak ada galat di konsol peramban.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah tipe kolom uang di database.
- Jangan mengubah `Format::rupiah()`.
- Jangan membuang titik di JavaScript sebelum form dikirim. Server yang membuangnya lewat `Parse::rupiah`.
- Jangan memberi `data-rupiah` pada kolom Jumlah.
- Jangan memasang paket JavaScript atau memakai Alpine.
- Jangan mengubah `TransactionService`. Komponen Livewire sudah mengirim bilangan bulat.
- Jangan menghapus atau melemahkan tes agar lulus.

# Tugas 21: Kolom Jumlah dan Diskon Kosong Saat Difokus Pertama Kali

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 20 selesai.

## Tujuan

Di form transaksi, kolom Jumlah sudah berisi `1` dan kolom Diskon berisi `0`. Untuk mengetik jumlah lain, pemilik harus menghapus angka itu dulu. Di HP ini lambat, dan sering menghasilkan angka salah seperti `13` karena angka 1 tidak terhapus.

Setelah tugas ini:

| Keadaan | Jumlah | Diskon |
|---|---|---|
| Tidak pernah difokus | Tetap `1` | Tetap `0` |
| Difokus PERTAMA kali dan isinya masih bawaan | Langsung kosong, siap diketik | Langsung kosong, siap diketik |
| Ditinggal dalam keadaan kosong | Kembali `1` | Kembali `0` |
| Difokus lagi setelah pernah difokus | Tidak dikosongkan, isinya tetap | Tidak dikosongkan, isinya tetap |
| Isinya bukan nilai bawaan saat difokus pertama kali (misalnya `2` saat mengubah draf) | Tidak dikosongkan | Tidak dikosongkan |

Server tidak berubah. Livewire tetap menerima `1` atau `0` jika kolom ditinggal kosong, jadi subtotal dan validasi tetap seperti sebelumnya.

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 07, kolom Jumlah dan Diskon | Nilai awal `1` dan `0`, tanpa perilaku khusus | Ditambah atribut `data-clear-default` |
| 07 dan 13, JavaScript | Hanya `rupiah.js` dan `check-all.js` (tugas 19) | Ditambah `resources/js/clear-default.js`, tanpa paket baru |
| 12 dan 13, isi kolom | Jumlah `type="text"`, Diskon `data-rupiah` | Tidak berubah. Atribut baru hanya ditambahkan |

Tidak ada rute, kolom, tabel, atau paket baru. `TransactionForm.php` dan `TransactionService` tidak diubah.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `resources/js/clear-default.js`
- `tests/e2e/jumlah-kosong-saat-fokus.spec.js`

## Yang diubah

- `resources/js/app.js`
- `resources/views/livewire/transaction-form.blade.php`
- `tests/Feature/TransactionFormTest.php` (menambah satu tes)
- `README.md` di akar proyek

## Langkah

### 1. JavaScript

Buat `resources/js/clear-default.js` dengan isi persis ini:

```js
// Kolom bertanda data-clear-default="<nilai bawaan>", misalnya Jumlah (1) dan Diskon (0) di form transaksi.
// - Saat difokus PERTAMA kali dan isinya masih nilai bawaan, kolom dikosongkan agar bisa langsung diketik.
// - Saat ditinggal dalam keadaan kosong, nilai bawaan dikembalikan.
const touched = new WeakSet();

const isTarget = (element) => element instanceof HTMLInputElement && element.matches('[data-clear-default]');

document.addEventListener('focusin', (event) => {
    const input = event.target;

    if (!isTarget(input) || touched.has(input)) {
        return;
    }

    touched.add(input);

    if (input.value === input.dataset.clearDefault) {
        input.value = '';
    }
});

// Fase capture pada "blur", agar nilai bawaan sudah kembali sebelum Livewire membacanya.
document.addEventListener('blur', (event) => {
    const input = event.target;

    if (isTarget(input) && input.value.trim() === '') {
        input.value = input.dataset.clearDefault;
    }
}, true);
```

Isi `resources/js/app.js` menjadi:

```js
import 'bootstrap';
import './rupiah';
import './check-all';
import './clear-default';
```

Penjelasan, agar tidak diubah tanpa sengaja:

- `WeakSet` menyimpan kolom yang sudah pernah difokus. Jangan menggantinya dengan atribut `data-...` di elemen. Livewire menggambar ulang form setelah setiap pembaruan dan menghapus atribut yang tidak ada di HTML dari server, sehingga tanda itu akan hilang dan kolom terkosongkan lagi. `WeakSet` tetap ingat karena Livewire memakai ulang elemen yang sama.
- Pendengar `blur` memakai fase capture (argumen ketiga `true`). Livewire membaca nilai kolom saat `blur` (`wire:model.live.blur`). Dengan capture, nilai bawaan sudah dikembalikan sebelum Livewire membacanya, sehingga server tidak pernah menerima jumlah kosong dari kolom yang hanya disentuh.
- Pendengar dipasang di `document`, jadi berlaku juga untuk baris yang ditambahkan Livewire belakangan.
- Kolom hanya dikosongkan jika isinya sama persis dengan nilai bawaan. Isian lain, misalnya `2` saat mengubah draf, tidak disentuh.

### 2. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, tambahkan atribut di akhir dua tag `<input>`. Atribut lain tidak berubah.

Kolom Jumlah, akhir tag menjadi:

```blade
inputmode="{{ in_array($row['unit'], $decimalUnits, true) ? 'decimal' : 'numeric' }}" autocomplete="off" data-clear-default="1">
```

Kolom Diskon, akhir tag menjadi:

```blade
wire:model.live.blur="items.{{ $i }}.discount" inputmode="numeric" autocomplete="off" data-rupiah data-clear-default="0">
```

Aturan:

- Nilai atribut harus sama persis dengan nilai bawaan yang tampil di kolom: `"1"` untuk Jumlah, `"0"` untuk Diskon.
- Jangan menambah atribut ini ke kolom Harga satuan, Harga jual, atau Harga modal. Kolom itu berisi harga barang, bukan nilai bawaan.
- Jangan mengubah nilai awal di `emptyRow()` (`'quantity' => '1'`, `'discount' => 0`).

### 3. README proyek

Di `README.md` akar proyek:

- Pada baris `Status`, ganti `Tugas 01 sampai 20` menjadi `Tugas 01 sampai 21`.
- Di bagian "Fitur tahap 1", tambahkan butir ini di paling bawah:

  ```
  - **Jumlah dan diskon cepat diisi**: di form transaksi, kolom Jumlah (1) dan Diskon (0) langsung kosong saat pertama kali disentuh, dan kembali ke nilai bawaan jika ditinggal kosong
  ```

Bagian lain README tidak diubah.

## Tes yang wajib ada

Tambahkan tes ini di akhir kelas `tests/Feature/TransactionFormTest.php`:

```php
public function test_quantity_and_discount_inputs_are_cleared_on_first_focus(): void
{
    $product = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
    ProductPrice::factory()->for($product)->create(['sell_price' => 18000]);

    Livewire::test(TransactionForm::class)
        ->call('selectProduct', 0, $product->id)
        ->assertSet('items.0.quantity', '1')
        ->assertSeeHtml('data-clear-default="1"')
        ->assertSeeHtml('data-clear-default="0"');
}
```

Perilaku di peramban tidak bisa diuji PHPUnit. Itu diuji tes e2e berikut. Buat `tests/e2e/jumlah-kosong-saat-fokus.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

// Mengisi baris barang baru ke-`row` (mulai 0). Menunggu sampai baris itu benar-benar tampil
// sebelum mengisi harga, agar tidak mengenai baris lain.
async function newItemRow(page, row, name, price) {
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await expect(page.getByLabel('Harga jual')).toHaveCount(row + 1);
    await page.getByLabel('Harga jual').nth(row).fill(price);
}

test('jumlah dan diskon: kosong saat difokus pertama kali, kembali ke bawaan jika ditinggal kosong', async ({ page }) => {
    const suffix = Date.now();

    await page.goto('/transactions/create');
    await newItemRow(page, 0, `Roti ${suffix}`, '5000');

    const qty = page.getByLabel('Jumlah').nth(0);
    const disc = page.getByLabel('Diskon').nth(0);
    await expect(qty).toHaveValue('1');
    await expect(disc).toHaveValue('0');

    // Fokus pertama: kosong, langsung ketik tanpa menghapus.
    await qty.click();
    await expect(qty).toHaveValue('');
    await page.keyboard.type('3');
    await disc.click();
    await expect(disc).toHaveValue('');
    await page.keyboard.type('1000');
    await expect(disc).toHaveValue('1.000');
    await qty.click();
    await expect(page.getByText('Subtotal: Rp14.000')).toBeVisible();

    // Fokus berikutnya tidak mengosongkan lagi.
    await expect(qty).toHaveValue('3');
    await disc.click();
    await expect(disc).toHaveValue('1.000');

    // Baris kedua: difokus lalu ditinggal kosong, kembali ke 1 dan 0.
    await page.getByRole('button', { name: '+ Tambah barang' }).click();
    await newItemRow(page, 1, `Susu ${suffix}`, '7000');
    const qty2 = page.getByLabel('Jumlah').nth(1);
    const disc2 = page.getByLabel('Diskon').nth(1);
    await qty2.click();
    await expect(qty2).toHaveValue('');
    await disc2.click();
    await expect(qty2).toHaveValue('1');
    await expect(disc2).toHaveValue('');
    await qty2.click();
    await expect(disc2).toHaveValue('0');
    await expect(page.getByText('Subtotal: Rp7.000')).toBeVisible();

    // Setelah form digambar ulang, kolom yang sudah pernah difokus tidak dikosongkan lagi.
    await page.getByRole('button', { name: '+ Tambah barang' }).click();
    await expect(page.getByPlaceholder('Ketik nama barang')).toHaveCount(1);
    await qty2.click();
    await expect(qty2).toHaveValue('1');

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('Rp21.000').first()).toBeVisible();
});

test('jumlah: ketik harga lalu langsung ketuk Jumlah dan ketik', async ({ page }) => {
    const name = `Cepat ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').click();
    await page.keyboard.type('5000');

    // Kursor pindah dari Harga jual ke Jumlah memicu pembaruan Livewire.
    // Angka yang diketik tidak boleh tergabung dengan 1 bawaan (misalnya menjadi 13).
    await page.getByLabel('Jumlah').click();
    await page.keyboard.type('3');
    await page.getByLabel('Diskon').click();
    await expect(page.getByLabel('Jumlah')).toHaveValue('3');
    await expect(page.getByText('Subtotal: Rp15.000')).toBeVisible();
});
```

Catatan untuk tes e2e:

- Kolom difokus dengan `.click()` dan diisi dengan `page.keyboard.type(...)`, bukan `.fill(...)`. `fill` mengganti isi kolom secara langsung, jadi tidak menguji perilaku "kosong saat difokus".
- Fungsi `newItemRow` menunggu jumlah kolom "Harga jual" bertambah sebelum mengisinya. Tanpa itu, tes bisa mengisi baris lain yang sudah ada, karena baris baru belum selesai digambar Livewire.
- Baris dipilih dengan `.nth(...)`, bukan `.last()`, karena alasan yang sama.

Tes lama tidak perlu diubah dan harus tetap lulus. Tes lama yang memakai `.fill(...)` pada kolom Jumlah tetap berjalan, karena `fill` langsung mengisi nilai baru.

## Kriteria selesai

Jalankan sesuai urutan ini. `npm run build` wajib diulang karena JavaScript berubah.

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 1.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2.
- [ ] Di browser HP: memilih barang, lalu mengetuk kolom Jumlah membuat kolom kosong, dan mengetik `3` menghasilkan `3`, bukan `13`.
- [ ] Mengetuk Jumlah lalu pindah ke kolom lain tanpa mengetik mengembalikan `1`, dan subtotal tetap benar.
- [ ] Kolom Diskon berperilaku sama dengan nilai bawaan `0`.
- [ ] Mengetuk Jumlah untuk kedua kalinya tidak menghapus isinya.
- [ ] Tidak ada galat di konsol peramban.
- [ ] `README.md` proyek sudah diperbarui.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengganti nilai awal dengan `placeholder`. Kolom harus benar-benar berisi `1` dan `0` sebelum disentuh.
- Jangan memakai `select()` atau memblok teks sebagai ganti pengosongan.
- Jangan mengubah `TransactionForm.php` atau `TransactionService`.
- Jangan memakai Alpine, jQuery, atau paket JavaScript.
- Jangan menambah perilaku ini ke form lain di luar form transaksi.
- Jangan menghapus atau melemahkan tes agar lulus.

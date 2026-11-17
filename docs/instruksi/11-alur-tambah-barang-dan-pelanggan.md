# Tugas 11: Alur Setelah Tambah Barang dan Pelanggan

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 10 selesai.

## Tujuan

Pemilik sering memasukkan banyak barang atau pelanggan berturut-turut. Saat ini setelah menyimpan, aplikasi membuka halaman detail, sehingga pemilik harus kembali dulu sebelum bisa menambah lagi.

Setelah tugas ini, form tambah barang dan form tambah pelanggan punya dua tombol:

| Tombol | Setelah berhasil disimpan |
|---|---|
| "Simpan" | Kembali ke halaman daftar (`products.index` atau `customers.index`) |
| "Simpan dan tambah lagi" | Membuka lagi form tambah yang kosong (`products.create` atau `customers.create`) |

Kedua tombol menampilkan pesan sukses yang sama seperti sekarang.

## Hubungan dengan tugas lama

Tugas ini mengubah satu hal dari tugas 04 dan satu hal dari tugas 05. Untuk hal berikut, file ini yang berlaku:

| Tugas lama | Aturan lama | Aturan baru |
|---|---|---|
| 04, `ProductController::store` | Redirect ke `products.show` | Redirect ke `products.index`, atau ke `products.create` jika tombol "Simpan dan tambah lagi" yang ditekan |
| 05, `CustomerController::store` | Redirect ke `customers.show` | Redirect ke `customers.index`, atau ke `customers.create` jika tombol "Simpan dan tambah lagi" yang ditekan |

Semua aturan lain di tugas 04 dan 05 tetap berlaku: validasi, pemeriksaan duplikat, teks pesan sukses, `update` yang tetap redirect ke halaman detail, dan isi halaman detail.

Tidak ada rute baru. Tabel "Daftar rute" di `00-aturan-umum.md` tidak berubah.

## Tentang tes e2e

Selain PHPUnit, proyek ini punya tes e2e Playwright di `tests/e2e/`. Tes itu ditambahkan di luar file tugas 01 sampai 10, jadi tidak disebut di `00-aturan-umum.md`.

- `@playwright/test` sudah terpasang. Jangan memasang paket apa pun.
- Jalankan dengan `npm run test:e2e`. Aset harus sudah dibangun dengan `npm run build`.
- Tes e2e memakai server sendiri di port 8001 dan database sendiri (`database/e2e.sqlite`). Data lokal tidak tersentuh.
- Jika peramban belum terpasang, jalankan sekali `npx playwright install chromium`. Jika peramban sudah ada di tempat lain, isi `PLAYWRIGHT_CHROMIUM_PATH` dengan letak berkasnya.

Tiga tes e2e yang sudah ada akan gagal setelah langkah 1 dan 2 dikerjakan, karena tombol "Simpan" menjadi dua dan halaman setelah simpan berubah. Tugas ini meminta Anda menyesuaikan tiga file itu di langkah 4. Ini pengecualian yang diizinkan dari larangan mengubah tes. Jangan mengubah file tes lain.

## Yang diubah

- `app/Http/Controllers/ProductController.php` (hanya method `store`)
- `app/Http/Controllers/CustomerController.php` (hanya method `store`)
- `resources/views/products/create.blade.php`
- `resources/views/customers/create.blade.php`
- `tests/Feature/ProductTest.php` (menambah tes)
- `tests/Feature/CustomerTest.php` (menambah tes)
- `tests/e2e/barang.spec.js`
- `tests/e2e/pelanggan.spec.js`
- `tests/e2e/halaman-hp.spec.js`
- `README.md` di akar proyek

Tidak ada file baru.

## Langkah

### 1. View form tambah

Di `resources/views/products/create.blade.php`, tepat di bawah tombol "Simpan" yang sudah ada, tambahkan satu tombol. Hasil akhirnya dua baris ini berurutan di dalam `<form>`:

```blade
<button type="submit" class="btn btn-primary w-100">Simpan</button>
<button type="submit" name="after_save" value="add_another" class="btn btn-outline-primary w-100 mt-2">Simpan dan tambah lagi</button>
```

Lakukan hal yang sama di `resources/views/customers/create.blade.php`.

Aturan:

- Tombol "Simpan" harus tetap yang pertama dan tetap tanpa atribut `name`. Saat pengguna menekan Enter di kolom teks, peramban memakai tombol kirim pertama, jadi Enter berarti "Simpan".
- Nama kolom persis `after_save` dan nilainya persis `add_another`.
- Jangan menambah `<input type="hidden" name="after_save">`. Nilai itu hanya boleh terkirim lewat tombol kedua.
- Jangan menambah JavaScript.
- Jangan mengubah `_form.blade.php`, `edit.blade.php`, `show.blade.php`, atau `index.blade.php`. Tombol kedua hanya ada di halaman tambah, tidak di halaman ubah.

### 2. Controller

`ProductController::store`

Validasi, pemeriksaan duplikat, dan pemanggilan `ProductService::create` tidak berubah. Yang berubah hanya bagian akhir method. Hasil `create` tidak lagi disimpan ke variabel karena tidak dipakai. Bagian akhir method menjadi:

```php
$this->products->create(
    name: $data['name'],
    unit: $data['unit'],
    sellPrice: (int) $data['sell_price'],
    costPrice: isset($data['cost_price']) ? (int) $data['cost_price'] : null,
    status: ProductStatus::Active,
    category: $data['category'] ?? null,
    code: $data['code'] ?? null,
);

$route = $request->input('after_save') === 'add_another' ? 'products.create' : 'products.index';

return redirect()->route($route)->with('success', 'Barang berhasil ditambahkan.');
```

`CustomerController::store`

Seluruh isi method menjadi:

```php
Customer::create($this->validated($request));

$route = $request->input('after_save') === 'add_another' ? 'customers.create' : 'customers.index';

return redirect()->route($route)->with('success', 'Pelanggan berhasil ditambahkan.');
```

Aturan:

- Hanya nilai persis `add_another` yang membuka form lagi. Nilai lain, atau tanpa nilai, kembali ke daftar.
- Jangan menambahkan `after_save` ke aturan validasi dan jangan menyimpannya ke database. Di `CustomerController`, method `validated()` tidak diubah.
- Jika validasi gagal, perilakunya tetap seperti sekarang: kembali ke form dengan galat dan isian lama. Tidak perlu kode tambahan.
- Jangan mengubah `update`, `show`, `markReviewed`, `destroy`, atau method lain.
- Teks pesan sukses tidak berubah.

### 3. Tes PHPUnit

Tambahkan tes di bagian "Tes yang wajib ada" ke file tes yang sudah ada. Tes lama tidak perlu diubah dan harus tetap lulus.

### 4. Sesuaikan tes e2e

`getByRole('button', { name: 'Simpan' })` mencocokkan sebagian teks, jadi sekarang cocok dengan dua tombol dan Playwright menolaknya. Di halaman tambah, pakai `{ name: 'Simpan', exact: true }`. Di halaman ubah hanya ada satu tombol, jadi tidak perlu diubah.

`tests/e2e/barang.spec.js`

Ganti seluruh isi file dengan:

```js
import { test, expect } from '@playwright/test';

test('barang: tambah, lihat detail, tambah harga, ubah nama', async ({ page }) => {
    const name = `Gula E2E ${Date.now()}`;

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('15000');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();

    // Tugas 11: setelah simpan kembali ke daftar barang.
    await page.waitForURL(/\/products$/);
    await expect(page.getByText('Barang berhasil ditambahkan.')).toBeVisible();

    await page.goto(`/products?q=${encodeURIComponent(name)}`);
    await page.getByRole('link', { name }).click();
    await expect(page.getByRole('heading', { name, level: 1 })).toBeVisible();
    await expect(page.getByText('Rp15.000').first()).toBeVisible();

    await page.getByLabel('Harga jual').fill('17500');
    await page.getByRole('button', { name: 'Simpan harga' }).click();
    await expect(page.getByText('Harga baru berhasil disimpan.')).toBeVisible();
    await expect(page.getByText('Rp17.500').first()).toBeVisible();

    await page.getByRole('link', { name: 'Ubah' }).click();
    await page.getByLabel('Nama').fill(`${name} Baru`);
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Barang berhasil diperbarui.')).toBeVisible();

    await page.goto('/products');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});

test('barang: simpan dan tambah lagi membuka form kosong', async ({ page }) => {
    const name = `Kopi E2E ${Date.now()}`;

    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('pcs');
    await page.getByLabel('Harga jual').fill('5000');
    await page.getByRole('button', { name: 'Simpan dan tambah lagi' }).click();

    await page.waitForURL(/\/products\/create$/);
    await expect(page.getByText('Barang berhasil ditambahkan.')).toBeVisible();
    await expect(page.getByLabel('Nama')).toHaveValue('');
    await expect(page.getByLabel('Harga jual')).toHaveValue('');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.goto(`/products?q=${encodeURIComponent(name)}`);
    await expect(page.getByRole('link', { name })).toBeVisible();
});
```

`tests/e2e/pelanggan.spec.js`

Di tes yang sudah ada, ganti dua baris ini:

```js
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Pelanggan berhasil ditambahkan.')).toBeVisible();
```

dengan:

```js
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();

    // Tugas 11: setelah simpan kembali ke daftar pelanggan.
    await page.waitForURL(/\/customers$/);
    await expect(page.getByText('Pelanggan berhasil ditambahkan.')).toBeVisible();
```

Baris `getByRole('button', { name: 'Simpan' })` yang lain di file itu berada di halaman ubah. Jangan diubah.

Lalu tambahkan tes baru di akhir file:

```js
test('pelanggan: simpan dan tambah lagi membuka form kosong', async ({ page }) => {
    const name = `Pak Budi ${Date.now()}`;

    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByRole('button', { name: 'Simpan dan tambah lagi' }).click();

    await page.waitForURL(/\/customers\/create$/);
    await expect(page.getByText('Pelanggan berhasil ditambahkan.')).toBeVisible();
    await expect(page.getByLabel('Nama')).toHaveValue('');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.goto(`/customers?q=${encodeURIComponent(name)}`);
    await expect(page.getByRole('link', { name })).toBeVisible();
});
```

`tests/e2e/halaman-hp.spec.js`

Tes ini dulu mengambil alamat halaman detail dari `page.url()` setelah simpan. Sekarang setelah simpan yang terbuka adalah daftar, jadi halaman detail harus dibuka lewat pencarian.

Ganti dua baris ini (bagian barang):

```js
    await page.getByRole('button', { name: 'Simpan' }).click();
    const productUrl = page.url();
```

dengan:

```js
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
    await page.goto(`/products?q=${encodeURIComponent(`Barang HP ${suffix}`)}`);
    await page.getByRole('link', { name: `Barang HP ${suffix}` }).click();
    await page.waitForURL(/\/products\/\d+$/);
    const productUrl = page.url();
```

Ganti dua baris ini (bagian pelanggan):

```js
    await page.getByRole('button', { name: 'Simpan' }).click();
    const customerUrl = page.url();
```

dengan:

```js
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/customers$/);
    await page.goto(`/customers?q=${encodeURIComponent(`Pelanggan HP ${suffix}`)}`);
    await page.getByRole('link', { name: `Pelanggan HP ${suffix}` }).click();
    await page.waitForURL(/\/customers\/\d+$/);
    const customerUrl = page.url();
```

Bagian lain file itu tidak diubah.

### 5. README proyek

Di `README.md` akar proyek:

- Pada baris `Status`, ganti `Tugas 01 sampai 10` menjadi `Tugas 01 sampai 11`.
- Di bagian "Fitur tahap 1", tambahkan satu butir di bawah butir "Pelanggan":

  ```
  - **Tambah beruntun**: form tambah barang dan tambah pelanggan punya tombol "Simpan" (kembali ke daftar) dan "Simpan dan tambah lagi" (membuka form kosong lagi)
  ```

Bagian lain README tidak diubah.

## Tes yang wajib ada

Tambahkan ke `tests/Feature/ProductTest.php`:

1. `POST /products` tanpa `after_save` dialihkan ke `/products` dan sesi berisi `success` dengan nilai `Barang berhasil ditambahkan.`
2. `POST /products` dengan `after_save` bernilai `add_another` dialihkan ke `/products/create`, sesi berisi pesan sukses yang sama, dan jumlah baris `products` adalah 1.
3. `POST /products` dengan `after_save` bernilai `lainnya` dialihkan ke `/products`.
4. `POST /products` dengan `name` kosong dan `after_save` bernilai `add_another` menghasilkan galat pada `name`, dan jumlah baris `products` tetap 0.
5. `GET /products/create` menampilkan teks `Simpan dan tambah lagi`. `GET /products/{id}/edit` tidak menampilkannya.

Tambahkan ke `tests/Feature/CustomerTest.php` lima tes yang sama untuk pelanggan:

6. `POST /customers` tanpa `after_save` dialihkan ke `/customers` dan sesi berisi `success` dengan nilai `Pelanggan berhasil ditambahkan.`
7. `POST /customers` dengan `after_save` bernilai `add_another` dialihkan ke `/customers/create`, sesi berisi pesan sukses yang sama, dan jumlah baris `customers` adalah 1.
8. `POST /customers` dengan `after_save` bernilai `lainnya` dialihkan ke `/customers`.
9. `POST /customers` dengan `name` kosong dan `after_save` bernilai `add_another` menghasilkan galat pada `name`, dan jumlah baris `customers` tetap 0.
10. `GET /customers/create` menampilkan teks `Simpan dan tambah lagi`. `GET /customers/{id}/edit` tidak menampilkannya.

Contoh bentuk tes nomor 2, tiru gayanya untuk yang lain:

```php
public function test_store_with_add_another_redirects_to_create_form(): void
{
    $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 15000, 'after_save' => 'add_another'])
        ->assertRedirect('/products/create')
        ->assertSessionHas('success', 'Barang berhasil ditambahkan.');

    $this->assertSame(1, Product::count());
}
```

Tes e2e yang wajib ada sudah tertulis lengkap di langkah 4.

## Kriteria selesai

Jalankan sesuai urutan ini. `npm run build` harus lebih dulu, karena tanpa aset hasil build banyak tes gagal dengan galat `Vite manifest not found`.

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 10 (saat file ini ditulis: dari 91 menjadi 101).
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2 (saat file ini ditulis: dari 11 menjadi 13).
- [ ] `php artisan route:list` tidak menunjukkan rute baru.
- [ ] Di browser: "Simpan" di form tambah barang kembali ke daftar barang dengan pesan sukses. "Simpan dan tambah lagi" membuka form kosong dengan pesan sukses. Hal yang sama berlaku di form tambah pelanggan.
- [ ] Halaman ubah barang dan ubah pelanggan tetap punya satu tombol "Simpan" dan tetap kembali ke halaman detail.
- [ ] Kedua tombol rapi di lebar layar 375px: bertumpuk, selebar kartu, tanpa gulir mendatar.
- [ ] `README.md` proyek sudah diperbarui.

Jika `npm run test:e2e` tidak bisa dijalankan, misalnya karena peramban tidak bisa dipasang, tulis apa adanya di laporan. Jangan menulis "lulus" tanpa benar-benar menjalankannya.

Pada laporan selesai, tambahkan satu baris di bawah `Hasil npm run build`:

```
Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>
```

## Jangan lakukan

- Jangan menambah rute, kolom, tabel, atau paket.
- Jangan mengubah teks pesan sukses.
- Jangan mengubah alur `update`. Setelah mengubah barang atau pelanggan, aplikasi tetap membuka halaman detail.
- Jangan menambah tombol di halaman detail, halaman daftar, atau halaman ubah.
- Jangan mengisi form berikutnya dengan data barang atau pelanggan sebelumnya. Form harus kosong.
- Jangan menyentuh form transaksi (`TransactionForm`) dan jangan memakai Livewire untuk tugas ini.
- Jangan menambah JavaScript.
- Jangan mengubah file tes selain yang disebut di bagian "Yang diubah".
- Jangan menghapus atau melemahkan tes agar lulus.

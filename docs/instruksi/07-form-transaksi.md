# Tugas 07: Form Transaksi

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 06 selesai dan semua tes lulus.

## Tujuan

Halaman "Transaksi baru" untuk mencatat satu nota. Di halaman ini pengguna bisa mencari barang, membuat barang baru beserta harganya, memilih atau membuat pelanggan, lalu menyimpan semuanya sekaligus.

Ini satu-satunya komponen Livewire di aplikasi. Komponen hanya mengurus tampilan dan keadaan form. Penyimpanan dilakukan `TransactionService::create`.

## Yang dibuat

- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `app/Http/Controllers/TransactionController.php` (baru method `create`)
- `resources/views/transactions/create.blade.php`
- Rute `transactions.create`
- `tests/Feature/TransactionFormTest.php`

## Langkah

### 1. Buat komponen

```bash
php artisan make:livewire TransactionForm --class
```

Pastikan hasilnya dua file: `app/Livewire/TransactionForm.php` dan `resources/views/livewire/transaction-form.blade.php`. Jika yang muncul satu file dengan awalan emoji petir, hapus file itu dan ulangi dengan `--class`.

### 2. Rute, controller, dan view halaman

Di dalam grup `auth`:

```php
Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
```

`TransactionController::create()` mengembalikan view `transactions.create`.

`resources/views/transactions/create.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Transaksi baru')

@section('content')
    <h1 class="h4 mb-3">Transaksi baru</h1>
    <livewire:transaction-form />
@endsection
```

Rute `transactions.show` baru dibuat di tugas 08. Sampai saat itu, setelah simpan komponen mengalihkan ke URL `/transactions/{id}` dengan `url()`.

### 3. Properti komponen

Semua properti `public`.

| Properti | Tipe | Nilai awal | Keterangan |
|---|---|---|---|
| `transactedAt` | string | waktu sekarang, format `Y-m-d\TH:i` | untuk `input type="datetime-local"` |
| `channel` | string | `'store'` | |
| `paymentStatus` | string | `'paid'` | |
| `note` | string | `''` | |
| `customerMode` | string | `'none'` | `'none'`, `'existing'`, atau `'new'` |
| `customerId` | ?int | `null` | |
| `customerLabel` | string | `''` | nama dan nomor pelanggan terpilih |
| `customerSearch` | string | `''` | |
| `customerResults` | array | `[]` | |
| `customerName` | string | `''` | untuk pelanggan baru |
| `customerPhone` | string | `''` | untuk pelanggan baru |
| `items` | array | satu baris kosong | lihat bentuk baris |

Bentuk satu baris di `items`:

```php
[
    'key' => (string) Str::uuid(),   // untuk wire:key
    'mode' => 'search',              // 'search', 'existing', atau 'new'
    'search' => '',
    'results' => [],                 // tiap hasil: ['id', 'name', 'unit', 'price']
    'product_id' => null,
    'name' => '',
    'unit' => '',
    'list_price' => null,            // harga daftar barang lama
    'cost_price' => null,            // hanya untuk barang baru
    'quantity' => '1',
    'unit_price' => null,
    'discount' => 0,
    'price_mode' => 'this_only',
]
```

Buat method privat `emptyRow(): array` yang mengembalikan bentuk di atas. Di `mount()`, isi `items` dengan satu `emptyRow()`.

### 4. Method komponen

`addRow()`
Menambah satu `emptyRow()` di akhir `items`.

`removeRow(int $index)`
Menghapus baris. Jika hanya tersisa satu baris, ganti dengan `emptyRow()`. Setelah menghapus, susun ulang indeks dengan `array_values`.

`updated($property, $value)`
Hook Livewire. Tangani dua kasus:

- Jika `$property` cocok dengan pola `items.{angka}.search`: jalankan pencarian barang untuk baris itu.
  - Jika panjang teks kurang dari 2 huruf, kosongkan `results` baris itu.
  - Jika 2 huruf atau lebih: `Product::selectable()->with('currentPrice')->where('name', 'like', '%'.$value.'%')->orderBy('name')->limit(8)->get()`. Ubah tiap barang menjadi array `['id', 'name', 'unit', 'price']`, dengan `price` dari `currentPrice?->sell_price`.
- Jika `$property` adalah `customerSearch`: cari pelanggan.
  - Kurang dari 2 huruf: kosongkan `customerResults`.
  - 2 huruf atau lebih: cari di `name` dengan `like`. Jika hasil `Phone::normalize($value)` tidak `null`, cari juga `phone` dengan `like` memakai nomor yang sudah dinormalkan. Batasi 8 hasil. Simpan sebagai array `['id', 'name', 'phone']`.

Cara mengenali pola: `preg_match('/^items\.(\d+)\.search$/', $property, $m)`.

`selectProduct(int $index, int $productId)`
Ambil barang dengan `Product::selectable()->with('currentPrice')->find($productId)`. Jika tidak ada, jangan lakukan apa pun. Jika ada, isi baris:

- `mode` = `'existing'`, `product_id`, `name`, `unit` dari barang.
- `list_price` = harga jual saat ini (boleh `null`).
- `unit_price` = sama dengan `list_price`.
- `price_mode` = `'this_only'`.
- Kosongkan `search` dan `results`.

`startNewProduct(int $index)`
Mengubah baris menjadi barang baru:

- `mode` = `'new'`.
- `name` = isi `search` baris itu yang sudah di-`trim`.
- `unit` = satuan pertama di `config('shop.units')`.
- `product_id`, `list_price`, `unit_price`, `cost_price` = `null`.
- Jangan mengosongkan `results`. Hasil pencarian tetap ditampilkan sebagai "barang yang mirip".

`resetRow(int $index)`
Mengembalikan baris ke `emptyRow()`.

`selectCustomer(int $customerId)`
Isi `customerMode` = `'existing'`, `customerId`, dan `customerLabel` (nama, lalu nomor HP jika ada). Kosongkan `customerSearch` dan `customerResults`.

`startNewCustomer()`
Isi `customerMode` = `'new'`. Jika `customerSearch` hanya berisi angka, spasi, tanda `+` dan `-`, pindahkan ke `customerPhone`. Selain itu pindahkan ke `customerName`. Kosongkan `customerSearch` dan `customerResults`.

`clearCustomer()`
Kembalikan semua properti pelanggan ke nilai awal (`customerMode` = `'none'`).

`rowSubtotal(array $row): int`
Jika `quantity` atau `unit_price` kosong atau bukan angka, kembalikan `0`. Selain itu kembalikan `TransactionService::calculateSubtotal(...)`. Jangan menulis ulang rumusnya.

`total(): int`
Jumlah `rowSubtotal` semua baris.

`save(TransactionService $service)`

1. Periksa baris sebelum memanggil service. Tambahkan galat dengan `$this->addError(kunci, pesan)`.
   - Buang semua baris ber-`mode` `'search'` yang `search`-nya kosong, lalu susun ulang indeks dengan `array_values`.
   - Jika masih ada baris ber-`mode` `'search'` (artinya `search` terisi tetapi barang belum dipilih): tambahkan galat `items.{i}.search` dengan pesan `Pilih barang dari daftar atau buat sebagai barang baru.` lalu berhenti.
   - Jika tidak ada baris tersisa: isi `items` dengan satu `emptyRow()`, tambahkan galat `items` dengan pesan `Pilih atau buat barang terlebih dahulu.` lalu berhenti.
2. Susun array data sesuai bagian "Bentuk data masukan" di `06-layanan-transaksi.md`:
   - `transacted_at`: `transactedAt` dengan huruf `T` diganti spasi.
   - `customer`: `null` jika mode `'none'`; `['id' => customerId]` jika `'existing'`; `['name' => ..., 'phone' => ...]` jika `'new'`.
   - Baris `mode` `'existing'` menjadi item dengan `product_id` dan `price_mode`.
   - Baris `mode` `'new'` menjadi item dengan `new_product` berisi `name`, `unit`, `cost_price`.
   - Ubah `unit_price`, `discount`, dan `cost_price` ke integer. Nilai kosong menjadi `null` untuk `unit_price` dan `cost_price`, dan `0` untuk `discount`.
3. Panggil `$service->create($data)`.
4. Jangan menangkap `ValidationException`. Livewire menampilkannya sendiri.
5. Jika berhasil: `session()->flash('success', 'Transaksi '.$transaction->number.' berhasil disimpan.')`, lalu `return $this->redirect(url('/transactions/'.$transaction->id));`

Catatan: tugas 16 (`16-form-draf-dan-ubah.md`) memindahkan isi `save` ke method privat `persist`, menambah `saveDraft`, dan membuat `mount` bisa menerima transaksi untuk diubah. Tugas 20 (`20-simpan-dan-transaksi-baru.md`) menambah `saveAndNew`, yang kembali ke `/transactions/create` setelah menyimpan.

Pemetaan galat dari service ke form. Service memakai kunci seperti `items.0.new_product.name`, sedangkan form memakai `items.0.name`. Di view, tampilkan galat dengan memeriksa beberapa kunci:

| Kolom di form | Kunci galat yang diperiksa |
|---|---|
| Nama barang baru | `items.{i}.new_product.name` |
| Satuan barang baru | `items.{i}.new_product.unit` |
| Harga modal | `items.{i}.new_product.cost_price` |
| Jumlah | `items.{i}.quantity` |
| Harga satuan | `items.{i}.unit_price` |
| Diskon | `items.{i}.discount` |
| Barang terpilih | `items.{i}.product_id` |
| Pencarian barang | `items.{i}.search` |
| Tanggal | `transacted_at` |
| Nama pelanggan baru | `customer.name` |
| Seluruh daftar barang | `items` |

`render()`
Mengembalikan `view('livewire.transaction-form')`. Kirim juga daftar satuan, pilihan `Channel::cases()`, dan `PaymentStatus::cases()`.

### 5. View komponen

Seluruh isi view berada di dalam satu elemen `<div>` pembungkus. Form memakai `<form wire:submit="save">`.

Susunan dari atas ke bawah:

**Kartu "Data transaksi"**

- Tanggal: `input type="datetime-local"` dengan `wire:model="transactedAt"`.
- Saluran: `select` dengan `wire:model="channel"`, pilihan dari `Channel::cases()` memakai `label()`.
- Status bayar: `select` dengan `wire:model="paymentStatus"`.

**Kartu "Pelanggan"**

- Mode `'none'`: kolom pencarian `wire:model.live.debounce.300ms="customerSearch"` dengan placeholder `Cari nama atau nomor HP (kosongkan untuk pembeli umum)`. Di bawahnya daftar hasil (`list-group`), tiap hasil adalah tombol `wire:click="selectCustomer({{ $c['id'] }})"`. Jika `customerSearch` berisi 2 huruf atau lebih, tampilkan tombol `+ Pelanggan baru` dengan `wire:click="startNewCustomer"`.
- Mode `'existing'`: tampilkan `customerLabel` dan tombol "Ganti" (`wire:click="clearCustomer"`).
- Mode `'new'`: kolom "Nama" (`wire:model="customerName"`), kolom "Nomor HP" (`wire:model="customerPhone"`, `type="tel"`), dan tombol "Batal" (`wire:click="clearCustomer"`).

**Daftar barang**

Ulangi `items` dengan `@foreach ($items as $i => $row)`. Tiap baris adalah satu `card` dengan `wire:key="row-{{ $row['key'] }}"`. Jangan memakai tabel.

Isi kartu menurut `mode`:

- `'search'`:
  - Kolom teks `wire:model.live.debounce.300ms="items.{{ $i }}.search"` dengan placeholder `Ketik nama barang`.
  - Daftar hasil (`list-group`). Tiap hasil adalah tombol `type="button"` dengan `wire:click="selectProduct({{ $i }}, {{ $r['id'] }})"` yang menampilkan nama, satuan, dan harga (`Format::rupiah`).
  - Jika `search` berisi 2 huruf atau lebih, tampilkan tombol `+ Tambah "{{ $row['search'] }}" sebagai barang baru` dengan `wire:click="startNewProduct({{ $i }})"`. Tombol ini berada DI BAWAH daftar hasil, supaya pengguna melihat barang yang mirip lebih dulu.

- `'existing'`:
  - Nama barang dan satuan sebagai teks, harga daftar sebagai teks kecil (`Harga daftar: Rp...`).
  - Tombol "Ganti barang" (`wire:click="resetRow({{ $i }})"`).
  - Kolom Jumlah, Harga satuan, Diskon.
  - Jika `list_price` tidak `null`, `unit_price` terisi, dan `(int) unit_price` berbeda dari `list_price`: tampilkan dua pilihan radio dengan `wire:model="items.{{ $i }}.price_mode"`:
  - (Catatan: tugas 18 mengganti pembanding ini dengan harga acuan, yaitu harga pelanggan jika ada, dan menambah pilihan ketiga "Jadikan harga pelanggan".)
    - nilai `this_only`, label `Hanya nota ini`
    - nilai `new_price`, label `Jadikan harga baru`

- `'new'`:
  - `badge` kuning bertulisan `Barang baru`.
  - Kolom Nama (`wire:model="items.{{ $i }}.name"`), Satuan (`select`), Harga modal (opsional).
  - Jika `results` baris itu tidak kosong, tampilkan `alert alert-warning` berjudul `Barang yang mirip:` berisi daftar hasil. Tiap hasil adalah tombol yang memanggil `selectProduct`.
  - Tombol "Batal" (`wire:click="resetRow({{ $i }})"`).
  - Kolom Jumlah, Harga satuan (label: `Harga jual`), Diskon.

Untuk kolom Jumlah, Harga satuan, dan Diskon pakai `wire:model.live.blur` agar subtotal dan total terhitung ulang saat kursor keluar dari kolom:

```blade
<input type="number" class="form-control @error("items.$i.quantity") is-invalid @enderror"
       wire:model.live.blur="items.{{ $i }}.quantity" min="0" step="0.001" inputmode="decimal">
@error("items.$i.quantity") <div class="invalid-feedback">{{ $message }}</div> @enderror
```

Harga satuan dan diskon memakai `step="1"` dan `inputmode="numeric"`.

Catatan: tugas 12 (`12-jumlah-desimal.md`) mengganti kolom Jumlah menjadi `type="text"` agar koma diterima. Tugas 13 (`13-titik-ribuan-harga.md`) mengganti kolom harga, harga modal, dan diskon menjadi `type="text"` dengan `data-rupiah`. Tugas 21 (`21-jumlah-kosong-saat-fokus.md`) menambah `data-clear-default` pada kolom Jumlah dan Diskon.

Di bagian bawah setiap kartu:

- Subtotal baris: `Format::rupiah($this->rowSubtotal($row))`.
- Tombol "Hapus baris" (`type="button"`, `wire:click="removeRow({{ $i }})"`).

Di bawah semua kartu: tombol `+ Tambah barang` (`type="button"`, `wire:click="addRow"`). Tampilkan galat `items` di sini dengan `@error('items')`.

**Kartu "Catatan"**

- `textarea` dengan `wire:model="note"`.

**Bilah bawah**

Satu `div` dengan kelas `sticky-actions bg-white border-top p-3`:

- Kiri: teks `Total` dan `Format::rupiah($this->total())` dengan huruf tebal.
- Kanan: tombol `type="submit"` bertulisan `Simpan transaksi`, kelas `btn btn-primary`, dengan `wire:loading.attr="disabled"`.

Catatan: tugas 16 mengubah bilah bawah ini menjadi dua baris dan menambah tombol "Simpan draf". Tugas 20 menambah baris ketiga dengan tombol "Simpan & transaksi baru".

Semua tombol selain tombol simpan wajib `type="button"`, agar tidak ikut mengirim form.

## Tes yang wajib ada

`tests/Feature/TransactionFormTest.php`, memakai `RefreshDatabase`, `actingAs`, dan `Livewire::test(TransactionForm::class)`:

1. Tamu yang membuka `/transactions/create` dialihkan ke `/login`.
2. Pengguna yang login bisa membuka `/transactions/create` dan halaman memuat komponen (`assertSeeLivewire(TransactionForm::class)`).
3. Komponen baru punya satu baris dengan `mode` `'search'`.
4. Mengisi `items.0.search` dengan `mi` saat ada barang "Mi Instan" mengisi `items.0.results` dengan satu hasil.
5. `selectProduct(0, id)` mengubah baris menjadi `mode` `'existing'` dengan `unit_price` sama dengan harga daftar.
6. `startNewProduct(0)` setelah mengisi pencarian `Gula` mengubah baris menjadi `mode` `'new'` dengan `name` `Gula`.
7. `addRow` menambah baris. `removeRow(0)` pada satu-satunya baris menyisakan satu baris kosong.
8. Skenario lengkap: pilih barang lama dengan harga berbeda mode `this_only`, tambah baris, buat barang baru, isi pelanggan baru, panggil `save`. Periksa: ada satu transaksi dengan total yang benar, barang baru berstatus `needs_review`, dan komponen mengalihkan ke `/transactions/{id}`.
9. Memilih `price_mode` `new_price` lalu `save` menambah satu baris harga untuk barang itu.
10. `save` dengan satu baris yang masih `mode` `'search'` menghasilkan galat pada `items` dan tidak membuat transaksi.
11. `save` dengan `quantity` kosong menghasilkan galat pada `items.0.quantity`.
12. `total()` untuk dua baris (2 × 115000 dan 1 × 18000) bernilai `248000`.

## Kriteria selesai

- [ ] `php artisan test` lulus semua.
- [ ] `npm run build` berhasil.
- [ ] Komponen berupa dua file class-based, bukan satu file.
- [ ] Di browser, lebar 375px: bisa mencari barang, membuat barang baru, mengubah harga dan memilih "Jadikan harga baru", menambah dan menghapus baris, memilih dan membuat pelanggan, lalu menyimpan.
- [ ] Total berubah setelah jumlah atau harga diubah dan kursor keluar dari kolom.
- [ ] Komponen tidak memanggil `Transaction::create`, `TransactionItem::create`, `Product::create`, atau `Customer::create`.
- [ ] Tidak ada galat di konsol browser.

## Jangan lakukan

- Jangan memakai komponen satu file (awalan emoji petir) atau multi-file.
- Jangan memakai `Route::livewire()`.
- Jangan menulis JavaScript sendiri. Jangan memasang Alpine. (Pengecualian yang diizinkan hanya `resources/js/rupiah.js` dari tugas 13, `resources/js/check-all.js` dari tugas 19, dan `resources/js/clear-default.js` dari tugas 21.)
- Jangan menyalin rumus subtotal atau aturan harga ke dalam komponen.
- Jangan membuat fitur simpan sebagai draf. Fitur itu dibuat nanti di tugas 15 dan 16.
- Jangan membuat komponen Livewire lain untuk baris atau pencarian. Cukup satu komponen.

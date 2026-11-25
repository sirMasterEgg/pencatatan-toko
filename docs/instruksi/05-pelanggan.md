# Tugas 05: Pelanggan

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 04 selesai.

## Tujuan

Pemilik bisa melihat, menambah, mengubah, dan menghapus pelanggan. Nomor HP selalu tersimpan dalam satu format dan tidak ada yang ganda.

## Yang dibuat

- `app/Services/CustomerService.php`
- `app/Http/Controllers/CustomerController.php`
- View di `resources/views/customers/`: `index`, `create`, `edit`, `show`, dan partial `_form`
- Rute pelanggan di dalam grup `auth`
- `tests/Feature/CustomerTest.php`

## Langkah

### 1. CustomerService

Service ini juga dipakai `TransactionService` di tugas 06.

```php
public function findOrCreate(string $name, ?string $phone = null, ?string $address = null): Customer
```

Perilaku:

1. Normalkan nomor dengan `Phone::normalize($phone)`.
2. Jika nomor tidak kosong dan ada pelanggan dengan nomor itu, kembalikan pelanggan tersebut. Jangan mengubah nama atau alamatnya.
3. Selain itu, buat pelanggan baru dengan nama yang sudah di-`trim`, nomor yang sudah dinormalkan (boleh `null`), dan alamat.
   (Catatan: sejak tugas 24, nama dan alamat disimpan huruf besar lewat `CustomerService::normalizeText`.)

### 2. Rute

Tambahkan di dalam grup `auth`:

```php
Route::resource('customers', CustomerController::class);
```

### 3. CustomerController

Aturan validasi untuk `store` dan `update`:

- `name`: wajib, string, maksimal 255.
- `phone`: opsional, string, maksimal 20, unik di `customers`. Pada `update`, abaikan pelanggan itu sendiri.
- `address`: opsional, string, maksimal 255.
- `note`: opsional, string, maksimal 255.

Sebelum validasi, ganti nilai `phone` di request dengan hasil `Phone::normalize`. Contoh:

```php
$request->merge(['phone' => Phone::normalize($request->input('phone'))]);
```

Dengan begitu aturan unik memeriksa nomor yang sudah dinormalkan. Pesan galat unik: `Nomor HP ini sudah dipakai pelanggan lain.`

`index`

- Parameter query `q`: cari di `name` dan `phone`. Jika `q` hanya berisi angka dan tanda baca, cari juga dengan hasil `Phone::normalize($q)`.
- Urutkan berdasarkan `name`. Pagination 20 per halaman dengan `withQueryString()`.

`store`

- Buat pelanggan langsung dengan `Customer::create`. Jangan memakai `findOrCreate` di sini, karena halaman ini harus menolak nomor ganda.
- Redirect ke `customers.show` dengan pesan `Pelanggan berhasil ditambahkan.`
- Catatan: tujuan redirect ini diganti oleh tugas 11 (`11-alur-tambah-barang-dan-pelanggan.md`). Teks pesannya tetap.

`show`

- Tampilkan data pelanggan.
- Tampilkan ringkasan dari transaksi berstatus `confirmed`: jumlah transaksi dan total belanja (`Format::rupiah`).
- Tampilkan 20 transaksi terakhir pelanggan (semua status): tanggal, nomor, total, `badge` status. Jika rute `transactions.show` sudah ada (`Route::has('transactions.show')`), nomor transaksi menjadi tautan ke sana.
- Sebelum tugas 06 selesai daftar ini kosong. Tampilkan `Belum ada transaksi.`

`update`

- Redirect ke `customers.show` dengan pesan `Pelanggan berhasil diperbarui.`

`destroy`

- Jika pelanggan punya transaksi (`$customer->transactions()->exists()`), jangan hapus. Redirect kembali dengan pesan `error`: `Pelanggan tidak bisa dihapus karena sudah punya transaksi.`
- Jika tidak punya, hapus dan redirect ke `customers.index` dengan pesan `Pelanggan berhasil dihapus.`

### 4. View

`index`

- Judul "Pelanggan" dan tombol "Tambah pelanggan".
- Form pencarian satu kolom `q` dengan placeholder `Cari nama atau nomor HP`.
- Daftar memakai `list-group`: nama dan nomor HP. Pelanggan tanpa nomor menampilkan `Tanpa nomor HP`.
- Catatan: sejak tugas 24 (`24-pelanggan-huruf-besar.md`), nama pelanggan di semua halaman ditampilkan dengan `$customer->displayName()` ("NAMA - ALAMAT").
- Jika kosong, tampilkan `Belum ada pelanggan.`

`_form`

- Kolom: Nama, Nomor HP (`type="tel"`, `inputmode="tel"`), Alamat, Catatan.
- Di bawah kolom Nomor HP beri teks bantuan: `Contoh: 0812-3456-7890. Disimpan dalam format 62812...`

`show`

- Tombol "Ubah".
- Tombol "Hapus" di dalam form `DELETE` dengan konfirmasi `onsubmit="return confirm('Hapus pelanggan ini?')"`.
- Catatan: tugas 17 (`17-harga-pelanggan.md`) menambah kartu "Harga khusus" dan tabel "Riwayat harga khusus" di halaman ini.

## Tes yang wajib ada

`tests/Feature/CustomerTest.php`, memakai `RefreshDatabase` dan `actingAs` pengguna:

1. Tamu yang membuka `/customers` dialihkan ke `/login`.
2. Menyimpan pelanggan dengan nomor `0812-0000-1234` menyimpan `6281200001234`.
3. Menyimpan pelanggan kedua dengan nomor `+62 812 0000 1234` ditolak dengan galat pada `phone`.
4. Dua pelanggan tanpa nomor HP boleh disimpan.
5. Mengubah pelanggan tanpa mengganti nomornya sendiri tidak memicu galat unik.
6. Pencarian `q=0812` menemukan pelanggan dengan nomor `6281200001234`.
7. Pelanggan tanpa transaksi bisa dihapus.
8. `CustomerService::findOrCreate('Bu Sari', '0812-0000-1234')` dipanggil dua kali hanya membuat satu pelanggan.
9. `CustomerService::findOrCreate('Nama Lain', '081200001234')` mengembalikan pelanggan yang sudah ada dan namanya tidak berubah.
10. `CustomerService::findOrCreate('Tanpa Nomor')` dipanggil dua kali membuat dua pelanggan.

## Kriteria selesai

- [ ] `php artisan test` lulus semua.
- [ ] `npm run build` berhasil.
- [ ] Di browser: bisa menambah, mencari, mengubah, dan menghapus pelanggan.
- [ ] Semua teks di layar berbahasa Indonesia.
- [ ] Halaman rapi di lebar layar 375px.

## Jangan lakukan

- Jangan membuat fitur gabung pelanggan.
- Jangan membuat impor atau ekspor pelanggan.
- Jangan memakai Livewire di halaman pelanggan.

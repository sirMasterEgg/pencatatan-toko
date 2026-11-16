# Tugas 10: Pemeriksaan Akhir

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 09 selesai.

## Tujuan

Memastikan seluruh tahap 1 berjalan dan konsisten. Tugas ini tidak menambah fitur. Yang dikerjakan hanya memeriksa, memperbaiki penyimpangan, dan menulis satu tes alur lengkap.

## Langkah

### 1. Jalankan pemeriksaan otomatis

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
php artisan route:list
```

Semua harus berhasil. `OWNER_PASSWORD` di `.env` harus terisi agar seeder jalan.

### 2. Cocokkan rute

Bandingkan hasil `php artisan route:list` dengan tabel "Daftar rute" di `00-aturan-umum.md`.

- Semua rute di tabel harus ada dengan nama yang sama.
- Tidak boleh ada rute aplikasi lain di luar tabel. Rute bawaan kerangka kerja seperti `/up`, `storage/{path}`, dan rute internal Livewire boleh ada.
- Semua rute selain `/login` harus memakai middleware `auth`.

### 3. Periksa kode terhadap aturan

Cari di seluruh folder `app/` dan `resources/views/`. Perbaiki setiap temuan.

| Yang dicari | Aturan |
|---|---|
| `Transaction::create`, `TransactionItem::create`, `->items()->create` | Hanya boleh ada di `TransactionService` |
| `ProductPrice::create`, `->prices()->create` | Hanya boleh ada di `ProductService` |
| `number_format(` | Hanya boleh ada di `App\Support\Format` |
| Kelas Tailwind seperti `flex-col`, `text-gray-`, `px-4`, `rounded-lg` | Tidak boleh ada |
| `whereDate(`, `DATE(` | Tidak boleh ada |
| `float`, `decimal` pada kolom uang di migrasi | Tidak boleh ada. `decimal` hanya untuk `quantity` |
| Teks antarmuka berbahasa Inggris seperti `Save`, `Cancel`, `Search`, `Delete` | Ganti ke bahasa Indonesia |
| File berawalan emoji petir di `resources/views` | Tidak boleh ada |
| `Route::livewire` | Tidak boleh ada |

### 4. Tulis tes alur lengkap

Buat `tests/Feature/FullFlowTest.php`, memakai `RefreshDatabase`. Satu tes yang menjalankan alur berikut dari awal sampai akhir:

1. Buat pengguna dan login dengan `actingAs`.
2. Lewat `POST /products`, buat barang "Mi Instan" satuan `dus` harga jual 118000 harga modal 105000.
3. Lewat `Livewire::test(TransactionForm::class)`:
   - Baris 1: pilih "Mi Instan", jumlah 2, harga satuan 115000, mode `this_only`.
   - Tambah baris. Baris 2: barang baru "Gula", satuan `kg`, jumlah 1, harga jual 18000.
   - Pelanggan baru: nama "Bu Sari", nomor `0812-0000-1234`.
   - Panggil `save`.
4. Periksa database:
   - Satu transaksi dengan `total` `248000` dan `status` `confirmed`.
   - Pelanggan dengan `phone` `6281200001234`.
   - Barang "Gula" berstatus `needs_review`.
   - Harga daftar "Mi Instan" tetap `118000`.
5. `GET /transactions/{id}` menampilkan `Rp248.000`, `Bu Sari`, dan `Harga daftar`.
6. `GET /products?status=needs_review` menampilkan `Gula`.
7. `POST /products/{id gula}/mark-reviewed`, lalu status "Gula" menjadi `active`.
8. `GET /reports/daily` menampilkan `Rp248.000`.
9. `POST /transactions/{id}/cancel` dengan alasan `Salah input`.
10. `GET /reports/daily` sekarang menampilkan `Rp0` pada kartu omzet, dan barang "Gula" masih ada di database.

### 5. Periksa tampilan

Buka setiap halaman di browser dengan lebar 375px dan periksa:

- Tidak ada gulir mendatar.
- Menu navigasi bisa dibuka dan ditutup.
- Semua tombol cukup besar untuk disentuh.
- Semua label, tombol, dan pesan berbahasa Indonesia.
- Semua uang tampil dalam format `Rp248.000`.
- Semua tanggal tampil dalam format `d/m/Y H:i`.

Halaman yang diperiksa: login, beranda, daftar barang, tambah barang, detail barang, ubah barang, daftar pelanggan, tambah pelanggan, detail pelanggan, transaksi baru, daftar transaksi, detail transaksi, laporan harian.

Jika Anda tidak bisa membuka browser, tulis di laporan bahwa langkah ini belum diperiksa. Jangan menulis "sudah diperiksa" tanpa benar-benar memeriksanya.

### 6. Tulis README proyek

Ganti isi `README.md` di akar proyek dengan panduan singkat berbahasa Indonesia:

- Nama dan tujuan aplikasi (dua kalimat).
- Kebutuhan: PHP 8.3, Composer, Node.js.
- Cara memasang: `composer install`, `npm install`, salin `.env.example` ke `.env`, `php artisan key:generate`, isi `OWNER_EMAIL` dan `OWNER_PASSWORD`, `php artisan migrate --seed`, `npm run build`.
- Cara menjalankan: `php artisan serve`.
- Cara menjalankan tes: `php artisan test`.
- Daftar fitur tahap 1.
- Daftar fitur yang belum ada (salin dari bagian "Lingkup" di `00-aturan-umum.md`).

## Kriteria selesai

- [ ] `php artisan migrate:fresh --seed` berhasil.
- [ ] `php artisan test` lulus semua, termasuk `FullFlowTest`.
- [ ] `npm run build` berhasil.
- [ ] Daftar rute cocok dengan `00-aturan-umum.md`.
- [ ] Tidak ada temuan tersisa dari tabel di langkah 3.
- [ ] `README.md` proyek sudah ditulis.
- [ ] Laporan menyebut dengan jujur bagian mana yang diperiksa di browser dan mana yang belum.

## Jangan lakukan

- Jangan menambah fitur baru.
- Jangan mengubah skema database.
- Jangan menghapus atau melemahkan tes agar lulus.
- Jangan merombak kode yang sudah benar hanya untuk merapikan gaya.

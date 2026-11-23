# Paket Instruksi: Aplikasi Pencatatan Barang dan Transaksi (Tahap 1)

File ini untuk Anda (pemilik proyek), bukan untuk model AI. Isinya cara memakai file instruksi lain di folder ini.

## Isi folder

| File | Isi | Untuk siapa |
|---|---|---|
| `README.md` | Cara pakai paket ini | Anda |
| `CLAUDE.md` | Ringkasan aturan, ditaruh di akar proyek | Model AI, dibaca otomatis oleh Claude Code |
| `00-aturan-umum.md` | Konteks, tech stack, konvensi, kamus istilah, larangan | Model AI, dibaca di setiap tugas |
| `01-setup-proyek.md` | Instal Laravel, Bootstrap, Livewire, layout | Model AI |
| `02-database-dan-model.md` | Migrasi, enum, model, factory | Model AI |
| `03-login.md` | Login satu pengguna | Model AI |
| `04-barang-dan-harga.md` | Halaman barang dan riwayat harga | Model AI |
| `05-pelanggan.md` | Halaman pelanggan | Model AI |
| `06-layanan-transaksi.md` | Logika simpan dan batal transaksi | Model AI |
| `07-form-transaksi.md` | Form transaksi dengan Livewire | Model AI |
| `08-daftar-dan-detail-transaksi.md` | Daftar, detail, batal, ubah status bayar | Model AI |
| `09-laporan-harian.md` | Beranda dan laporan harian | Model AI |
| `10-pemeriksaan-akhir.md` | Pemeriksaan menyeluruh | Model AI |
| `11-alur-tambah-barang-dan-pelanggan.md` | Tombol "Simpan" dan "Simpan dan tambah lagi" di form tambah barang dan pelanggan | Model AI |
| `12-jumlah-desimal.md` | Jumlah desimal untuk satuan timbang/ukur, koma diterima | Model AI |
| `13-titik-ribuan-harga.md` | Titik ribuan otomatis di semua kolom uang | Model AI |
| `14-nama-barang-huruf-besar.md` | Nama barang selalu huruf besar, termasuk data lama | Model AI |
| `15-draf-dan-ubah-transaksi.md` | Draf dan ubah transaksi: aturan, layanan, rute, halaman | Model AI |
| `16-form-draf-dan-ubah.md` | Draf dan ubah transaksi: form Livewire | Model AI |
| `17-harga-pelanggan.md` | Harga pelanggan: tabel, layanan, halaman pelanggan | Model AI |
| `18-harga-pelanggan-di-transaksi.md` | Harga pelanggan di form dan detail transaksi | Model AI |
| `19-ubah-harga-massal.md` | Ubah harga banyak barang sekaligus dengan pratinjau | Model AI |
| `20-simpan-dan-transaksi-baru.md` | Tombol "Simpan & transaksi baru" di form transaksi | Model AI |

## Cara pakai

1. Salin folder ini ke dalam proyek, misalnya ke `docs/instruksi/`.
2. Pindahkan `CLAUDE.md` ke akar proyek jika Anda memakai Claude Code. File itu ringkasan `00-aturan-umum.md` dan mengandaikan file tugas berada di `docs/instruksi/`. Untuk alat lain yang membaca file aturan otomatis, salin isinya ke file yang sesuai, misalnya `AGENTS.md`.
3. Kerjakan tugas berurutan dari 01 sampai 20. Satu tugas satu sesi percakapan baru. Tugas 01 sampai 10 membangun tahap 1. Tugas 11 dan seterusnya adalah perbaikan setelah tahap 1 selesai.
4. Di awal setiap sesi, berikan perintah seperti ini:

   ```
   Baca docs/instruksi/00-aturan-umum.md, lalu kerjakan docs/instruksi/04-barang-dan-harga.md.
   Kerjakan hanya tugas itu. Setelah selesai, jalankan semua perintah di bagian
   "Kriteria selesai" dan laporkan hasilnya.
   ```

5. Setelah model melapor selesai, periksa sendiri bagian "Kriteria selesai" di file tugas.
6. Jika semua terpenuhi, simpan dengan `git commit`, lalu lanjut ke tugas berikutnya.

## Saran agar model murah tidak melenceng

- Jangan gabungkan dua tugas dalam satu sesi.
- Jika model mulai mengerjakan hal di luar tugas, hentikan dan ulangi dengan sesi baru.
- Jika tes gagal dua kali berturut-turut pada hal yang sama, tempelkan pesan galat lengkapnya dan minta model memperbaiki hanya itu.
- Jika model mengusulkan paket tambahan atau mengubah skema, tolak. Semua yang dibutuhkan sudah tertulis.
- Tugas 06 dan 07 paling sulit. Pertimbangkan memakai model yang lebih kuat khusus untuk dua tugas ini.
- Dari tugas 12 sampai 16, yang paling berisiko adalah 15 dan 16, karena mengubah aturan bisnis inti. Kode dan tesnya sudah ditulis lengkap di file tugas, tetapi tetap periksa laporannya dengan teliti.
- Tugas 15 dan 16 saling bergantung. Setelah tugas 15 dan sebelum tugas 16 selesai, halaman "Ubah transaksi" masih menampilkan form kosong. Jangan dipakai untuk data sungguhan di antara keduanya.
- Tugas 17 dan 18 juga berpasangan. Setelah tugas 17, harga pelanggan sudah bisa diatur, tetapi form transaksi baru memakainya setelah tugas 18. Tugas 18 paling berisiko di rangkaian ini, karena mengubah form transaksi dan `TransactionService`.

## Keputusan yang sudah diambil

| Topik | Keputusan |
|---|---|
| Lingkup | Tahap 1: transaksi, barang dan harga saat transaksi, pelanggan, laporan harian |
| Framework | Laravel 13, PHP 8.3 atau lebih baru |
| Tampilan | Blade dan Bootstrap 5.3 lewat Vite, tanpa Sass |
| Interaksi form | Livewire 4, gaya class-based, hanya untuk form transaksi |
| Koneksi | Online saja. Fitur offline ditunda |
| Penamaan kode | Bahasa Inggris. Teks di layar bahasa Indonesia |
| Database | SQLite (bawaan Laravel). Bisa diganti MySQL lewat `.env` tanpa mengubah kode |
| Pengguna | Satu akun pemilik, tanpa pendaftaran |
| Tes | PHPUnit bawaan Laravel. Tes e2e Playwright di `tests/e2e/` ditambahkan di luar file tugas dan mulai dirawat lewat file tugas sejak tugas 11 |
| Setelah tambah barang atau pelanggan | "Simpan" kembali ke daftar, "Simpan dan tambah lagi" membuka form kosong lagi (tugas 11, menggantikan redirect ke halaman detail di tugas 04 dan 05) |
| Jumlah desimal | Hanya `kg`, `gram`, `liter`, `meter` (daftar di `config/shop.php`), maksimal 3 angka di belakang koma. Koma dan titik sama-sama tanda desimal (tugas 12) |
| Kolom uang | Titik ribuan otomatis saat diketik, di halaman barang dan form transaksi, termasuk diskon (tugas 13) |
| Nama barang | Disimpan huruf besar. Data lama diubah sekali lewat migrasi (tugas 14) |
| Draf | Bisa diubah kapan saja, tidak masuk laporan, bisa dihapus permanen. Barang baru dari draf yang dihapus tetap ada (tugas 15) |
| Ubah transaksi | Boleh selama status bayar "Belum bayar". "Sebagian" dan "Lunas" mengunci. Mengembalikan status ke "Belum bayar" membuka kunci lagi (tugas 15) |
| Harga pelanggan | Angka tetap per pelanggan per barang, disimpan sebagai riwayat. Diatur di halaman pelanggan atau lewat "Jadikan harga pelanggan" di form transaksi. Urutan: harga khusus nota, harga pelanggan, harga umum (tugas 17 dan 18) |
| Simpan & transaksi baru | Tombol ketiga di form transaksi baru dan saat mengubah draf. Menyimpan sebagai transaksi, lalu membuka form kosong (tanggal sekarang) dengan tautan ke transaksi tadi. Tidak tampil saat mengubah transaksi yang sudah dikonfirmasi (tugas 20) |
| Ubah harga massal | Naik atau turun dalam rupiah, harga modal opsional, bisa dijadwalkan, ada pratinjau. Harga pelanggan ikut berubah jika dicentang (bawaan tercentang) (tugas 19) |

## Yang sengaja belum dibuat

Input dari foto nota dan chat WhatsApp, antrean review, gabung barang, analisis margin, stok, mode kasir, ekspor CSV, dan mode offline. Skema database sudah menyiapkan kolom untuk sebagian fitur itu, tetapi layarnya belum ada.

## Perbedaan dari dokumentasi desain

- Nama tabel dan kolom memakai bahasa Inggris. Padanannya ada di kamus istilah dalam `00-aturan-umum.md`.
- Tabel lampiran belum dibuat karena baru dipakai di tahap 2.
- Ada satu tambahan: status bayar transaksi bisa diubah setelah transaksi dikonfirmasi, agar piutang bisa ditandai lunas.
- Sejak tugas 15, isi transaksi juga bisa diubah selama belum dibayar, dan transaksi bisa disimpan sebagai draf.

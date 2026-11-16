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

## Cara pakai

1. Salin folder ini ke dalam proyek, misalnya ke `docs/instruksi/`.
2. Pindahkan `CLAUDE.md` ke akar proyek jika Anda memakai Claude Code. File itu ringkasan `00-aturan-umum.md` dan mengandaikan file tugas berada di `docs/instruksi/`. Untuk alat lain yang membaca file aturan otomatis, salin isinya ke file yang sesuai, misalnya `AGENTS.md`.
3. Kerjakan tugas berurutan dari 01 sampai 10. Satu tugas satu sesi percakapan baru.
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
| Tes | PHPUnit bawaan Laravel |

## Yang sengaja belum dibuat

Input dari foto nota dan chat WhatsApp, antrean review, gabung barang, analisis margin, stok, mode kasir, ekspor CSV, dan mode offline. Skema database sudah menyiapkan kolom untuk sebagian fitur itu, tetapi layarnya belum ada.

## Perbedaan dari dokumentasi desain

- Nama tabel dan kolom memakai bahasa Inggris. Padanannya ada di kamus istilah dalam `00-aturan-umum.md`.
- Tabel lampiran belum dibuat karena baru dipakai di tahap 2.
- Ada satu tambahan: status bayar transaksi bisa diubah setelah transaksi dikonfirmasi, agar piutang bisa ditandai lunas. Isi transaksi lainnya tetap tidak bisa diubah.

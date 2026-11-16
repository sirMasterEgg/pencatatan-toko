# Tugas 01: Setup Proyek

Baca dulu: `00-aturan-umum.md`
Prasyarat: PHP 8.3 atau lebih baru, Composer, Node.js 22 atau lebih baru sudah terpasang.

## Tujuan

Proyek Laravel 13 yang berjalan dengan Bootstrap 5.3 dan Livewire 4, punya layout dasar, kelas bantu, dan pesan validasi berbahasa Indonesia.

## Langkah

### 1. Buat proyek

Jika folder proyek belum ada, jalankan:

```bash
composer create-project laravel/laravel toko
cd toko
```

Jika proyek sudah ada, lewati langkah ini. Jangan memilih starter kit apa pun.

Periksa versinya dengan `php artisan --version`. Hasilnya harus Laravel 13. Jika bukan, berhenti dan laporkan.

### 2. Atur lingkungan

Di file `.env` dan `.env.example`, ubah nilai berikut:

```
APP_NAME="Catatan Toko"
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
```

Di akhir kedua file itu, tambahkan:

```
OWNER_NAME="Pemilik Toko"
OWNER_EMAIL=pemilik@toko.test
OWNER_PASSWORD=
```

Biarkan `DB_CONNECTION=sqlite`.

Di `config/app.php`, ubah baris `'timezone' => 'UTC',` menjadi:

```php
'timezone' => 'Asia/Jakarta',
```

### 3. Ganti Tailwind dengan Bootstrap

Jalankan:

```bash
npm uninstall tailwindcss @tailwindcss/vite
npm install bootstrap @popperjs/core
```

Ganti seluruh isi `vite.config.js` dengan:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

Ganti seluruh isi `resources/css/app.css` dengan:

```css
@import 'bootstrap/dist/css/bootstrap.min.css';

.sticky-actions {
    position: sticky;
    bottom: 0;
    z-index: 1020;
}
```

Ganti seluruh isi `resources/js/app.js` dengan:

```js
import 'bootstrap';
```

### 4. Pasang Livewire

```bash
composer require livewire/livewire
php artisan livewire:config
```

Perintah kedua membuat `config/livewire.php`. Di file itu ubah dua nilai:

- Di dalam `make_command`, ubah `'type' => 'sfc'` menjadi `'type' => 'class'`.
- Ubah `'pagination_theme' => 'tailwind'` menjadi `'pagination_theme' => 'bootstrap'`.

Jangan membuat komponen Livewire di tugas ini.

### 5. Pagination Bootstrap

Di `app/Providers/AppServiceProvider.php`, di dalam method `boot()`, tambahkan:

```php
\Illuminate\Pagination\Paginator::useBootstrapFive();
```

### 6. Konfigurasi toko

Buat `config/shop.php`:

```php
<?php

return [
    'units' => ['pcs', 'dus', 'pak', 'lusin', 'kg', 'gram', 'liter', 'botol', 'sak', 'karton', 'meter', 'rol'],

    'owner' => [
        'name' => env('OWNER_NAME', 'Pemilik Toko'),
        'email' => env('OWNER_EMAIL', 'pemilik@toko.test'),
        'password' => env('OWNER_PASSWORD'),
    ],
];
```

### 7. Kelas bantu

Buat `app/Support/Format.php`:

```php
<?php

namespace App\Support;

class Format
{
    public static function rupiah(int|float|string|null $amount): string
    {
        return 'Rp'.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    public static function quantity(int|float|string|null $quantity): string
    {
        $text = number_format((float) ($quantity ?? 0), 3, ',', '.');

        return rtrim(rtrim($text, '0'), ',');
    }
}
```

Buat `app/Support/Phone.php`:

```php
<?php

namespace App\Support;

class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}
```

### 8. Layout

Buat `resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    @auth
        <nav class="navbar navbar-expand-md navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="{{ url('/transactions') }}">Transaksi</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ url('/products') }}">Barang</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ url('/customers') }}">Pelanggan</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ url('/reports/daily') }}">Laporan</a></li>
                    </ul>
                    <form method="POST" action="{{ url('/logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Keluar</button>
                    </form>
                </div>
            </div>
        </nav>
    @endauth

    <main class="container py-3">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
```

Menu memakai `url()` dengan sengaja, karena rutenya baru dibuat di tugas berikutnya. Jangan diganti dengan `route()`.

### 9. Halaman beranda sementara

Buat `resources/views/dashboard.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <h1 class="h4">Beranda</h1>
    <p class="text-muted">Halaman ini dilengkapi di tugas 09.</p>
@endsection
```

Di `routes/web.php`, ganti rute `/` yang ada dengan:

```php
Route::get('/', fn () => view('dashboard'))->name('dashboard');
```

Hapus `resources/views/welcome.blade.php`.

### 10. Pesan validasi bahasa Indonesia

Jalankan:

```bash
php artisan lang:publish
```

Salin folder `lang/en` menjadi `lang/id`. Di `lang/id/validation.php`, terjemahkan ke bahasa Indonesia pesan untuk aturan berikut. Pertahankan penanda seperti `:attribute`, `:max`, `:min`, `:value`, `:values`, `:date`.

`required`, `required_with`, `required_without`, `string`, `integer`, `numeric`, `array`, `date`, `email`, `enum`, `exists`, `in`, `unique`, `max` (semua bentuk), `min` (semua bentuk), `gt` (semua bentuk), `before_or_equal`.

Contoh: `'required' => 'Kolom :attribute wajib diisi.'`

Di bagian `attributes` pada file yang sama, isi:

```php
'attributes' => [
    'name' => 'nama',
    'unit' => 'satuan',
    'category' => 'kategori',
    'code' => 'kode',
    'sell_price' => 'harga jual',
    'cost_price' => 'harga modal',
    'phone' => 'nomor HP',
    'address' => 'alamat',
    'note' => 'catatan',
    'email' => 'email',
    'password' => 'kata sandi',
    'transacted_at' => 'tanggal transaksi',
    'channel' => 'saluran',
    'payment_status' => 'status bayar',
    'quantity' => 'jumlah',
    'unit_price' => 'harga satuan',
    'discount' => 'diskon',
],
```

Di `lang/id/auth.php`, terjemahkan `failed` menjadi `'Email atau kata sandi salah.'` dan `throttle` menjadi `'Terlalu banyak percobaan. Coba lagi dalam :seconds detik.'`

## Tes yang wajib ada

`tests/Unit/FormatTest.php`:

| Pemanggilan | Hasil |
|---|---|
| `Format::rupiah(248000)` | `Rp248.000` |
| `Format::rupiah(0)` | `Rp0` |
| `Format::rupiah(null)` | `Rp0` |
| `Format::quantity(2)` | `2` |
| `Format::quantity('0.500')` | `0,5` |
| `Format::quantity(1.25)` | `1,25` |
| `Format::quantity(1000)` | `1.000` |

`tests/Unit/PhoneTest.php`:

| Pemanggilan | Hasil |
|---|---|
| `Phone::normalize('0812-0000-1234')` | `6281200001234` |
| `Phone::normalize('+62 812 0000 1234')` | `6281200001234` |
| `Phone::normalize('81200001234')` | `6281200001234` |
| `Phone::normalize('')` | `null` |
| `Phone::normalize(null)` | `null` |

Tes bawaan `tests/Feature/ExampleTest.php` dibiarkan. Tes itu memeriksa `/` mengembalikan status 200.

## Kriteria selesai

- [ ] `php artisan --version` menampilkan Laravel 13.
- [ ] `npm run build` berhasil tanpa galat.
- [ ] `php artisan migrate` berhasil.
- [ ] `php artisan test` lulus semua.
- [ ] `package.json` tidak lagi memuat `tailwindcss`.
- [ ] `config/livewire.php` memuat `'type' => 'class'` dan `'pagination_theme' => 'bootstrap'`.
- [ ] Membuka `/` di browser menampilkan judul "Beranda" dengan gaya Bootstrap.

## Jangan lakukan

- Jangan membuat migrasi, model, atau controller di tugas ini.
- Jangan memasang Sass, jQuery, atau ikon.
- Jangan menjalankan `php artisan livewire:layout`.

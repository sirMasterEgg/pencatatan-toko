# Tugas 03: Login

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 dan 02 selesai.

## Tujuan

Aplikasi hanya bisa dipakai setelah login. Hanya ada satu akun, yaitu pemilik toko. Tidak ada pendaftaran.

## Yang dibuat

- `app/Http/Controllers/Auth/LoginController.php`
- `resources/views/auth/login.blade.php`
- `database/seeders/OwnerSeeder.php`
- Perubahan `routes/web.php` dan `database/seeders/DatabaseSeeder.php`
- `tests/Feature/LoginTest.php`

## Langkah

### 1. Seeder akun pemilik

`OwnerSeeder` membaca `config('shop.owner')`.

- Jika `password` kosong, lempar `RuntimeException` dengan pesan `Isi OWNER_PASSWORD di file .env sebelum menjalankan seeder.`
- Jika terisi, jalankan `User::updateOrCreate` dengan kunci `email`, lalu isi `name` dan `password`. Model `User` sudah meng-hash `password` secara otomatis lewat cast `hashed`. Jangan memanggil `Hash::make` lagi.

Di `DatabaseSeeder`, hapus pembuatan "Test User" dan ganti dengan `$this->call(OwnerSeeder::class);`.

### 2. Controller

`LoginController` punya tiga method.

`create()`: mengembalikan view `auth.login`.

`store(Request $request)`:

1. Validasi: `email` wajib dan berformat email; `password` wajib.
2. Panggil `Auth::attempt($credentials, $request->boolean('remember'))`.
3. Jika gagal, lempar `ValidationException::withMessages(['email' => __('auth.failed')])`.
4. Jika berhasil, panggil `$request->session()->regenerate()`, lalu `redirect()->intended(route('dashboard'))`.

`destroy(Request $request)`:

1. `Auth::logout()`.
2. `$request->session()->invalidate()`.
3. `$request->session()->regenerateToken()`.
4. Redirect ke `route('login')`.

### 3. Rute

Susun `routes/web.php` seperti ini. Semua rute di tugas berikutnya ditaruh di dalam grup `auth`.

```php
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => view('dashboard'))->name('dashboard');
});
```

### 4. View login

`resources/views/auth/login.blade.php` memakai `@extends('layouts.app')`.

- Satu `card` di tengah layar, lebar maksimal sekitar 400px.
- Judul: "Masuk".
- Kolom "Email" (`type="email"`, `name="email"`, isi ulang dengan `old('email')`).
- Kolom "Kata sandi" (`type="password"`, `name="password"`).
- Kotak centang "Ingat saya" (`name="remember"`).
- Tombol "Masuk" selebar kartu.
- Pesan galat ditampilkan di bawah kolom terkait dengan `@error` dan kelas `is-invalid` serta `invalid-feedback`.

Layout sudah menyembunyikan menu untuk tamu lewat `@auth`.

### 5. Perbarui tes bawaan

Hapus `tests/Feature/ExampleTest.php`. Penggantinya ada di `LoginTest`.

## Tes yang wajib ada

`tests/Feature/LoginTest.php`, memakai `RefreshDatabase`:

1. Tamu yang membuka `/` dialihkan ke `/login`.
2. Halaman `/login` bisa dibuka tamu dan memuat teks "Masuk".
3. Login dengan email dan kata sandi benar mengalihkan ke `/` dan pengguna terautentikasi.
4. Login dengan kata sandi salah kembali dengan galat pada `email` dan pengguna tetap tamu.
5. Pengguna yang sudah login bisa membuka `/` dengan status 200.
6. Logout membuat pengguna menjadi tamu dan mengalihkan ke `/login`.
7. `OwnerSeeder` membuat satu pengguna jika `shop.owner.password` diisi (atur dengan `config()` di dalam tes).
8. `OwnerSeeder` melempar `RuntimeException` jika `shop.owner.password` kosong.

## Kriteria selesai

- [ ] `php artisan test` lulus semua.
- [ ] `npm run build` berhasil.
- [ ] Dengan `OWNER_PASSWORD` terisi di `.env`, `php artisan migrate:fresh --seed` berhasil.
- [ ] Login di browser dengan akun pemilik berhasil dan menu navigasi muncul.
- [ ] Tombol "Keluar" berfungsi.
- [ ] Tidak ada rute pendaftaran atau lupa kata sandi di `php artisan route:list`.

## Jangan lakukan

- Jangan memasang Breeze, Jetstream, Fortify, atau starter kit.
- Jangan membuat halaman pendaftaran, lupa kata sandi, atau profil.
- Jangan menulis kata sandi di dalam kode atau seeder.

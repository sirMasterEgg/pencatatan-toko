# Tugas 25: Tanggal Transaksi Berjalan, Bisa Diubah Manual

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 24 selesai.

## Tujuan

Kolom Tanggal di form transaksi baru sekarang diisi waktu saat form dibuka, lalu diam. Jika form dibuka lama sebelum disimpan, waktu yang tersimpan tertinggal.

Setelah tugas ini:

| Keadaan | Perilaku |
|---|---|
| Form transaksi baru | Kolom Tanggal ikut berjalan mengikuti jam (mode otomatis). Di bawahnya tertulis `Mengikuti waktu sekarang. Ubah jika transaksi terjadi di waktu lain.` |
| Disimpan dalam mode otomatis | Server memakai waktu saat tombol simpan ditekan, bukan angka di layar |
| Pengguna mengubah kolom Tanggal | Mode otomatis berhenti. Kolom tidak berjalan lagi, dan yang disimpan adalah tanggal isian. Muncul tombol `Pakai waktu sekarang` |
| Tombol `Pakai waktu sekarang` | Kembali ke mode otomatis |
| Mengubah draf atau transaksi lama | Tidak berjalan. Tanggal tetap tanggal asli transaksi. Tombol `Pakai waktu sekarang` tersedia jika ingin memperbaruinya |

Validasi lama tetap berlaku: tanggal di masa depan ditolak (`Tanggal transaksi tidak boleh di masa depan.`).

## Cara kerja

Ada dua bagian yang bekerja bersama:

1. **Server (Livewire).** Properti baru `$autoTime`. Selama `true`, setiap permintaan Livewire memperbarui `$transactedAt` ke waktu sekarang (`hydrate()`), dan saat simpan, server memakai `now()`. Jika pengguna mengubah kolom Tanggal, `updatedTransactedAt()` mengubah `$autoTime` menjadi `false`.
2. **Peramban (JavaScript).** Selama mode otomatis, kolom diberi atribut `data-live-clock`. Skrip kecil `live-clock.js` memperbarui isi kolom setiap detik mengikuti jam perangkat, agar tampilan berjalan walau tidak ada permintaan ke server. Skrip ini hanya mengubah tampilan. Yang disimpan tetap waktu server.

Kolom Tanggal memakai `wire:model.live`, bukan `wire:model`, agar perubahan manual langsung sampai ke server, sehingga atribut `data-live-clock` segera hilang dan jam berhenti berjalan.

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 07, kolom Tanggal | `wire:model="transactedAt"`, diisi saat `mount` | `wire:model.live`, mode otomatis dengan `data-live-clock` |
| 07 dan 16, data simpan | `transacted_at` dari `$transactedAt` | Dari `now()` jika `$autoTime`, selain itu dari `$transactedAt` |
| 16, `fillFromTransaction` | Mengisi `$transactedAt` dari transaksi | Juga `$autoTime = false` |
| 13, 19, 21, JavaScript | `rupiah.js`, `check-all.js`, `clear-default.js` | Ditambah `resources/js/live-clock.js`, tanpa paket baru |

`TransactionService` tidak berubah. Tidak ada rute, kolom, tabel, atau paket baru.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `resources/js/live-clock.js`
- `tests/Feature/TransactionTimeTest.php`
- `tests/e2e/tanggal-berjalan.spec.js`

## Yang diubah

- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `resources/js/app.js`

## Langkah

### 1. Komponen TransactionForm

Tambahkan properti ini tepat di bawah `public string $transactedAt = '';`:

```php
/**
 * true: tanggal mengikuti waktu sekarang dan disimpan dengan waktu saat simpan.
 * false: tanggal diisi manual, atau tanggal asli transaksi yang sedang diubah.
 */
public bool $autoTime = true;
```

Tambahkan tiga method ini tepat di bawah `mount`:

```php
/**
 * Setiap permintaan Livewire, tanggal otomatis diperbarui ke waktu sekarang.
 */
public function hydrate(): void
{
    if ($this->autoTime) {
        $this->transactedAt = now()->format('Y-m-d\TH:i');
    }
}

/**
 * Dipanggil Livewire saat pengguna mengubah kolom tanggal sendiri.
 */
public function updatedTransactedAt(): void
{
    $this->autoTime = false;
}

public function useCurrentTime(): void
{
    $this->autoTime = true;
    $this->transactedAt = now()->format('Y-m-d\TH:i');
}
```

Di `fillFromTransaction`, tambahkan satu baris tepat di bawah `$this->originalStatus = ...;`:

```php
$this->autoTime = false;
```

Di `persist`, ubah baris `transacted_at` di array `$data` menjadi:

```php
'transacted_at' => $this->autoTime ? now()->format('Y-m-d H:i:s') : str_replace('T', ' ', $this->transactedAt),
```

Penjelasan:

- `hydrate()` dijalankan Livewire di awal setiap permintaan, sebelum isian pengguna diterapkan. Jadi jika pengguna mengubah tanggal, isiannya tetap menang, lalu `updatedTransactedAt()` mematikan mode otomatis.
- `mount` tidak perlu diubah. Transaksi baru mulai dengan `$autoTime = true` dari nilai bawaan properti.

### 2. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, ganti kolom Tanggal dan pesan galatnya (dari `<input type="datetime-local" ...>` sampai `@error('transacted_at') ... @enderror`) dengan:

```blade
                    <input type="datetime-local" id="transactedAt" class="form-control @error('transacted_at') is-invalid @enderror" wire:model.live="transactedAt" @if ($autoTime) data-live-clock @endif>
                    @error('transacted_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @if ($autoTime)
                        <div class="form-text">Mengikuti waktu sekarang. Ubah jika transaksi terjadi di waktu lain.</div>
                    @else
                        <button type="button" class="btn btn-link btn-sm px-0" wire:click="useCurrentTime">Pakai waktu sekarang</button>
                    @endif
```

Label kolom tetap "Tanggal".

### 3. JavaScript

Buat `resources/js/live-clock.js` dengan isi persis ini:

```js
// Kolom datetime-local bertanda data-live-clock ikut berjalan mengikuti jam perangkat.
// Atribut itu hanya dipasang server (TransactionForm) selama waktu masih otomatis.
// Kolom yang sedang difokus tidak diubah, agar tidak mengganggu pengguna yang sedang mengetik.
const pad = (number) => String(number).padStart(2, '0');

const currentMinute = () => {
    const now = new Date();

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
};

setInterval(() => {
    document.querySelectorAll('input[data-live-clock]').forEach((input) => {
        const value = currentMinute();

        if (document.activeElement !== input && input.value !== value) {
            input.value = value;
        }
    });
}, 1000);
```

Isi `resources/js/app.js` menjadi:

```js
import 'bootstrap';
import './rupiah';
import './check-all';
import './clear-default';
import './live-clock';
```

Penjelasan:

- Skrip memakai jam perangkat. Perangkat pemilik diharapkan memakai zona waktu yang sama dengan aplikasi (`Asia/Jakarta`). Jika berbeda, hanya tampilan yang bergeser. Waktu yang disimpan tetap waktu server.
- Kolom yang sedang difokus tidak diubah, agar tidak mengganggu pengguna yang sedang memilih tanggal.
- Skrip tidak mengirim event apa pun, jadi Livewire tidak menganggapnya sebagai perubahan manual.

## Tes yang wajib ada

Buat `tests/Feature/TransactionTimeTest.php` dengan isi persis ini:

```php
<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionTimeTest extends TestCase
{
    use RefreshDatabase;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setTime(10, 0));

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);
    }

    public function test_new_form_uses_automatic_time(): void
    {
        Livewire::test(TransactionForm::class)
            ->assertSet('autoTime', true)
            ->assertSet('transactedAt', now()->format('Y-m-d\TH:i'))
            ->assertSeeHtml('data-live-clock')
            ->assertSee('Mengikuti waktu sekarang.')
            ->assertDontSee('Pakai waktu sekarang');
    }

    public function test_automatic_time_is_refreshed_on_every_request(): void
    {
        $component = Livewire::test(TransactionForm::class);

        $this->travel(7)->minutes();
        $component->call('addRow')->assertSet('transactedAt', now()->format('Y-m-d\TH:i'));
    }

    public function test_save_with_automatic_time_uses_time_of_saving(): void
    {
        $component = Livewire::test(TransactionForm::class)->call('selectProduct', 0, $this->gula->id);

        $this->travel(25)->minutes();
        $component->call('save')->assertHasNoErrors();

        $this->assertSame('10:25', Transaction::firstOrFail()->transacted_at->format('H:i'));
    }

    public function test_manual_time_stops_automatic_time_and_is_saved(): void
    {
        $manual = now()->subDay()->setTime(8, 15)->format('Y-m-d\TH:i');

        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('transactedAt', $manual)
            ->assertSet('autoTime', false)
            ->assertDontSeeHtml('data-live-clock')
            ->assertSee('Pakai waktu sekarang');

        $this->travel(30)->minutes();
        $component->call('addRow')->assertSet('transactedAt', $manual);
        $component->call('save')->assertHasNoErrors();

        $this->assertSame(str_replace('T', ' ', $manual), Transaction::firstOrFail()->transacted_at->format('Y-m-d H:i'));
    }

    public function test_use_current_time_returns_to_automatic_time(): void
    {
        Livewire::test(TransactionForm::class)
            ->set('transactedAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('useCurrentTime')
            ->assertSet('autoTime', true)
            ->assertSet('transactedAt', now()->format('Y-m-d\TH:i'))
            ->assertSeeHtml('data-live-clock');
    }

    public function test_future_manual_time_is_still_rejected(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('transactedAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('transacted_at');

        $this->assertSame(0, Transaction::count());
    }

    public function test_edit_mode_keeps_original_time(): void
    {
        $draft = app(TransactionService::class)->create([
            'transacted_at' => now()->subDays(2)->setTime(9, 30)->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0]],
        ], TransactionStatus::Draft);

        $component = Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->assertSet('autoTime', false)
            ->assertSee('Pakai waktu sekarang')
            ->assertDontSeeHtml('data-live-clock');

        $this->travel(1)->hours();
        $component->call('save')->assertHasNoErrors();

        $this->assertSame('09:30', $draft->fresh()->transacted_at->format('H:i'));
    }

    public function test_edit_mode_can_switch_to_current_time(): void
    {
        $draft = app(TransactionService::class)->create([
            'transacted_at' => now()->subDays(2)->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0]],
        ], TransactionStatus::Draft);

        Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->call('useCurrentTime')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($draft->fresh()->transacted_at->isToday());
    }
}
```

Buat `tests/e2e/tanggal-berjalan.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

test.use({ timezoneId: 'Asia/Jakarta' });

const asInputValue = (date) => {
    const pad = (n) => String(n).padStart(2, '0');
    const local = new Date(date.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));

    return `${local.getFullYear()}-${pad(local.getMonth() + 1)}-${pad(local.getDate())}T${pad(local.getHours())}:${pad(local.getMinutes())}`;
};

test('tanggal transaksi berjalan sendiri sampai diubah manual', async ({ page }) => {
    const start = new Date();
    start.setSeconds(0, 0);
    await page.clock.setFixedTime(start);

    await page.goto('/transactions/create');
    const field = page.getByLabel('Tanggal');
    await expect(page.getByText('Mengikuti waktu sekarang.')).toBeVisible();

    // Jam perangkat maju 5 menit: kolom ikut berjalan.
    const later = new Date(start.getTime() + 5 * 60 * 1000);
    await page.clock.setFixedTime(later);
    await expect(field).toHaveValue(asInputValue(later));

    // Diubah manual: berhenti berjalan dan muncul tombol "Pakai waktu sekarang".
    const manual = new Date(start.getTime() - 26 * 60 * 60 * 1000);
    await field.fill(asInputValue(manual));
    await expect(page.getByRole('button', { name: 'Pakai waktu sekarang' })).toBeVisible();
    await page.clock.setFixedTime(new Date(start.getTime() + 10 * 60 * 1000));
    await page.waitForTimeout(1500);
    await expect(field).toHaveValue(asInputValue(manual));

    // Kembali ke waktu sekarang: berjalan lagi.
    await page.getByRole('button', { name: 'Pakai waktu sekarang' }).click();
    await expect(page.getByText('Mengikuti waktu sekarang.')).toBeVisible();
    const evenLater = new Date(start.getTime() + 15 * 60 * 1000);
    await page.clock.setFixedTime(evenLater);
    await expect(field).toHaveValue(asInputValue(evenLater));
});

test('tanggal manual tersimpan sesuai isian', async ({ page }) => {
    const name = `Jam ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').fill('1000');

    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
    yesterday.setMinutes(5);
    await page.getByLabel('Tanggal').fill(asInputValue(yesterday));
    await expect(page.getByRole('button', { name: 'Pakai waktu sekarang' })).toBeVisible();

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);

    const [date, time] = asInputValue(yesterday).split('T');
    const [y, m, d] = date.split('-');
    await expect(page.getByText(`${d}/${m}/${y} ${time}`)).toBeVisible();
});
```

Catatan untuk tes e2e:

- `page.clock.setFixedTime(...)` membuat jam peramban bisa diatur, sehingga tes tidak bergantung pada pergantian menit sungguhan.
- `test.use({ timezoneId: 'Asia/Jakarta' })` menyamakan zona waktu peramban dengan aplikasi.

Tes lama tidak perlu diubah dan harus tetap lulus. Tes lama yang mengisi tanggal dengan `->set('transactedAt', ...)` otomatis masuk mode manual, jadi hasilnya sama seperti sebelumnya.

## Kriteria selesai

Jalankan sesuai urutan ini. `npm run build` wajib diulang karena JavaScript berubah.

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 8.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2.
- [ ] Di browser HP: buka "Transaksi baru", tunggu satu-dua menit, kolom Tanggal ikut berganti menit.
- [ ] Mengubah tanggal manual menghentikan jam dan memunculkan "Pakai waktu sekarang". Tanggal itu yang tersimpan.
- [ ] "Pakai waktu sekarang" membuat jam berjalan lagi.
- [ ] Mengubah draf tetap memakai tanggal asli draf.
- [ ] Tidak ada galat di konsol peramban.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan memakai `wire:poll` untuk menjalankan jam. Itu mengirim permintaan ke server terus-menerus.
- Jangan menyimpan waktu dari peramban. Waktu otomatis selalu dari `now()` di server.
- Jangan mengubah `TransactionService` atau aturan validasi tanggal.
- Jangan memakai Alpine atau paket JavaScript.
- Jangan menghapus atau melemahkan tes agar lulus.

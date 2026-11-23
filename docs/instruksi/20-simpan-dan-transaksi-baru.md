# Tugas 20: Tombol "Simpan & transaksi baru"

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 19 selesai.

## Tujuan

Saat toko ramai, pemilik mencatat banyak transaksi berturut-turut. Saat ini setelah "Simpan transaksi", aplikasi membuka halaman detail transaksi, sehingga pemilik harus kembali ke "Transaksi baru" untuk mencatat pembeli berikutnya.

Setelah tugas ini, form transaksi punya tombol ketiga "Simpan & transaksi baru":

| Hal | Aturan |
|---|---|
| Yang disimpan | Transaksi dengan status `confirmed`, sama persis dengan "Simpan transaksi" |
| Setelah tersimpan | Membuka lagi `/transactions/create` dengan form kosong: tanggal = sekarang, saluran Toko, status bayar Lunas, tanpa pelanggan dan barang |
| Pesan | Pesan sukses yang sama dengan "Simpan transaksi", misalnya `Transaksi TRX-20261123-011 berhasil disimpan.` |
| Tautan | Di bawah judul "Transaksi baru" tampil tautan `Lihat TRX-...` ke transaksi yang baru disimpan |
| Tampil di | Form transaksi baru, dan saat mengubah DRAF (draf dijadikan transaksi, lalu form kosong dibuka) |
| Tidak tampil di | Saat mengubah transaksi yang sudah `confirmed` (tombolnya tetap hanya "Simpan perubahan") |
| Jika ada galat | Tidak ada yang tersimpan, tetap di form yang sama dengan pesan galat, seperti "Simpan transaksi" |

Menekan Enter di kolom teks tetap berarti "Simpan transaksi", bukan tombol baru ini.

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 07 dan 16, setelah simpan | Selalu ke halaman detail transaksi | Ke detail, kecuali lewat "Simpan & transaksi baru" yang kembali ke `/transactions/create` |
| 16, bilah bawah form | Total, lalu "Simpan draf" dan "Simpan transaksi" (atau "Simpan perubahan") | Ditambah baris ketiga: tombol "Simpan & transaksi baru", kecuali saat mengubah transaksi `confirmed` |
| 16, `persist` | Parameter `$service` dan `$status` | Ditambah parameter `bool $startNew = false` |
| 07, halaman `transactions/create` | Judul dan komponen | Ditambah tautan ke transaksi terakhir jika ada |

Tidak ada rute, kolom, tabel, paket, atau JavaScript baru. `TransactionService` tidak diubah.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `tests/Feature/TransactionSaveAndNewTest.php`
- `tests/e2e/simpan-dan-baru.spec.js`

## Yang diubah

- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `resources/views/transactions/create.blade.php`
- `README.md` di akar proyek

## Langkah

### 1. Komponen TransactionForm

Tambahkan method publik ini tepat di bawah `saveDraft`:

```php
public function saveAndNew(TransactionService $service)
{
    return $this->persist($service, TransactionStatus::Confirmed, startNew: true);
}
```

Ubah tanda tangan `persist` menjadi:

```php
private function persist(TransactionService $service, TransactionStatus $status, bool $startNew = false)
```

Di akhir `persist`, ganti dua baris terakhir:

```php
session()->flash('success', $message);

return $this->redirect(route('transactions.show', $transaction));
```

dengan:

```php
session()->flash('success', $message);

if ($startNew) {
    session()->flash('last_transaction', ['id' => $transaction->id, 'number' => $transaction->number]);

    return $this->redirect(route('transactions.create'));
}

return $this->redirect(route('transactions.show', $transaction));
```

Aturan:

- Pesan `$message` tidak berubah. Karena status `Confirmed`, pesannya `Transaksi ... berhasil disimpan.` untuk transaksi baru maupun draf yang dijadikan transaksi.
- Jangan mengosongkan properti form secara manual. Redirect ke `/transactions/create` membuat komponen baru dengan `mount()` biasa, jadi form otomatis kosong dan tanggalnya sekarang.
- Jangan menyimpan model `Transaction` di sesi. Simpan hanya `id` dan `number`.

### 2. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, di dalam `<div class="sticky-actions ...">`, tepat setelah `</div>` penutup baris tombol (`<div class="d-flex gap-2">`), tambahkan:

```blade
@if ($originalStatus !== 'confirmed')
    <button type="button" class="btn btn-outline-primary w-100 mt-2" wire:click="saveAndNew" wire:loading.attr="disabled">Simpan &amp; transaksi baru</button>
@endif
```

Hasil akhir bilah bawah menjadi:

```blade
<div class="sticky-actions bg-white border-top p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span>Total</span>
        <strong>{{ \App\Support\Format::rupiah($this->total()) }}</strong>
    </div>
    <div class="d-flex gap-2">
        @if ($originalStatus === 'confirmed')
            <button type="submit" class="btn btn-primary flex-fill" wire:loading.attr="disabled">Simpan perubahan</button>
        @else
            <button type="button" class="btn btn-outline-secondary flex-fill" wire:click="saveDraft" wire:loading.attr="disabled">Simpan draf</button>
            <button type="submit" class="btn btn-primary flex-fill" wire:loading.attr="disabled">Simpan transaksi</button>
        @endif
    </div>
    @if ($originalStatus !== 'confirmed')
        <button type="button" class="btn btn-outline-primary w-100 mt-2" wire:click="saveAndNew" wire:loading.attr="disabled">Simpan &amp; transaksi baru</button>
    @endif
</div>
```

Aturan:

- Tombol baru wajib `type="button"` dengan `wire:click`. Jika `type="submit"`, Enter dan tombol "Simpan transaksi" ikut kacau.
- Tulis `&amp;` di Blade. Di layar tampil `Simpan & transaksi baru`.
- Label "Simpan draf" dan "Simpan transaksi" tidak berubah. Tes lama mencarinya.

### 3. View halaman transaksi baru

Ganti isi `resources/views/transactions/create.blade.php` dengan:

```blade
@extends('layouts.app')

@section('title', 'Transaksi baru')

@section('content')
    <h1 class="h4 mb-3">Transaksi baru</h1>
    @if (session('last_transaction'))
        <p class="mb-3">
            <a href="{{ route('transactions.show', session('last_transaction')['id']) }}">Lihat {{ session('last_transaction')['number'] }}</a>
        </p>
    @endif
    <livewire:transaction-form />
@endsection
```

Pesan sukses hijau sudah ditampilkan layout (`session('success')`) sejak tugas 01. Jangan menampilkannya dua kali.

### 4. README proyek

Di `README.md` akar proyek:

- Pada baris `Status`, ganti `Tugas 01 sampai 19` menjadi `Tugas 01 sampai 20`.
- Di bagian "Fitur tahap 1", tambahkan butir ini di paling bawah:

  ```
  - **Simpan & transaksi baru**: menyimpan transaksi lalu langsung membuka form kosong untuk pembeli berikutnya, dengan tautan ke transaksi yang baru disimpan
  ```

Bagian lain README tidak diubah.

## Tes yang wajib ada

Buat `tests/Feature/TransactionSaveAndNewTest.php` dengan isi persis ini:

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

class TransactionSaveAndNewTest extends TestCase
{
    use RefreshDatabase;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);
    }

    private function draft(): Transaction
    {
        return app(TransactionService::class)->create([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0]],
        ], TransactionStatus::Draft);
    }

    public function test_save_and_new_saves_confirmed_transaction_and_opens_new_form(): void
    {
        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '2')
            ->call('saveAndNew')
            ->assertHasNoErrors()
            ->assertRedirect('/transactions/create');

        $transaction = Transaction::firstOrFail();
        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
        $this->assertSame(36000, $transaction->total);
        $this->assertSame('Transaksi '.$transaction->number.' berhasil disimpan.', session('success'));
        $this->assertSame(['id' => $transaction->id, 'number' => $transaction->number], session('last_transaction'));
    }

    public function test_save_and_new_with_errors_saves_nothing_and_stays(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '')
            ->call('saveAndNew')
            ->assertHasErrors('items.0.quantity')
            ->assertNoRedirect();

        $this->assertSame(0, Transaction::count());
    }

    public function test_save_and_new_confirms_a_draft(): void
    {
        $draft = $this->draft();

        Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->assertSee('Simpan &amp; transaksi baru', false)
            ->call('saveAndNew')
            ->assertHasNoErrors()
            ->assertRedirect('/transactions/create');

        $this->assertSame(TransactionStatus::Confirmed, $draft->fresh()->status);
        $this->assertSame(1, Transaction::count());
    }

    public function test_button_is_shown_on_new_form_but_not_when_editing_confirmed_transaction(): void
    {
        Livewire::test(TransactionForm::class)->assertSee('Simpan &amp; transaksi baru', false);

        $draft = $this->draft();
        $draft->update(['status' => TransactionStatus::Confirmed, 'payment_status' => 'unpaid']);

        Livewire::test(TransactionForm::class, ['transaction' => $draft->fresh()])
            ->assertDontSee('Simpan &amp; transaksi baru', false);
    }

    public function test_save_and_new_on_confirmed_edit_only_updates_same_transaction(): void
    {
        $draft = $this->draft();
        $draft->update(['status' => TransactionStatus::Confirmed, 'payment_status' => 'unpaid']);

        Livewire::test(TransactionForm::class, ['transaction' => $draft->fresh()])
            ->set('items.0.quantity', '3')
            ->call('saveAndNew')
            ->assertRedirect('/transactions/create');

        // Tombolnya tidak tampil di sini. Jika aksinya tetap dipanggil, hanya transaksi yang sama yang diperbarui.
        $this->assertSame(1, Transaction::count());
        $this->assertSame(54000, $draft->fresh()->total);
    }

    public function test_create_page_shows_link_to_last_saved_transaction(): void
    {
        $transaction = $this->draft();

        $this->withSession(['last_transaction' => ['id' => $transaction->id, 'number' => $transaction->number]])
            ->get('/transactions/create')
            ->assertOk()
            ->assertSee('Lihat '.$transaction->number)
            ->assertSee(route('transactions.show', $transaction), false);
    }

    public function test_create_page_without_last_transaction_has_no_link(): void
    {
        $this->get('/transactions/create')->assertOk()->assertDontSee('Lihat TRX-');
    }
}
```

Buat `tests/e2e/simpan-dan-baru.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function fillNewItem(page, name, price) {
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').fill(price);
    await page.getByLabel('Diskon').click();
}

test('simpan & transaksi baru: menyimpan lalu membuka form kosong', async ({ page }) => {
    const suffix = Date.now();

    await page.goto('/transactions/create');
    await fillNewItem(page, `Sabun ${suffix}`, '4000');
    await expect(page.getByText('Rp4.000').first()).toBeVisible();
    await noOverflow(page);

    await page.getByRole('button', { name: 'Simpan & transaksi baru' }).click();

    // Alamatnya tetap /transactions/create, jadi tunggu pesan sukses, bukan perubahan URL.
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByPlaceholder('Ketik nama barang')).toHaveValue('');
    const lastLink = page.getByRole('link', { name: /^Lihat TRX-\d{8}-\d{3}$/ });
    const firstNumber = await lastLink.textContent();

    // Transaksi kedua langsung dari form yang sama.
    await fillNewItem(page, `Sikat ${suffix}`, '7000');
    await page.getByRole('button', { name: 'Simpan & transaksi baru' }).click();
    await expect(lastLink).not.toHaveText(firstNumber);
    await expect(page.getByPlaceholder('Ketik nama barang')).toHaveValue('');

    // Tautan ke transaksi terakhir membuka transaksi kedua.
    await lastLink.click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(`SIKAT ${suffix}`)).toBeVisible();
    await expect(page.getByText('Rp7.000').first()).toBeVisible();
});

test('simpan & transaksi baru: menjadikan draf transaksi lalu membuka form kosong', async ({ page }) => {
    const suffix = Date.now();

    await page.goto('/transactions/create');
    await fillNewItem(page, `Draf Baru ${suffix}`, '5000');
    await page.getByRole('button', { name: 'Simpan draf' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    const draftUrl = page.url();

    await page.getByRole('link', { name: 'Ubah transaksi' }).click();
    await page.getByRole('button', { name: 'Simpan & transaksi baru' }).click();
    await page.waitForURL(/\/transactions\/create$/);
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();

    await page.goto(draftUrl);
    await expect(page.getByText('Ini draf.')).toHaveCount(0);
});
```

Catatan untuk tes e2e: setelah "Simpan & transaksi baru", alamat halaman tetap `/transactions/create`. Karena itu tes menunggu pesan sukses atau perubahan tautan `Lihat TRX-...`, bukan `waitForURL`. Jangan menggantinya dengan `waitForURL(/\/transactions\/create$/)`, karena syarat itu sudah terpenuhi sebelum halaman berganti.

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 7.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2.
- [ ] `php artisan route:list` tidak menunjukkan rute baru.
- [ ] Di browser HP: mengisi transaksi lalu menekan "Simpan & transaksi baru" menampilkan pesan sukses, tautan `Lihat TRX-...`, dan form kosong dengan total Rp0.
- [ ] Tautan `Lihat TRX-...` membuka transaksi yang baru disimpan.
- [ ] Saat mengubah draf, tombol itu ada dan menjadikan draf transaksi. Saat mengubah transaksi yang sudah dikonfirmasi, tombol itu tidak ada.
- [ ] Bilah bawah rapi di lebar 375px: total, dua tombol berdampingan, lalu tombol baru selebar layar.
- [ ] `README.md` proyek sudah diperbarui.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah `TransactionService`, rute, atau controller.
- Jangan menyalin tanggal, saluran, pelanggan, atau barang dari transaksi sebelumnya ke form baru. Form baru selalu kosong.
- Jangan mengubah tujuan "Simpan transaksi", "Simpan draf", atau "Simpan perubahan". Ketiganya tetap ke halaman detail.
- Jangan membuat tombol "Simpan draf & baru".
- Jangan menambah JavaScript.
- Jangan menghapus atau melemahkan tes agar lulus.

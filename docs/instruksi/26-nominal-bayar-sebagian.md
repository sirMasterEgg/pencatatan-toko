# Tugas 26: Nominal Dibayar untuk Status "Sebagian"

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 25 selesai.

## Tujuan

Status bayar "Sebagian" sekarang hanya label. Tidak tercatat berapa yang sudah dibayar, dan piutang di laporan menghitung seluruh total nota.

Setelah tugas ini, setiap transaksi punya kolom baru `paid_amount` (sudah dibayar):

| Status bayar | `paid_amount` | Kurang |
|---|---|---|
| Lunas (`paid`) | Otomatis sama dengan total | Rp0 |
| Belum bayar (`unpaid`) | Otomatis 0 | Total |
| Sebagian (`partial`) | Diisi pemilik. Wajib lebih dari Rp0 dan kurang dari total | Total dikurangi `paid_amount` |

Aturan:

- Untuk "Sebagian", nominal wajib diisi. Pesan jika kosong: `Isi nominal yang sudah dibayar.` Jika sama dengan atau lebih dari total: `Nominal dibayar harus kurang dari total. Pilih "Lunas" jika sudah dibayar penuh.`
- Nominal adalah TOTAL yang sudah dibayar sampai sekarang, bukan pembayaran terakhir. Untuk pembayaran berikutnya, pemilik mengganti angkanya di halaman detail transaksi. Tidak ada riwayat cicilan.
- Untuk "Lunas" dan "Belum bayar", isian nominal diabaikan.
- Piutang di beranda dan laporan harian = jumlah total nota "Belum bayar" dan "Sebagian" dikurangi jumlah yang sudah dibayar. Jumlah transaksi piutang tidak berubah.
- Data lama: transaksi "Lunas" diisi `paid_amount` = total. "Belum bayar" dan "Sebagian" diisi 0, sehingga piutang lama tetap sama seperti sebelum tugas ini.
- Aturan kunci dari tugas 15 tidak berubah: transaksi "Sebagian" tetap tidak bisa diubah isinya. Yang bisa diubah hanya status bayar dan nominalnya, lewat form status bayar di halaman detail.

Tempat yang berubah:

| Tempat | Perubahan |
|---|---|
| Form transaksi | Saat status bayar "Sebagian" dipilih, muncul kolom "Nominal dibayar" dan teks `Kurang: Rp...` yang ikut berubah |
| Detail transaksi | Baris "Dibayar" (untuk Sebagian) dan "Kurang" (untuk Sebagian dan Belum bayar). Form status bayar punya kolom "Nominal dibayar" |
| Daftar transaksi | Transaksi "Sebagian" menampilkan `Kurang Rp...` di samping badge |
| Beranda dan laporan harian | Piutang = sisa yang belum dibayar |

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 02, tabel `transactions` | Tanpa kolom nominal dibayar | Ditambah `paid_amount` lewat migrasi BARU |
| 06, "Jangan membuat tabel pembayaran atau cicilan" | Berlaku | Tetap berlaku. Hanya satu kolom angka, bukan tabel |
| 06, `create` dan `update` | `total` dihitung dari item | `paid_amount` juga diisi sesuai status bayar |
| 06, `updatePaymentStatus($transaction, $status)` | Hanya mengubah status | Parameter ketiga `?int $paidAmount = null`, dan `paid_amount` ikut diperbarui |
| 07, select status bayar | `wire:model` | `wire:model.live`, agar kolom nominal langsung muncul |
| 08, form status bayar | Select dan tombol | Ditambah kolom "Nominal dibayar" |
| 09, piutang | Jumlah total nota belum lunas | Jumlah total dikurangi jumlah dibayar |

Tidak ada rute atau paket baru, dan tidak ada JavaScript baru selain memakai `data-rupiah` yang sudah ada.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `database/migrations/2026_11_25_000002_add_paid_amount_to_transactions_table.php`
- `tests/Feature/PartialPaymentTest.php`
- `tests/e2e/bayar-sebagian.spec.js`

## Yang diubah

- `app/Models/Transaction.php`
- `app/Services/TransactionService.php`
- `app/Services/DailyReportService.php` (satu baris)
- `app/Http/Controllers/TransactionController.php` (hanya `updatePaymentStatus`)
- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `resources/views/transactions/show.blade.php`
- `resources/views/transactions/index.blade.php`
- `tests/Feature/TransactionDraftAndEditTest.php` (satu baris, lihat langkah 8)
- `README.md` di akar proyek (langkah 9)

## Langkah

### 1. Migrasi

Buat `database/migrations/2026_11_25_000002_add_paid_amount_to_transactions_table.php` dengan isi persis ini, lalu jalankan `php artisan migrate`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas 26: nominal yang sudah dibayar. Lunas = total, Belum bayar = 0, Sebagian = isian pemilik.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0)->after('total');
        });

        // Data lama: Lunas dianggap dibayar penuh. Sebagian dan Belum bayar dianggap 0,
        // sehingga piutang lama tetap sama seperti sebelum tugas ini.
        DB::table('transactions')->where('payment_status', 'paid')->update(['paid_amount' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
```

`DB::raw('total')` menyalin nilai kolom `total` di baris yang sama. Cara ini berlaku di SQLite dan MySQL.

### 2. Model Transaction

- Tambahkan `'paid_amount'` ke `#[Fillable([...])]`, tepat setelah `'total'`.
- Tambahkan `'paid_amount' => 'integer',` ke `casts()`, tepat setelah baris `total`.
- Tambahkan method ini tepat di atas `scopeConfirmed`:

```php
/**
 * Sisa yang belum dibayar.
 */
public function remainingAmount(): int
{
    return max(0, $this->total - $this->paid_amount);
}
```

### 3. TransactionService

Di `normalize`, tambahkan blok ini sebagai baris pertama di dalam method, agar nominal bertitik (`'40.000'`) juga diterima:

```php
if (array_key_exists('paid_amount', $data)) {
    $data['paid_amount'] = Parse::rupiah($data['paid_amount']);
}
```

Di `validate`, tambahkan aturan ini tepat di bawah aturan `payment_status`:

```php
'paid_amount' => ['nullable', 'integer', 'min:0'],
```

dan nama atribut ini di array `$attributes`, di bawah `customer.name`:

```php
'paid_amount' => 'nominal dibayar',
```

Di `create`, ganti baris `$transaction->update(['total' => $this->createItems($transaction, $data['items'])]);` dengan:

```php
$total = $this->createItems($transaction, $data['items']);
$transaction->update([
    'total' => $total,
    'paid_amount' => $this->paidAmountFor($data['payment_status'], $total, $data['paid_amount'] ?? null),
]);
```

Di `update`, ganti baris `$transaction->update(['total' => $total]);` dengan:

```php
$transaction->update([
    'total' => $total,
    'paid_amount' => $this->paidAmountFor($data['payment_status'], $total, $data['paid_amount'] ?? null),
]);
```

Ganti method `updatePaymentStatus` dengan dua method ini:

```php
public function updatePaymentStatus(Transaction $transaction, PaymentStatus $status, ?int $paidAmount = null): Transaction
{
    if ($transaction->status !== TransactionStatus::Confirmed) {
        throw ValidationException::withMessages(['payment_status' => 'Status bayar hanya bisa diubah pada transaksi yang dikonfirmasi.']);
    }

    $transaction->update([
        'payment_status' => $status,
        'paid_amount' => $this->paidAmountFor($status, $transaction->total, $paidAmount),
    ]);

    return $transaction;
}

/**
 * Nominal dibayar yang disimpan: Lunas = total, Belum bayar = 0,
 * Sebagian = isian pemilik, wajib lebih dari 0 dan kurang dari total.
 */
private function paidAmountFor(PaymentStatus|string $status, int $total, mixed $paidAmount): int
{
    $status = $status instanceof PaymentStatus ? $status : PaymentStatus::from($status);

    if ($status === PaymentStatus::Paid) {
        return $total;
    }

    if ($status === PaymentStatus::Unpaid) {
        return 0;
    }

    if (! is_int($paidAmount) || $paidAmount < 1) {
        throw ValidationException::withMessages(['paid_amount' => 'Isi nominal yang sudah dibayar.']);
    }

    if ($paidAmount >= $total) {
        throw ValidationException::withMessages(['paid_amount' => 'Nominal dibayar harus kurang dari total. Pilih "Lunas" jika sudah dibayar penuh.']);
    }

    return $paidAmount;
}
```

Penjelasan:

- Pemeriksaan "kurang dari total" baru bisa dilakukan setelah total dihitung dari item. Karena itu `paidAmountFor` dipanggil di dalam `DB::transaction`. Jika gagal, `ValidationException` membatalkan seluruh simpanan, sesuai aturan bisnis 7.
- Semua aturan `paid_amount` hanya ada di `paidAmountFor`. Jangan menulisnya ulang di controller atau komponen.

### 4. TransactionController

Tambahkan `use App\Support\Parse;` di bagian atas. Ganti method `updatePaymentStatus` dengan:

```php
public function updatePaymentStatus(Request $request, Transaction $transaction, TransactionService $service): RedirectResponse
{
    $request->merge(['paid_amount' => Parse::rupiah($request->input('paid_amount'))]);

    $data = $request->validate([
        'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
        'paid_amount' => ['nullable', 'integer', 'min:0'],
    ], [], ['paid_amount' => 'nominal dibayar']);

    $service->updatePaymentStatus($transaction, PaymentStatus::from($data['payment_status']), $data['paid_amount'] ?? null);

    return redirect()->route('transactions.show', $transaction)->with('success', 'Status bayar diperbarui.');
}
```

### 5. Laporan

Di `app/Services/DailyReportService.php`, ubah baris `$unpaidTotal` menjadi:

```php
$unpaidTotal = (int) (clone $unpaid)->sum('total') - (int) (clone $unpaid)->sum('paid_amount');
```

Kunci `unpaid_total` dan `unpaid_count` tidak berubah, jadi view beranda dan laporan tidak perlu diubah.

### 6. Komponen TransactionForm

Tambahkan properti ini tepat di bawah `public string $paymentStatus = 'paid';`:

```php
public string $paidAmount = '';
```

Tambahkan method ini tepat di atas `total()`:

```php
/**
 * Sisa yang belum dibayar untuk status "Sebagian".
 */
public function remaining(): int
{
    $paid = Parse::rupiah($this->paidAmount);

    return max(0, $this->total() - (is_int($paid) ? $paid : 0));
}
```

Di `fillFromTransaction`, tambahkan satu baris tepat di bawah `$this->paymentStatus = ...;`:

```php
$this->paidAmount = $transaction->payment_status === PaymentStatus::Partial ? Format::thousands($transaction->paid_amount) : '';
```

Di `persist`, tambahkan satu baris di array `$data`, tepat di bawah `'payment_status' => $this->paymentStatus,`:

```php
'paid_amount' => $this->paymentStatus === PaymentStatus::Partial->value ? Parse::rupiah($this->paidAmount) : null,
```

### 7. View

Form transaksi (`resources/views/livewire/transaction-form.blade.php`). Ubah select status bayar dari `wire:model="paymentStatus"` menjadi `wire:model.live="paymentStatus"`. Lalu tepat setelah `</div>` penutup baris saluran dan status bayar (`<div class="row g-2">`), masih di dalam kartu "Data transaksi", tambahkan:

```blade
                @if ($paymentStatus === 'partial')
                    <div class="mt-2">
                        <label for="paidAmount" class="form-label">Nominal dibayar</label>
                        <input type="text" id="paidAmount" class="form-control @error('paid_amount') is-invalid @enderror" wire:model.live.blur="paidAmount" inputmode="numeric" autocomplete="off" data-rupiah>
                        @error('paid_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Kurang: <strong>{{ \App\Support\Format::rupiah($this->remaining()) }}</strong></div>
                    </div>
                @endif
```

Detail transaksi (`resources/views/transactions/show.blade.php`). Tepat di bawah baris `<dt class="col-5">Status bayar</dt>...`, tambahkan:

```blade
                @if ($transaction->payment_status === \App\Enums\PaymentStatus::Partial)
                    <dt class="col-5">Dibayar</dt><dd class="col-7">{{ \App\Support\Format::rupiah($transaction->paid_amount) }}</dd>
                @endif
                @if ($transaction->payment_status !== \App\Enums\PaymentStatus::Paid && ! $cancelled)
                    <dt class="col-5">Kurang</dt><dd class="col-7 fw-semibold text-danger">{{ \App\Support\Format::rupiah($transaction->remainingAmount()) }}</dd>
                @endif
```

Lalu di form status bayar, ganti `<select id="payment_status" ...> ... </select>` dengan blok berikut. Tombol "Simpan status bayar" di bawahnya tetap.

```blade
                    <select id="payment_status" name="payment_status" class="form-select mb-2">
                        @foreach ($paymentStatuses as $status)
                            <option value="{{ $status->value }}" @selected(old('payment_status', $transaction->payment_status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <label for="paid_amount" class="form-label">Nominal dibayar</label>
                    <input type="text" id="paid_amount" name="paid_amount" value="{{ \App\Support\Format::thousands(old('paid_amount', $transaction->payment_status === \App\Enums\PaymentStatus::Partial ? $transaction->paid_amount : null)) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('paid_amount') is-invalid @enderror">
                    @error('paid_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mb-2">Wajib diisi jika status bayar Sebagian. Isi total yang sudah dibayar sampai sekarang.</div>
```

Daftar transaksi (`resources/views/transactions/index.blade.php`). Tepat di bawah `@include('transactions._payment_badge', ...)`, tambahkan:

```blade
@if ($transaction->payment_status === \App\Enums\PaymentStatus::Partial && ! $cancelled)
    <span>Kurang {{ \App\Support\Format::rupiah($transaction->remainingAmount()) }}</span>
@endif
```

### 8. Sesuaikan satu tes lama

Di `tests/Feature/TransactionDraftAndEditTest.php`, tes `test_paid_partial_and_cancelled_transactions_cannot_be_updated` membuat transaksi "Sebagian" tanpa nominal, yang sekarang ditolak. Ubah baris:

```php
            $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => $payment]));
```

menjadi:

```php
            $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => $payment, 'paid_amount' => 5000]));
```

Untuk status "Lunas", nominal itu diabaikan. Ini satu-satunya perubahan tes lama yang diizinkan di tugas ini.

### 9. README proyek

Tugas ini menutup rangkaian tugas 22 sampai 26. Perbarui `README.md` di akar proyek:

- Pada baris `Status`, ganti `Tugas 01 sampai 21` menjadi `Tugas 01 sampai 26`.
- Di bagian "Fitur tahap 1", tambahkan lima butir ini di paling bawah:

  ```
  - **Filter dan urutan barang**: daftar barang bisa disaring per satuan, kategori, dan rentang harga, dan diurutkan menurut nama, harga, tanggal ditambahkan, atau paling laku
  - **Cari barang per satuan**: di form transaksi, hasil cari barang dikelompokkan per satuan lalu urut nama
  - **Pelanggan huruf besar**: nama dan alamat pelanggan disimpan huruf besar dan ditampilkan sebagai "NAMA - ALAMAT"
  - **Tanggal berjalan**: tanggal transaksi baru mengikuti waktu sekarang sampai diubah manual
  - **Bayar sebagian**: status "Sebagian" mencatat nominal yang sudah dibayar, dan piutang dihitung dari sisanya
  ```

Bagian lain README tidak diubah.

## Tes yang wajib ada

Buat `tests/Feature/PartialPaymentTest.php` dengan isi persis ini:

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->service = app(TransactionService::class);

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 20000, 'effective_from' => now()->subDay()]);
    }

    private function data(string $payment, mixed $paidAmount = null, int $quantity = 5): array
    {
        return [
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => $payment,
            'paid_amount' => $paidAmount,
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => $quantity, 'unit_price' => 20000, 'discount' => 0]],
        ];
    }

    // --- Layanan

    public function test_paid_amount_follows_payment_status(): void
    {
        $this->assertSame(100000, $this->service->create($this->data('paid'))->paid_amount);
        $this->assertSame(0, $this->service->create($this->data('unpaid'))->paid_amount);
        $this->assertSame(40000, $this->service->create($this->data('partial', 40000))->paid_amount);
    }

    public function test_partial_accepts_amount_with_thousand_dots(): void
    {
        $transaction = $this->service->create($this->data('partial', '40.000'));

        $this->assertSame(40000, $transaction->paid_amount);
        $this->assertSame(60000, $transaction->remainingAmount());
    }

    public function test_partial_without_amount_is_rejected(): void
    {
        try {
            $this->service->create($this->data('partial'));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Isi nominal yang sudah dibayar.', $e->errors()['paid_amount'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_partial_amount_must_be_less_than_total(): void
    {
        try {
            $this->service->create($this->data('partial', 100000));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Nominal dibayar harus kurang dari total. Pilih "Lunas" jika sudah dibayar penuh.', $e->errors()['paid_amount'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_paid_amount_is_ignored_for_paid_and_unpaid(): void
    {
        $this->assertSame(100000, $this->service->create($this->data('paid', 1))->paid_amount);
        $this->assertSame(0, $this->service->create($this->data('unpaid', 50000))->paid_amount);
    }

    public function test_update_recalculates_paid_amount(): void
    {
        $draft = $this->service->create($this->data('partial', 40000), TransactionStatus::Draft);

        $updated = $this->service->update($draft, $this->data('partial', 10000, 2), TransactionStatus::Draft);
        $this->assertSame(10000, $updated->paid_amount);
        $this->assertSame(30000, $updated->remainingAmount());

        $this->expectException(ValidationException::class);
        $this->service->update($updated, $this->data('partial', 50000, 2), TransactionStatus::Draft);
    }

    public function test_update_payment_status_with_amount(): void
    {
        $transaction = $this->service->create($this->data('unpaid'));

        $this->service->updatePaymentStatus($transaction, PaymentStatus::Partial, 25000);
        $this->assertSame(25000, $transaction->fresh()->paid_amount);

        $this->service->updatePaymentStatus($transaction->fresh(), PaymentStatus::Paid);
        $this->assertSame(100000, $transaction->fresh()->paid_amount);

        $this->service->updatePaymentStatus($transaction->fresh(), PaymentStatus::Unpaid);
        $this->assertSame(0, $transaction->fresh()->paid_amount);
    }

    public function test_report_counts_only_remaining_amount_as_receivable(): void
    {
        $this->service->create($this->data('paid'));
        $this->service->create($this->data('unpaid'));
        $this->service->create($this->data('partial', 40000));

        $report = app(DailyReportService::class)->forDate(now());

        $this->assertSame(160000, $report['unpaid_total']);
        $this->assertSame(2, $report['unpaid_count']);
    }

    public function test_migration_sets_paid_amount_for_old_transactions(): void
    {
        $paid = $this->service->create($this->data('paid'));
        $unpaid = $this->service->create($this->data('unpaid'));
        Transaction::query()->update(['paid_amount' => 0]);

        $migration = require database_path('migrations/2026_11_25_000002_add_paid_amount_to_transactions_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(100000, $paid->fresh()->paid_amount);
        $this->assertSame(0, $unpaid->fresh()->paid_amount);
    }

    // --- Halaman

    public function test_payment_status_route_saves_partial_amount(): void
    {
        $transaction = $this->service->create($this->data('unpaid'));

        $this->post("/transactions/{$transaction->id}/payment-status", ['payment_status' => 'partial', 'paid_amount' => '30.000'])
            ->assertRedirect("/transactions/{$transaction->id}")
            ->assertSessionHas('success', 'Status bayar diperbarui.');

        $this->assertSame(30000, $transaction->fresh()->paid_amount);
        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee('Dibayar')
            ->assertSee('Rp30.000')
            ->assertSee('Kurang')
            ->assertSee('Rp70.000')
            ->assertSee('value="30.000"', false);
    }

    public function test_payment_status_route_rejects_partial_without_amount(): void
    {
        $transaction = $this->service->create($this->data('unpaid'));

        $this->from("/transactions/{$transaction->id}")
            ->post("/transactions/{$transaction->id}/payment-status", ['payment_status' => 'partial', 'paid_amount' => ''])
            ->assertSessionHasErrors('paid_amount');

        $this->assertSame(PaymentStatus::Unpaid, $transaction->fresh()->payment_status);
    }

    public function test_transaction_list_shows_remaining_for_partial(): void
    {
        $this->service->create($this->data('partial', 40000));

        $this->get('/transactions')->assertOk()->assertSee('Kurang Rp60.000');
    }

    // --- Form

    public function test_form_shows_amount_field_only_for_partial_and_computes_remaining(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '5')
            ->assertDontSee('Nominal dibayar')
            ->set('paymentStatus', 'partial')
            ->assertSee('Nominal dibayar')
            ->set('paidAmount', '40.000')
            ->assertSee('Kurang: <strong>Rp60.000</strong>', false);
    }

    public function test_form_saves_partial_amount(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '5')
            ->set('paymentStatus', 'partial')
            ->set('paidAmount', '40.000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(40000, Transaction::firstOrFail()->paid_amount);
    }

    public function test_form_shows_error_when_partial_amount_missing(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('paymentStatus', 'partial')
            ->call('save')
            ->assertHasErrors('paid_amount');

        $this->assertSame(0, Transaction::count());
    }

    public function test_edit_mode_loads_partial_amount_of_draft(): void
    {
        $draft = $this->service->create($this->data('partial', 40000), TransactionStatus::Draft);

        Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->assertSet('paymentStatus', 'partial')
            ->assertSet('paidAmount', '40.000');
    }
}
```

Buat `tests/e2e/bayar-sebagian.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

test('bayar sebagian: isi nominal dibayar, lihat kurangnya, lalu perbarui di detail', async ({ page }) => {
    const name = `Sebagian ${Date.now()}`;

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Harga jual').fill('20000');
    await page.getByLabel('Jumlah').fill('5');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp100.000').first()).toBeVisible();

    await expect(page.getByLabel('Nominal dibayar')).toHaveCount(0);
    await page.getByLabel('Status bayar').selectOption('partial');
    await page.getByLabel('Nominal dibayar').pressSequentially('40000');
    await expect(page.getByLabel('Nominal dibayar')).toHaveValue('40.000');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Kurang: Rp60.000')).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('Rp40.000').first()).toBeVisible();
    await expect(page.getByText('Rp60.000').first()).toBeVisible();

    // Pembayaran berikutnya: isi total yang sudah dibayar sampai sekarang.
    await page.getByLabel('Nominal dibayar').fill('70000');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();
    await expect(page.getByText('Rp30.000').first()).toBeVisible();

    // Lunas: baris "Kurang" hilang.
    await page.getByLabel('Status bayar', { exact: true }).selectOption('paid');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();
    await expect(page.locator('dt', { hasText: 'Kurang' })).toHaveCount(0);
});
```

## Kriteria selesai

- [ ] `php artisan migrate` berhasil.
- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 16.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] Di browser HP: memilih "Sebagian" di form transaksi memunculkan "Nominal dibayar" dan teks "Kurang" yang ikut berubah.
- [ ] Menyimpan "Sebagian" tanpa nominal, atau dengan nominal sama dengan total, ditolak dengan pesan berbahasa Indonesia.
- [ ] Detail transaksi menampilkan "Dibayar" dan "Kurang". Mengganti nominal di form status bayar memperbarui "Kurang".
- [ ] Piutang di laporan harian hanya menghitung sisa yang belum dibayar.
- [ ] Halaman rapi di lebar 375px.
- [ ] `README.md` proyek sudah diperbarui.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan membuat tabel pembayaran atau riwayat cicilan.
- Jangan mengubah migrasi lama.
- Jangan mengubah aturan kunci transaksi dari tugas 15.
- Jangan menulis aturan `paid_amount` di luar `TransactionService::paidAmountFor`.
- Jangan menghapus atau melemahkan tes agar lulus.

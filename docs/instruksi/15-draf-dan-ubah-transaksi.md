# Tugas 15: Draf dan Ubah Transaksi (Layanan dan Halaman)

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 14 selesai.

Tugas ini bagian pertama dari dua. Tugas 15 membuat aturan, layanan, rute, dan halaman. Tugas 16 mengubah form transaksi Livewire agar bisa menyimpan draf dan membuka transaksi lama untuk diubah. Kerjakan berurutan, masing-masing dalam sesi sendiri.

## Tujuan

Pemilik sering mencatat pesanan yang belum dibayar, misalnya pesanan WhatsApp yang diantar sore hari. Isinya bisa berubah sebelum dibayar. Saat ini transaksi yang sudah disimpan sama sekali tidak bisa diubah.

Setelah tugas 15 dan 16:

1. **Draf.** Transaksi bisa disimpan sebagai draf (`status` = `draft`). Draf tidak masuk laporan, bisa diubah kapan saja, bisa dijadikan transaksi (`confirmed`), dan bisa dihapus permanen.
2. **Ubah selama belum bayar.** Transaksi `confirmed` dengan status bayar `unpaid` ("Belum bayar") masih bisa diubah isinya.
3. **Terkunci setelah dibayar.** Begitu status bayar `partial` ("Sebagian") atau `paid` ("Lunas"), isi transaksi terkunci. Jika pemilik mengembalikan status bayar ke "Belum bayar" lewat form status bayar yang sudah ada, transaksi bisa diubah lagi.
4. Transaksi `cancelled` tidak pernah bisa diubah.

Ringkasnya, transaksi bisa diubah jika:

| `status` | `payment_status` | Bisa diubah? |
|---|---|---|
| `draft` | apa saja | Ya |
| `confirmed` | `unpaid` | Ya |
| `confirmed` | `partial` atau `paid` | Tidak |
| `cancelled` | apa saja | Tidak |

## Hubungan dengan tugas lama

Tugas ini mengganti satu aturan bisnis inti. Untuk hal berikut, file ini yang berlaku:

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| `00-aturan-umum.md` aturan 8 | Transaksi `confirmed` tidak bisa diubah isinya | Transaksi bisa diubah jika `Transaction::isEditable()` bernilai `true` (tabel di atas) |
| `00-aturan-umum.md` bagian 3 | "Ubah atau hapus transaksi" di luar lingkup | Ubah transaksi yang belum dibayar dan hapus DRAF kini masuk lingkup. Menghapus transaksi `confirmed` tetap dilarang |
| 06, `TransactionService::create` | Selalu `confirmed` | Parameter kedua `TransactionStatus $status`, bawaan `Confirmed`. Boleh `Draft` |
| 06 | Tidak ada `update` dan `deleteDraft` | Ditambah |
| 08, halaman detail | Tidak ada tombol ubah. Teks "Transaksi yang sudah disimpan tidak bisa diubah" | Ada tombol "Ubah transaksi" dan "Hapus draf". Teks penjelasan berubah |

Yang tidak berubah:

- Laporan hanya menghitung `confirmed` (aturan 9). Draf otomatis tidak terhitung.
- `cancel` hanya untuk `confirmed`. Draf tidak dibatalkan, tetapi dihapus.
- `updatePaymentStatus` hanya untuk `confirmed`.
- Semua penulisan transaksi tetap di `TransactionService` dan di dalam `DB::transaction`.
- Tidak ada kolom atau tabel baru. Nilai `draft` sudah ada di enum `TransactionStatus` sejak tugas 02.

## Keputusan desain

Ikuti keputusan ini. Jangan menggantinya dengan cara lain.

| Hal | Keputusan |
|---|---|
| Nomor transaksi | Draf langsung mendapat nomor `TRX-...` saat dibuat. Nomor tidak berubah saat diubah, walaupun tanggalnya diganti. Nomor draf yang dihapus tidak dipakai ulang |
| Cara mengubah item | Semua item lama dihapus, lalu item dibuat ulang dari data baru, di dalam satu `DB::transaction` |
| `list_price` saat diubah | Diambil dari harga yang berlaku saat diubah, sama seperti saat membuat. Pengecualian: barang yang lahir dari transaksi ini (item lama dengan `list_price` null) tetap `null`, agar tanda "Barang baru" tidak hilang |
| Barang dan harga baru dari draf | Dibuat saat draf disimpan, sama seperti transaksi biasa. Jika draf dihapus, barang itu tetap ada dengan status "Perlu dirapikan" |
| "Jadikan harga baru" saat mengubah | Menambah riwayat harga, sama seperti saat membuat |
| Draf dengan status bayar Lunas | Boleh. Draf selalu bisa diubah, apa pun status bayarnya |
| `confirmed` kembali ke `draft` | Ditolak |

## Yang dibuat

- `resources/views/transactions/edit.blade.php`
- `tests/Feature/TransactionDraftAndEditTest.php`

## Yang diubah

- `app/Models/Transaction.php`
- `app/Services/TransactionService.php`
- `app/Http/Controllers/TransactionController.php`
- `routes/web.php`
- `resources/views/transactions/show.blade.php`
- `resources/views/transactions/index.blade.php`

## Langkah

### 1. Model Transaction

Tambahkan method ini tepat di atas `scopeConfirmed`. `PaymentStatus` dan `TransactionStatus` sudah di-`use` di file itu.

```php
/**
 * Isi transaksi boleh diubah jika masih draf, atau sudah dikonfirmasi tetapi belum dibayar sama sekali.
 */
public function isEditable(): bool
{
    return $this->status === TransactionStatus::Draft
        || ($this->status === TransactionStatus::Confirmed && $this->payment_status === PaymentStatus::Unpaid);
}
```

Ini satu-satunya tempat aturan "bisa diubah". View, controller, dan service memanggil method ini. Jangan menulis ulang syaratnya di tempat lain.

### 2. TransactionService

Ganti seluruh method `create` lama dengan kode di bawah. Kode ini juga menambah `update`, `deleteDraft`, dan dua method privat. Letakkan semuanya di tempat `create` lama, yaitu sebelum `cancel`.

Isi perulangan item yang dulu ada di dalam `create` dipindah apa adanya ke method privat `createItems`, agar `create` dan `update` memakai logika yang sama.

```php
public function create(array $data, TransactionStatus $status = TransactionStatus::Confirmed): Transaction
{
    $this->assertSavableStatus($status);
    $data = $this->normalize($data);
    $this->validate($data);

    return DB::transaction(function () use ($data, $status) {
        $transactedAt = Carbon::parse($data['transacted_at']);

        $transaction = Transaction::create([
            'number' => $this->generateNumber($transactedAt),
            'transacted_at' => $transactedAt,
            'customer_id' => $this->resolveCustomerId($data['customer'] ?? null),
            'channel' => $data['channel'],
            'input_source' => 'form',
            'status' => $status,
            'payment_status' => $data['payment_status'],
            'total' => 0,
            'note' => $data['note'] ?? null,
        ]);

        $transaction->update(['total' => $this->createItems($transaction, $data['items'])]);

        return $transaction->load('items.product', 'customer');
    });
}

public function update(Transaction $transaction, array $data, TransactionStatus $status): Transaction
{
    if (! $transaction->isEditable()) {
        throw ValidationException::withMessages(['status' => 'Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.']);
    }

    $this->assertSavableStatus($status);

    if ($transaction->status === TransactionStatus::Confirmed && $status === TransactionStatus::Draft) {
        throw ValidationException::withMessages(['status' => 'Transaksi yang sudah dikonfirmasi tidak bisa dijadikan draf.']);
    }

    $data = $this->normalize($data);
    $this->validate($data);

    return DB::transaction(function () use ($transaction, $data, $status) {
        // Barang yang lahir dari transaksi ini tetap ditandai "Barang baru" (list_price null).
        $newProductIds = $transaction->items()->whereNull('list_price')->pluck('product_id')->all();

        $transaction->items()->delete();

        $transaction->update([
            'transacted_at' => Carbon::parse($data['transacted_at']),
            'customer_id' => $this->resolveCustomerId($data['customer'] ?? null),
            'channel' => $data['channel'],
            'status' => $status,
            'payment_status' => $data['payment_status'],
            'note' => $data['note'] ?? null,
        ]);

        $total = $this->createItems($transaction, $data['items']);

        $transaction->items()->whereIn('product_id', $newProductIds)->update(['list_price' => null]);
        $transaction->update(['total' => $total]);

        return $transaction->load('items.product', 'customer');
    });
}

public function deleteDraft(Transaction $transaction): void
{
    if ($transaction->status !== TransactionStatus::Draft) {
        throw ValidationException::withMessages(['status' => 'Hanya draf yang bisa dihapus.']);
    }

    DB::transaction(function () use ($transaction) {
        $transaction->items()->delete();
        $transaction->delete();
    });
}

/**
 * Membuat semua item untuk transaksi dan mengembalikan totalnya.
 */
private function createItems(Transaction $transaction, array $items): int
{
    $total = 0;

    foreach ($items as $index => $item) {
        $unitPrice = (int) $item['unit_price'];
        $discount = (int) ($item['discount'] ?? 0);
        $priceMode = $item['price_mode'] ?? 'this_only';

        if (! empty($item['product_id'])) {
            $product = Product::with('currentPrice')->findOrFail($item['product_id']);
            $this->assertSelectable($product, $index);
            $listPrice = $this->applyExistingProduct($product, $unitPrice, $priceMode, $transaction);
        } else {
            $new = $item['new_product'];
            $product = $this->products->findByNameAndUnit($new['name'], $new['unit']);

            if ($product) {
                $product->load('currentPrice');
                $listPrice = $this->applyExistingProduct($product, $unitPrice, 'this_only', $transaction);
            } else {
                $product = $this->products->create(
                    name: $new['name'],
                    unit: $new['unit'],
                    sellPrice: $unitPrice,
                    costPrice: isset($new['cost_price']) && $new['cost_price'] !== '' ? (int) $new['cost_price'] : null,
                    status: ProductStatus::NeedsReview,
                    sourceTransactionId: $transaction->id,
                );
                $listPrice = null;
            }
        }

        $subtotal = self::calculateSubtotal($item['quantity'], $unitPrice, $discount);

        if ($subtotal < 0) {
            throw ValidationException::withMessages(["items.{$index}.discount" => 'Diskon melebihi nilai baris.']);
        }

        $transaction->items()->create([
            'product_id' => $product->id,
            'quantity' => $item['quantity'],
            'unit' => $product->unit,
            'unit_price' => $unitPrice,
            'list_price' => $listPrice,
            'discount' => $discount,
            'subtotal' => $subtotal,
        ]);

        $total += $subtotal;
    }

    return $total;
}

private function assertSavableStatus(TransactionStatus $status): void
{
    if (! in_array($status, [TransactionStatus::Draft, TransactionStatus::Confirmed], true)) {
        throw ValidationException::withMessages(['status' => 'Status simpan harus draf atau dikonfirmasi.']);
    }
}
```

Aturan:

- `cancel`, `updatePaymentStatus`, `applyExistingProduct`, `assertSelectable`, `resolveCustomerId`, `generateNumber`, `normalize`, dan `validate` tidak berubah.
- `update` menjalankan `normalize` dan `validate` yang sama dengan `create`.
- `update` tidak mengubah `number`, `input_source`, `cancelled_at`, dan `cancel_reason`.
- Jangan menghapus barang atau riwayat harga apa pun, baik saat mengubah maupun saat menghapus draf.
- Jangan mengubah `products.created_from_transaction_id` atau `product_prices.source_transaction_id`. Kolom itu tidak punya foreign key, jadi boleh tetap menunjuk draf yang sudah dihapus.

### 3. Rute

Di `routes/web.php`, tambahkan dua rute ini tepat di atas rute `transactions.cancel`:

```php
Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
```

Tidak ada rute `PUT`. Penyimpanan perubahan dilakukan komponen Livewire di tugas 16.

### 4. TransactionController

Tambahkan dua method ini tepat di atas `cancel`. Tipe kembalian `edit` adalah `View|RedirectResponse`, karena method itu bisa menampilkan halaman atau mengalihkan. Kedua kelas itu sudah di-`use` di file ini.

```php
public function edit(Transaction $transaction): View|RedirectResponse
{
    if (! $transaction->isEditable()) {
        return redirect()->route('transactions.show', $transaction)
            ->with('error', 'Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.');
    }

    return view('transactions.edit', ['transaction' => $transaction]);
}

public function destroy(Transaction $transaction, TransactionService $service): RedirectResponse
{
    $service->deleteDraft($transaction);

    return redirect()->route('transactions.index')->with('success', 'Draf '.$transaction->number.' berhasil dihapus.');
}
```

Jika `deleteDraft` menolak (bukan draf), `ValidationException` otomatis mengembalikan pengguna ke halaman sebelumnya dengan galat `status`. Halaman detail sudah menampilkan semua galat sejak tugas 08.

### 5. View halaman ubah

Buat `resources/views/transactions/edit.blade.php` dengan isi persis ini:

```blade
@extends('layouts.app')

@section('title', 'Ubah '.$transaction->number)

@section('content')
    <h1 class="h4 mb-3">Ubah {{ $transaction->status === \App\Enums\TransactionStatus::Draft ? 'draf' : 'transaksi' }} {{ $transaction->number }}</h1>
    <livewire:transaction-form :transaction="$transaction" />
@endsection
```

Komponen Livewire baru bisa membaca `:transaction` setelah tugas 16. Sampai tugas 16 selesai, halaman ini menampilkan form kosong. Jangan memakainya untuk menyimpan data di antara kedua tugas.

### 6. View detail transaksi

Ganti seluruh isi `resources/views/transactions/show.blade.php` dengan isi di bawah. Bagian atas (data transaksi dan daftar item) sama dengan sebelumnya. Yang berubah:

- Ada variabel `$draft` dan kotak `Ini draf. ...`.
- Blok `@unless ($cancelled) ... @endunless` di bawah diganti: tombol "Ubah transaksi" jika `isEditable()`, form "Hapus draf" untuk draf, dan kartu status bayar serta batal untuk transaksi `confirmed`.
- Teks penjelasan di bawah tombol batal mengikuti `isEditable()`.

```blade
@extends('layouts.app')

@section('title', $transaction->number)

@section('content')
    @php($cancelled = $transaction->status === \App\Enums\TransactionStatus::Cancelled)
    @php($draft = $transaction->status === \App\Enums\TransactionStatus::Draft)
    <h1 class="h4 mb-3">{{ $transaction->number }}</h1>

    @if ($draft)
        <div class="alert alert-secondary">Ini draf. Draf belum masuk laporan sampai disimpan sebagai transaksi.</div>
    @endif

    @if ($cancelled)
        <div class="alert alert-danger">
            Dibatalkan pada {{ $transaction->cancelled_at->format('d/m/Y H:i') }}@if ($transaction->cancel_reason): {{ $transaction->cancel_reason }}@endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Tanggal</dt><dd class="col-7">{{ $transaction->transacted_at->format('d/m/Y H:i') }}</dd>
                <dt class="col-5">Saluran</dt><dd class="col-7">{{ $transaction->channel->label() }}</dd>
                <dt class="col-5">Status</dt><dd class="col-7">{{ $transaction->status->label() }}</dd>
                <dt class="col-5">Status bayar</dt><dd class="col-7">@include('transactions._payment_badge', ['status' => $transaction->payment_status])</dd>
                <dt class="col-5">Pelanggan</dt>
                <dd class="col-7">
                    @if ($transaction->customer)
                        <a href="{{ route('customers.show', $transaction->customer) }}">{{ $transaction->customer->name }}</a>
                        @if ($transaction->customer->phone)<div class="small text-muted">{{ $transaction->customer->phone }}</div>@endif
                    @else
                        Pembeli umum
                    @endif
                </dd>
                @if ($transaction->note)
                    <dt class="col-5">Catatan</dt><dd class="col-7">{{ $transaction->note }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <ul class="list-group mb-3">
        @foreach ($transaction->items as $item)
            <li class="list-group-item">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="{{ route('products.show', $item->product) }}" class="fw-semibold">{{ $item->product->name }}</a>
                        <div>{{ \App\Support\Format::quantity($item->quantity) }} {{ $item->unit }} × {{ \App\Support\Format::rupiah($item->unit_price) }}</div>
                        @if ($item->list_price === null)
                            <span class="badge text-bg-warning">Barang baru</span>
                        @elseif ($item->list_price !== $item->unit_price)
                            <small class="text-muted">Harga daftar: {{ \App\Support\Format::rupiah($item->list_price) }}</small>
                        @endif
                        @if ($item->discount > 0)
                            <div class="small text-muted">Diskon: {{ \App\Support\Format::rupiah($item->discount) }}</div>
                        @endif
                    </div>
                    <div class="fw-semibold">{{ \App\Support\Format::rupiah($item->subtotal) }}</div>
                </div>
            </li>
        @endforeach
        <li class="list-group-item d-flex justify-content-between">
            <strong>Total</strong>
            <strong>{{ \App\Support\Format::rupiah($transaction->total) }}</strong>
        </li>
    </ul>

    @if ($transaction->isEditable())
        <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-primary w-100 mb-3">Ubah transaksi</a>
    @endif

    @if ($draft)
        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" onsubmit="return confirm('Hapus draf ini? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger w-100">Hapus draf</button>
        </form>
    @elseif (! $cancelled)
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('transactions.payment-status', $transaction) }}" class="mb-3">
                    @csrf
                    <label for="payment_status" class="form-label">Status bayar</label>
                    <select id="payment_status" name="payment_status" class="form-select mb-2">
                        @foreach ($paymentStatuses as $status)
                            <option value="{{ $status->value }}" @selected($transaction->payment_status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary w-100">Simpan status bayar</button>
                </form>

                <form method="POST" action="{{ route('transactions.cancel', $transaction) }}" onsubmit="return confirm('Batalkan transaksi ini? Tindakan ini tidak bisa dibatalkan.')">
                    @csrf
                    <input type="text" name="reason" class="form-control mb-2" placeholder="Alasan pembatalan (opsional)" aria-label="Alasan pembatalan">
                    <button type="submit" class="btn btn-danger w-100">Batalkan transaksi</button>
                </form>
                @if ($transaction->isEditable())
                    <p class="small text-muted mt-2 mb-0">Transaksi ini masih bisa diubah selama status bayarnya Belum bayar.</p>
                @else
                    <p class="small text-muted mt-2 mb-0">Transaksi yang sudah dibayar tidak bisa diubah. Untuk koreksi, ubah status bayar ke Belum bayar, atau batalkan lalu buat transaksi baru.</p>
                @endif
            </div>
        </div>
    @endif
@endsection
```

### 7. View daftar transaksi

Di `resources/views/transactions/index.blade.php`, ubah blok badge status:

```blade
@if ($cancelled)
    <span class="badge text-bg-danger">{{ $transaction->status->label() }}</span>
@endif
```

menjadi:

```blade
@if ($cancelled)
    <span class="badge text-bg-danger">{{ $transaction->status->label() }}</span>
@elseif ($transaction->status === \App\Enums\TransactionStatus::Draft)
    <span class="badge text-bg-secondary">{{ $transaction->status->label() }}</span>
@endif
```

Filter status di halaman ini sudah memuat pilihan "Draf", karena memakai `TransactionStatus::cases()`.

## Tes yang wajib ada

Buat `tests/Feature/TransactionDraftAndEditTest.php` dengan isi persis ini. Tes ini menjadi patokan perilaku. Jika ada yang gagal, perbaiki kodenya, bukan tesnya.

```php
<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionDraftAndEditTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->service = app(TransactionService::class);
    }

    private function productWithPrice(string $name, string $unit, int $price): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => $unit]);
        ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'effective_from' => now()->subDay()]);

        return $product;
    }

    private function data(array $items, array $extra = []): array
    {
        return array_merge([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'unpaid',
            'note' => null,
            'customer' => null,
            'items' => $items,
        ], $extra);
    }

    private function item(Product $product, int|string $quantity, int $unitPrice): array
    {
        return ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount' => 0, 'price_mode' => 'this_only'];
    }

    // --- Model

    public function test_is_editable_follows_status_and_payment_status(): void
    {
        $cases = [
            [TransactionStatus::Draft, PaymentStatus::Paid, true],
            [TransactionStatus::Draft, PaymentStatus::Unpaid, true],
            [TransactionStatus::Confirmed, PaymentStatus::Unpaid, true],
            [TransactionStatus::Confirmed, PaymentStatus::Partial, false],
            [TransactionStatus::Confirmed, PaymentStatus::Paid, false],
            [TransactionStatus::Cancelled, PaymentStatus::Unpaid, false],
        ];

        foreach ($cases as [$status, $payment, $expected]) {
            $transaction = new Transaction(['status' => $status, 'payment_status' => $payment]);
            $this->assertSame($expected, $transaction->isEditable(), "{$status->value} + {$payment->value}");
        }
    }

    // --- Draf

    public function test_create_as_draft_is_not_counted_in_report(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        $draft = $this->service->create($this->data([$this->item($gula, 2, 18000)]), TransactionStatus::Draft);

        $this->assertSame(TransactionStatus::Draft, $draft->status);
        $this->assertSame(36000, $draft->total);
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-\d{3}$/', $draft->number);
        $this->assertSame(0, app(DailyReportService::class)->forDate(now())['revenue']);
    }

    public function test_create_without_status_is_still_confirmed(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
    }

    public function test_cannot_save_as_cancelled(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        $this->expectException(ValidationException::class);

        $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Cancelled);
    }

    public function test_delete_draft_removes_transaction_and_items_but_keeps_new_product(): void
    {
        $draft = $this->service->create($this->data([
            ['new_product' => ['name' => 'Teh', 'unit' => 'pak'], 'quantity' => 1, 'unit_price' => 8000, 'discount' => 0],
        ]), TransactionStatus::Draft);

        $this->service->deleteDraft($draft);

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, TransactionItem::count());
        $this->assertSame(ProductStatus::NeedsReview, Product::firstOrFail()->status);
    }

    public function test_confirmed_transaction_cannot_be_deleted(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->expectException(ValidationException::class);

        $this->service->deleteDraft($transaction);
    }

    // --- Ubah

    public function test_update_replaces_items_and_total_but_keeps_number(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $kopi = $this->productWithPrice('KOPI', 'pcs', 2000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));
        $number = $transaction->number;

        $updated = $this->service->update($transaction, $this->data([
            $this->item($gula, '0,5', 18000),
            $this->item($kopi, 3, 2000),
        ], ['note' => 'Dikoreksi']), TransactionStatus::Confirmed);

        $this->assertSame($number, $updated->number);
        $this->assertSame(15000, $updated->total);
        $this->assertSame('Dikoreksi', $updated->note);
        $this->assertSame(2, TransactionItem::count());
        $this->assertSame(1, Transaction::count());
    }

    public function test_draft_can_be_confirmed_by_update(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $confirmed = $this->service->update($draft, $this->data([$this->item($gula, 2, 18000)], ['payment_status' => 'paid']), TransactionStatus::Confirmed);

        $this->assertSame(TransactionStatus::Confirmed, $confirmed->status);
        $this->assertSame(36000, app(DailyReportService::class)->forDate(now())['revenue']);
    }

    public function test_draft_with_paid_status_can_still_be_edited(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']), TransactionStatus::Draft);

        $updated = $this->service->update($draft, $this->data([$this->item($gula, 3, 18000)], ['payment_status' => 'paid']), TransactionStatus::Draft);

        $this->assertSame(54000, $updated->total);
    }

    public function test_confirmed_transaction_cannot_go_back_to_draft(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->expectException(ValidationException::class);

        $this->service->update($transaction, $this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);
    }

    public function test_paid_partial_and_cancelled_transactions_cannot_be_updated(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        foreach (['paid', 'partial'] as $payment) {
            $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => $payment]));
            $this->assertUpdateRejected($transaction, $gula);
        }

        $cancelled = $this->service->create($this->data([$this->item($gula, 1, 18000)]));
        $this->service->cancel($cancelled);
        $this->assertUpdateRejected($cancelled->fresh(), $gula);
    }

    private function assertUpdateRejected(Transaction $transaction, Product $product): void
    {
        $before = $transaction->total;

        try {
            $this->service->update($transaction, $this->data([$this->item($product, 5, 18000)]), TransactionStatus::Confirmed);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame($before, $transaction->fresh()->total);
    }

    public function test_paid_transaction_is_editable_again_after_payment_set_back_to_unpaid(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']));

        $this->service->updatePaymentStatus($transaction, PaymentStatus::Unpaid);
        $updated = $this->service->update($transaction->fresh(), $this->data([$this->item($gula, 2, 18000)]), TransactionStatus::Confirmed);

        $this->assertSame(36000, $updated->total);
    }

    public function test_invalid_update_changes_nothing(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        try {
            $this->service->update($transaction, $this->data([$this->item($gula, '', 18000)]), TransactionStatus::Confirmed);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException) {
        }

        $this->assertSame(18000, $transaction->fresh()->total);
        $this->assertSame(1, $transaction->items()->count());
    }

    public function test_update_keeps_new_product_marker(): void
    {
        $transaction = $this->service->create($this->data([
            ['new_product' => ['name' => 'Teh', 'unit' => 'pak'], 'quantity' => 1, 'unit_price' => 8000, 'discount' => 0],
        ]));
        $teh = Product::firstOrFail();

        $updated = $this->service->update($transaction, $this->data([$this->item($teh, 2, 8000)]), TransactionStatus::Confirmed);

        $this->assertNull($updated->items->first()->list_price);
        $this->assertSame(1, $teh->prices()->count());
    }

    public function test_update_with_new_price_mode_adds_price_history(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->service->update($transaction, $this->data([
            array_merge($this->item($gula, 1, 19000), ['price_mode' => 'new_price']),
        ]), TransactionStatus::Confirmed);

        $this->assertSame(2, $gula->prices()->count());
        $this->assertSame(19000, $gula->fresh()->currentPrice->sell_price);
    }

    // --- Halaman

    public function test_show_page_has_edit_button_only_when_editable(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $unpaid = $this->service->create($this->data([$this->item($gula, 1, 18000)]));
        $paid = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']));

        $this->get("/transactions/{$unpaid->id}")->assertOk()->assertSee('Ubah transaksi');
        $this->get("/transactions/{$paid->id}")->assertOk()->assertDontSee('Ubah transaksi');
    }

    public function test_draft_show_page_has_delete_button_and_no_cancel_or_payment_form(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $this->get("/transactions/{$draft->id}")
            ->assertOk()
            ->assertSee('Ini draf.')
            ->assertSee('Hapus draf')
            ->assertSee('Ubah transaksi')
            ->assertDontSee('Batalkan transaksi')
            ->assertDontSee('Simpan status bayar');
    }

    public function test_edit_page_shows_form_for_editable_transaction(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->get("/transactions/{$transaction->id}/edit")
            ->assertOk()
            ->assertSee('Ubah transaksi '.$transaction->number)
            ->assertSeeLivewire('transaction-form');
    }

    public function test_edit_page_redirects_when_paid(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']));

        $this->get("/transactions/{$transaction->id}/edit")
            ->assertRedirect("/transactions/{$transaction->id}")
            ->assertSessionHas('error', 'Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.');
    }

    public function test_delete_route_removes_draft(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $this->delete("/transactions/{$draft->id}")
            ->assertRedirect('/transactions')
            ->assertSessionHas('success', 'Draf '.$draft->number.' berhasil dihapus.');

        $this->assertSame(0, Transaction::count());
    }

    public function test_delete_route_rejects_confirmed_transaction(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->from("/transactions/{$transaction->id}")
            ->delete("/transactions/{$transaction->id}")
            ->assertSessionHasErrors('status');

        $this->assertSame(1, Transaction::count());
    }

    public function test_index_shows_draft_badge(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $this->get('/transactions?status=draft')->assertOk()->assertSee('badge text-bg-secondary">Draf', false);
    }
}
```

Tes lama tidak perlu diubah dan harus tetap lulus. `TransactionServiceTest` yang memanggil `create($data)` tanpa parameter kedua tetap membuat transaksi `confirmed`.

## Kriteria selesai

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 22.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes tidak bertambah.
- [ ] `php artisan route:list` menampilkan `transactions.edit` dan `transactions.destroy`, dan tidak ada rute lain yang baru.
- [ ] Di browser: transaksi "Belum bayar" menampilkan tombol "Ubah transaksi". Transaksi "Lunas" tidak.
- [ ] Membuka `/transactions/{id}/edit` untuk transaksi "Lunas" kembali ke detail dengan pesan galat.
- [ ] Laporan harian tidak berubah untuk transaksi yang ada.

Tombol "Simpan draf" dan isian form ubah belum ada. Keduanya dibuat di tugas 16. Tulis di laporan bahwa alur draf di browser belum bisa diperiksa sampai tugas 16.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan menambah kolom atau tabel. Jangan mengubah migrasi.
- Jangan membuat fitur hapus untuk transaksi `confirmed` atau `cancelled`.
- Jangan membuat rute `PUT /transactions/{transaction}` atau form ubah tanpa Livewire.
- Jangan menulis syarat "bisa diubah" di luar `Transaction::isEditable()`.
- Jangan mengubah `DailyReportService`. Draf sudah tidak terhitung karena laporan hanya membaca `confirmed`.
- Jangan mengubah `TransactionForm` di tugas ini. Itu tugas 16.
- Jangan menghapus atau melemahkan tes agar lulus.

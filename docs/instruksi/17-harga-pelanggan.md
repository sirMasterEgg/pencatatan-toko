# Tugas 17: Harga Pelanggan (Data dan Halaman Pelanggan)

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 16 selesai.

Tugas ini bagian pertama dari dua. Tugas 17 membuat tempat menyimpan harga pelanggan dan halaman untuk mengaturnya. Tugas 18 memakai harga itu di form transaksi. Kerjakan berurutan, masing-masing dalam sesi sendiri.

## Tujuan

Pelanggan langganan sering mendapat harga sendiri, misalnya Bu Sari membeli GULA Rp17.000/kg, sementara harga umumnya Rp18.000. Setelah tugas 17 dan 18, harga di nota ditentukan dengan urutan:

| Urutan | Harga | Disimpan di |
|---|---|---|
| 1 | Harga khusus per nota: harga yang diketik pemilik di form transaksi untuk nota itu saja ("Hanya nota ini") | `transaction_items.unit_price` |
| 2 | Harga pelanggan: harga tetap untuk satu pelanggan dan satu barang | Tabel baru `customer_prices` |
| 3 | Harga umum: harga jual barang seperti sekarang | `product_prices` |

Di tugas 17:

- Tabel `customer_prices` dan model `CustomerPrice`.
- `CustomerPriceService` untuk membaca dan menambah harga pelanggan.
- Di halaman detail pelanggan: daftar harga khusus yang berlaku, form untuk mengatur harga khusus, tombol "Hentikan", dan riwayat harga khusus.

## Aturan harga pelanggan

1. Harga pelanggan adalah riwayat, sama seperti harga umum (aturan bisnis 2). Setiap perubahan menambah baris baru. Baris lama tidak pernah diubah atau dihapus.
2. Harga pelanggan yang berlaku untuk satu pelanggan dan satu barang adalah baris dengan `effective_from` terbaru yang tidak melewati waktu sekarang. Jika dua baris punya `effective_from` sama, yang `id`-nya lebih besar yang dipakai.
3. "Hentikan" menambah baris dengan `sell_price` = `null`. Sejak saat itu pelanggan kembali memakai harga umum.
4. Harga pelanggan berupa angka tetap, bukan potongan dari harga umum. Jika harga umum berubah, harga pelanggan tidak ikut berubah, kecuali lewat fitur ubah harga massal di tugas 19.
5. Jika pelanggan dihapus (hanya boleh jika belum punya transaksi, aturan dari tugas 05), semua baris harga pelanggannya ikut terhapus oleh foreign key `cascadeOnDelete`.

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| `00-aturan-umum.md` aturan 2 | Hanya untuk `product_prices` | Berlaku juga untuk `customer_prices` |
| 05, halaman detail pelanggan | Data, ringkasan, dan transaksi terakhir | Ditambah kartu "Harga khusus" dan tabel "Riwayat harga khusus" di atas "Transaksi terakhir" |
| 05, `CustomerController::show` | Tiga data untuk view | Ditambah empat data untuk bagian harga khusus |
| 00, larangan tabel baru | Jangan menambah tabel | Tugas ini menambah satu tabel `customer_prices` lewat migrasi baru |

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `database/migrations/2026_11_21_000001_create_customer_prices_table.php`
- `app/Models/CustomerPrice.php`
- `app/Services/CustomerPriceService.php`
- `app/Http/Controllers/CustomerPriceController.php`
- `tests/Feature/CustomerPriceTest.php`
- `tests/e2e/harga-pelanggan.spec.js`

## Yang diubah

- `app/Models/Customer.php`
- `app/Http/Controllers/CustomerController.php` (hanya method `show`)
- `routes/web.php`
- `resources/views/customers/show.blade.php`

## Langkah

### 1. Migrasi

Buat `database/migrations/2026_11_21_000001_create_customer_prices_table.php` dengan isi persis ini, lalu jalankan `php artisan migrate`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas 17: harga khusus per pelanggan per barang, disimpan sebagai riwayat.
     * sell_price null berarti harga khusus dihentikan mulai effective_from.
     */
    public function up(): void
    {
        Schema::create('customer_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sell_price')->nullable();
            $table->dateTime('effective_from');
            $table->unsignedBigInteger('source_transaction_id')->nullable()->index();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'product_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_prices');
    }
};
```

### 2. Model

Buat `app/Models/CustomerPrice.php` dengan isi persis ini. Penjaga di `booted()` meniru `ProductPrice`, agar riwayat tidak bisa diubah atau dihapus lewat Eloquent.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'product_id', 'sell_price', 'effective_from', 'source_transaction_id', 'note'])]
class CustomerPrice extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Riwayat harga pelanggan tidak boleh diubah.'));
        static::deleting(fn () => throw new \LogicException('Riwayat harga pelanggan tidak boleh dihapus.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'sell_price' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

Di `app/Models/Customer.php`, tambahkan relasi ini di bawah `transactions()`:

```php
public function prices(): HasMany
{
    return $this->hasMany(CustomerPrice::class);
}
```

Tidak perlu factory untuk `CustomerPrice`. Tes membuat harga pelanggan lewat service.

### 3. CustomerPriceService

Buat `app/Services/CustomerPriceService.php` dengan isi persis ini:

```php
<?php

namespace App\Services;

use App\Models\CustomerPrice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CustomerPriceService
{
    /**
     * Harga pelanggan yang berlaku untuk satu barang pada waktu tertentu (bawaan: sekarang).
     * Mengembalikan null jika tidak ada atau sudah dihentikan.
     */
    public function priceAt(int $customerId, int $productId, ?CarbonInterface $at = null): ?int
    {
        return CustomerPrice::query()
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('effective_from', '<=', $at ?? now())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first()
            ?->sell_price;
    }

    /**
     * Semua harga khusus yang berlaku untuk satu pelanggan: [product_id => sell_price].
     */
    public function activeForCustomer(int $customerId, ?CarbonInterface $at = null): array
    {
        return $this->latestByKey(
            CustomerPrice::query()->where('customer_id', $customerId),
            'product_id',
            $at,
        );
    }

    /**
     * Semua pelanggan yang punya harga khusus berlaku untuk satu barang: [customer_id => sell_price].
     */
    public function activeForProduct(int $productId, ?CarbonInterface $at = null): array
    {
        return $this->latestByKey(
            CustomerPrice::query()->where('product_id', $productId),
            'customer_id',
            $at,
        );
    }

    public function set(
        int $customerId,
        int $productId,
        int $sellPrice,
        ?string $note = null,
        ?int $sourceTransactionId = null,
        ?CarbonInterface $effectiveFrom = null,
    ): CustomerPrice {
        return CustomerPrice::create([
            'customer_id' => $customerId,
            'product_id' => $productId,
            'sell_price' => $sellPrice,
            'effective_from' => $effectiveFrom ?? now(),
            'source_transaction_id' => $sourceTransactionId,
            'note' => $note,
        ]);
    }

    public function stop(int $customerId, int $productId): CustomerPrice
    {
        return CustomerPrice::create([
            'customer_id' => $customerId,
            'product_id' => $productId,
            'sell_price' => null,
            'effective_from' => now(),
            'note' => 'Harga khusus dihentikan',
        ]);
    }

    /**
     * Ambil baris terbaru per $key yang sudah berlaku, lalu buang yang sudah dihentikan.
     */
    private function latestByKey(Builder $query, string $key, ?CarbonInterface $at): array
    {
        $rows = $query
            ->where('effective_from', '<=', $at ?? now())
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get([$key, 'sell_price']);

        $prices = [];
        foreach ($rows as $row) {
            $prices[$row->{$key}] = $row->sell_price;
        }

        return array_filter($prices, fn (?int $price) => $price !== null);
    }
}
```

Penjelasan:

- `priceAt` dipakai untuk satu barang, misalnya saat memilih barang di form transaksi (tugas 18).
- `activeForCustomer` dipakai halaman pelanggan. `activeForProduct` dipakai fitur ubah harga massal (tugas 19).
- `latestByKey` mengambil semua baris yang sudah berlaku dengan urutan naik, lalu baris yang lebih baru menimpa yang lebih lama. Cara ini sengaja tidak memakai SQL khusus satu database.
- Parameter `$at` memungkinkan membaca harga di waktu lain. Tugas 19 memakainya untuk harga yang berlaku di masa depan.
- Ini satu-satunya tempat membuat baris `CustomerPrice`. Jangan memanggil `CustomerPrice::create` di tempat lain.

### 4. Controller

Buat `app/Http/Controllers/CustomerPriceController.php` dengan isi persis ini:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Services\CustomerPriceService;
use App\Support\Parse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerPriceController extends Controller
{
    public function store(Request $request, Customer $customer, CustomerPriceService $prices): RedirectResponse
    {
        $request->merge(['sell_price' => Parse::rupiah($request->input('sell_price'))]);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'product_id' => 'barang',
            'sell_price' => 'harga pelanggan',
        ]);

        if (! Product::selectable()->whereKey($data['product_id'])->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Barang ini sudah tidak aktif.']);
        }

        $prices->set($customer->id, (int) $data['product_id'], (int) $data['sell_price'], $data['note'] ?? null);

        return redirect()->route('customers.show', $customer)->with('success', 'Harga khusus berhasil disimpan.');
    }

    public function stop(Customer $customer, Product $product, CustomerPriceService $prices): RedirectResponse
    {
        if ($prices->priceAt($customer->id, $product->id) === null) {
            return back()->with('error', 'Barang ini tidak punya harga khusus untuk pelanggan ini.');
        }

        $prices->stop($customer->id, $product->id);

        return redirect()->route('customers.show', $customer)->with('success', 'Harga khusus dihentikan.');
    }
}
```

### 5. Rute

Di `routes/web.php`, tambahkan `use App\Http\Controllers\CustomerPriceController;` di bagian atas, lalu dua rute ini tepat di bawah `Route::resource('customers', CustomerController::class);`:

```php
Route::post('/customers/{customer}/prices', [CustomerPriceController::class, 'store'])->name('customers.prices.store');
Route::post('/customers/{customer}/prices/{product}/stop', [CustomerPriceController::class, 'stop'])->name('customers.prices.stop');
```

### 6. CustomerController::show

Tambahkan `use App\Models\Product;` dan `use App\Services\CustomerPriceService;` di bagian atas. Ganti method `show` dengan:

```php
public function show(Customer $customer, CustomerPriceService $prices): View
{
    $confirmed = $customer->transactions()->where('status', TransactionStatus::Confirmed);
    $specialPrices = $prices->activeForCustomer($customer->id);

    return view('customers.show', [
        'customer' => $customer,
        'transactionCount' => (clone $confirmed)->count(),
        'totalSpent' => (int) (clone $confirmed)->sum('total'),
        'transactions' => $customer->transactions()->latest('transacted_at')->latest('id')->limit(20)->get(),
        'specialPrices' => $specialPrices,
        'specialProducts' => Product::with('currentPrice')->whereKey(array_keys($specialPrices))->orderBy('name')->get(),
        'selectableProducts' => Product::selectable()->with('currentPrice')->orderBy('name')->get(),
        'priceHistory' => $customer->prices()->with('product')->latest('effective_from')->latest('id')->limit(20)->get(),
    ]);
}
```

Method lain di controller ini tidak berubah.

### 7. View detail pelanggan

Di `resources/views/customers/show.blade.php`, tepat di atas baris `<h2 class="h6">Transaksi terakhir</h2>`, sisipkan blok ini. Bagian lain view tidak berubah.

```blade
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Harga khusus</h2>
            @if ($specialProducts->isEmpty())
                <p class="text-muted">Belum ada harga khusus.</p>
            @else
                <ul class="list-group mb-3">
                    @foreach ($specialProducts as $product)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <a href="{{ route('products.show', $product) }}" class="fw-semibold">{{ $product->name }}</a>
                                <small class="text-muted">({{ $product->unit }})</small>
                                <div class="small text-muted">Harga umum: {{ $product->currentPrice ? \App\Support\Format::rupiah($product->currentPrice->sell_price) : '-' }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold">{{ \App\Support\Format::rupiah($specialPrices[$product->id]) }}</div>
                                <form method="POST" action="{{ route('customers.prices.stop', [$customer, $product]) }}" onsubmit="return confirm('Hentikan harga khusus barang ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">Hentikan</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('customers.prices.store', $customer) }}">
                @csrf
                <div class="mb-2">
                    <label for="product_id" class="form-label">Barang</label>
                    <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">Pilih barang</option>
                        @foreach ($selectableProducts as $option)
                            <option value="{{ $option->id }}" @selected((int) old('product_id') === $option->id)>{{ $option->name }} ({{ $option->unit }}){{ $option->currentPrice ? ' - '.\App\Support\Format::rupiah($option->currentPrice->sell_price) : '' }}</option>
                        @endforeach
                    </select>
                    @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label for="special_sell_price" class="form-label">Harga pelanggan</label>
                    <input type="text" id="special_sell_price" name="sell_price" value="{{ \App\Support\Format::thousands(old('sell_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('sell_price') is-invalid @enderror" required>
                    @error('sell_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label for="special_note" class="form-label">Catatan</label>
                    <input type="text" id="special_note" name="note" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text mb-2">Jika barang sudah punya harga khusus, harga lama diganti mulai sekarang.</div>
                <button type="submit" class="btn btn-primary w-100">Simpan harga khusus</button>
            </form>
        </div>
    </div>

    @if ($priceHistory->isNotEmpty())
        <h2 class="h6">Riwayat harga khusus</h2>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped bg-white">
                <thead>
                    <tr><th>Berlaku sejak</th><th>Barang</th><th>Harga</th><th>Catatan</th></tr>
                </thead>
                <tbody>
                    @foreach ($priceHistory as $row)
                        <tr>
                            <td>{{ $row->effective_from->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->product->name }}</td>
                            <td>{{ $row->sell_price !== null ? \App\Support\Format::rupiah($row->sell_price) : 'Dihentikan' }}</td>
                            <td>{{ $row->note ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
```

Aturan:

- Kolom harga memakai `data-rupiah` dan `Format::thousands`, sesuai aturan kolom uang dari tugas 13.
- `id` kolom memakai awalan `special_` (`special_sell_price`, `special_note`) agar tidak bentrok dengan `id` lain di halaman. Label tetap "Harga pelanggan" dan "Catatan", karena tes e2e mencarinya lewat label.
- Pilihan barang hanya barang yang bisa dipilih (`active` dan `needs_review`).

## Tes yang wajib ada

Buat `tests/Feature/CustomerPriceTest.php` dengan isi persis ini:

```php
<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\CustomerPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPriceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerPriceService $prices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->prices = app(CustomerPriceService::class);
    }

    private function product(string $name = 'GULA', int $price = 18000): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'effective_from' => now()->subDay()]);

        return $product;
    }

    // --- Layanan

    public function test_price_at_returns_null_without_special_price(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNull($this->prices->priceAt($customer->id, $this->product()->id));
    }

    public function test_set_then_price_at_returns_latest_price(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->set($customer->id, $gula->id, 16500);

        $this->assertSame(16500, $this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame(2, CustomerPrice::count());
    }

    public function test_future_price_is_not_active_yet(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->set($customer->id, $gula->id, 17500, effectiveFrom: now()->addDay());

        $this->assertSame(17000, $this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame(17500, $this->prices->priceAt($customer->id, $gula->id, now()->addDays(2)));
    }

    public function test_stop_ends_special_price_but_keeps_history(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->stop($customer->id, $gula->id);

        $this->assertNull($this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame(2, CustomerPrice::count());
    }

    public function test_active_for_customer_and_for_product(): void
    {
        $sari = Customer::factory()->create();
        $budi = Customer::factory()->create();
        $gula = $this->product('GULA');
        $kopi = $this->product('KOPI', 2000);

        $this->prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->set($sari->id, $kopi->id, 1800, effectiveFrom: now()->subHour());
        $this->prices->stop($sari->id, $kopi->id);
        $this->prices->set($budi->id, $gula->id, 17500, effectiveFrom: now()->subHour());

        $this->assertSame([$gula->id => 17000], $this->prices->activeForCustomer($sari->id));
        $this->assertSame([$sari->id => 17000, $budi->id => 17500], $this->prices->activeForProduct($gula->id));
    }

    public function test_history_rows_cannot_be_changed_or_deleted(): void
    {
        $customer = Customer::factory()->create();
        $row = $this->prices->set($customer->id, $this->product()->id, 17000);

        try {
            $row->update(['sell_price' => 1]);
            $this->fail('Seharusnya ditolak.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_deleting_customer_removes_its_special_prices(): void
    {
        $customer = Customer::factory()->create();
        $this->prices->set($customer->id, $this->product()->id, 17000);

        $this->delete("/customers/{$customer->id}")->assertRedirect('/customers');

        $this->assertSame(0, CustomerPrice::count());
    }

    // --- Halaman

    public function test_store_sets_special_price_with_thousand_dots(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->post("/customers/{$customer->id}/prices", ['product_id' => $gula->id, 'sell_price' => '17.000', 'note' => 'Langganan'])
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('success', 'Harga khusus berhasil disimpan.');

        $this->assertSame(17000, $this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame('Langganan', CustomerPrice::firstOrFail()->note);
    }

    public function test_store_validates_input(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->post("/customers/{$customer->id}/prices", ['product_id' => $gula->id, 'sell_price' => ''])
            ->assertSessionHasErrors('sell_price');
        $this->post("/customers/{$customer->id}/prices", ['product_id' => 999, 'sell_price' => '1.000'])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(0, CustomerPrice::count());
    }

    public function test_store_rejects_inactive_product(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['status' => ProductStatus::Inactive]);

        $this->post("/customers/{$customer->id}/prices", ['product_id' => $product->id, 'sell_price' => '1.000'])
            ->assertSessionHasErrors('product_id');
    }

    public function test_stop_route_ends_special_price(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();
        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subMinute());

        $this->post("/customers/{$customer->id}/prices/{$gula->id}/stop")
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('success', 'Harga khusus dihentikan.');

        $this->assertNull($this->prices->priceAt($customer->id, $gula->id));
    }

    public function test_stop_route_without_special_price_shows_error(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->from("/customers/{$customer->id}")
            ->post("/customers/{$customer->id}/prices/{$gula->id}/stop")
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('error', 'Barang ini tidak punya harga khusus untuk pelanggan ini.');

        $this->assertSame(0, CustomerPrice::count());
    }

    public function test_customer_page_lists_special_prices_and_history(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product('GULA', 18000);
        $this->prices->set($customer->id, $gula->id, 17000, 'Langganan', effectiveFrom: now()->subMinute());

        $this->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Harga khusus')
            ->assertSee('GULA')
            ->assertSee('Harga umum: Rp18.000')
            ->assertSee('Rp17.000')
            ->assertSee('Riwayat harga khusus')
            ->assertSee('Langganan')
            ->assertSee('Simpan harga khusus');
    }

    public function test_customer_page_without_special_price(): void
    {
        $customer = Customer::factory()->create();

        $this->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Belum ada harga khusus.')
            ->assertDontSee('Riwayat harga khusus');
    }
}
```

Buat `tests/e2e/harga-pelanggan.spec.js` dengan isi persis ini. Tugas 18 akan menambah satu tes ke file ini.

```js
import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function newProduct(page, name, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

async function newCustomer(page, name) {
    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/customers$/);
    await page.goto(`/customers?q=${encodeURIComponent(name)}`);
    await page.getByRole('link', { name }).click();
    await page.waitForURL(/\/customers\/\d+$/);
}

test('harga pelanggan: atur dan hentikan harga khusus di halaman pelanggan', async ({ page }) => {
    const suffix = Date.now();
    const product = `Gula Pelanggan ${suffix}`;
    await newProduct(page, product, '18000');
    await newCustomer(page, `Bu Harga ${suffix}`);

    await expect(page.getByText('Belum ada harga khusus.')).toBeVisible();
    await page.getByLabel('Barang').selectOption({ label: `${product.toUpperCase()} (kg) - Rp18.000` });
    await page.getByLabel('Harga pelanggan').pressSequentially('17000');
    await expect(page.getByLabel('Harga pelanggan')).toHaveValue('17.000');
    await page.getByRole('button', { name: 'Simpan harga khusus' }).click();

    await expect(page.getByText('Harga khusus berhasil disimpan.')).toBeVisible();
    await expect(page.getByText('Harga umum: Rp18.000')).toBeVisible();
    await expect(page.getByText('Riwayat harga khusus')).toBeVisible();
    await noOverflow(page);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Hentikan' }).click();
    await expect(page.getByText('Harga khusus dihentikan.')).toBeVisible();
    await expect(page.getByText('Belum ada harga khusus.')).toBeVisible();
    await expect(page.getByRole('cell', { name: 'Dihentikan', exact: true })).toBeVisible();
});
```

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

- [ ] `php artisan migrate` berhasil.
- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 14.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] `php artisan route:list` menampilkan `customers.prices.store` dan `customers.prices.stop`, dan tidak ada rute lain yang baru.
- [ ] Di browser HP: di detail pelanggan bisa memilih barang, mengisi harga (langsung bertitik ribuan), menyimpan, melihatnya di daftar harga khusus, lalu menghentikannya.
- [ ] Halaman detail pelanggan rapi di lebar 375px.

Form transaksi belum memakai harga pelanggan. Itu tugas 18.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan membuat fitur ubah atau hapus baris harga pelanggan. Perubahan selalu menambah baris baru.
- Jangan menyimpan harga pelanggan sebagai potongan atau persen.
- Jangan mengubah `TransactionService`, `TransactionForm`, atau halaman transaksi. Itu tugas 18.
- Jangan memakai Livewire di halaman pelanggan.
- Jangan menghapus atau melemahkan tes agar lulus.

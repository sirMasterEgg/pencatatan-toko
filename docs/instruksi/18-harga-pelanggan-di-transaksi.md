# Tugas 18: Harga Pelanggan di Form Transaksi

Baca dulu: `00-aturan-umum.md` dan `17-harga-pelanggan.md` (bagian "Tujuan" dan "Aturan harga pelanggan")
Prasyarat: tugas 01 sampai 17 selesai.

## Tujuan

Tugas 17 sudah membuat harga pelanggan dan halaman untuk mengaturnya. Tugas ini memakainya saat mencatat transaksi, dengan urutan harga: harga khusus per nota, lalu harga pelanggan, lalu harga umum.

Setelah tugas ini:

1. **Harga acuan.** Di form transaksi, harga satuan awal sebuah baris adalah harga pelanggan jika pelanggan terpilih punya harga khusus untuk barang itu. Jika tidak, harga umum. Di bawah nama barang tampil `Harga daftar: Rp...` seperti sekarang, ditambah `Harga pelanggan: Rp...` jika ada.
2. **Ganti pelanggan.** Jika pelanggan dipilih, diganti, atau dihapus setelah barang dipilih, harga baris yang BELUM diubah pemilik ikut berganti ke harga acuan baru. Harga yang sudah diketik sendiri (harga khusus per nota) tidak disentuh.
3. **Pilihan ketiga.** Jika harga satuan berbeda dari harga acuan, selain "Hanya nota ini" dan "Jadikan harga baru" ada pilihan "Jadikan harga pelanggan". Pilihan itu hanya tampil jika ada pelanggan (lama atau baru).
4. **Salinan di nota.** Setiap item transaksi menyimpan salinan harga pelanggan yang berlaku saat dicatat, di kolom baru `transaction_items.customer_price`. Halaman detail transaksi menampilkan `Harga pelanggan: Rp... · Harga umum: Rp...` untuk item itu.

Arti tiap pilihan harga:

| `price_mode` | Label | Yang terjadi saat disimpan |
|---|---|---|
| `this_only` | Hanya nota ini | Tidak ada harga yang berubah. Harga hanya berlaku di nota ini |
| `new_price` | Jadikan harga baru | Menambah baris harga umum di `product_prices`, seperti sekarang |
| `customer_price` | Jadikan harga pelanggan | Menambah baris harga pelanggan di `customer_prices` untuk pelanggan nota ini, dengan `source_transaction_id` = id transaksi |

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| `00-aturan-umum.md` aturan 6 | Harga yang diubah di form hanya untuk nota itu, kecuali "Jadikan harga baru" | Ditambah pilihan "Jadikan harga pelanggan" |
| 06, `price_mode` | `this_only` atau `new_price` | Ditambah `customer_price`. Ditolak jika transaksi tanpa pelanggan |
| 06, item transaksi | Salinan `unit_price`, `list_price`, `unit` | Ditambah salinan `customer_price` |
| 07, `selectProduct` | `unit_price` = harga umum | `unit_price` = harga pelanggan jika ada, selain itu harga umum |
| 07, pilihan harga | Muncul jika harga satuan berbeda dari `list_price` | Muncul jika harga satuan berbeda dari harga acuan (`customer_price` jika ada, selain itu `list_price`) |
| 08, detail transaksi | `Harga daftar: Rp...` jika berbeda dari harga satuan | Jika item punya `customer_price`, tampil `Harga pelanggan: Rp... · Harga umum: Rp...`. Selain itu seperti sebelumnya |

Yang tidak berubah: `list_price` tetap salinan harga UMUM. Tanda "Barang baru" (`list_price` null) tetap seperti sebelumnya.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `database/migrations/2026_11_21_000002_add_customer_price_to_transaction_items_table.php`
- `tests/Feature/CustomerPriceTransactionTest.php`

## Yang diubah

- `app/Models/TransactionItem.php`
- `app/Services/TransactionService.php`
- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `resources/views/transactions/show.blade.php`
- `tests/e2e/harga-pelanggan.spec.js` (menambah satu tes)

## Langkah

### 1. Migrasi

Buat `database/migrations/2026_11_21_000002_add_customer_price_to_transaction_items_table.php` dengan isi persis ini, lalu jalankan `php artisan migrate`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas 18: salinan harga pelanggan yang berlaku saat transaksi dicatat.
     */
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_price')->nullable()->after('list_price');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('customer_price');
        });
    }
};
```

### 2. Model TransactionItem

- Tambahkan `'customer_price'` ke daftar `#[Fillable([...])]`, tepat setelah `'list_price'`.
- Tambahkan `'customer_price' => 'integer',` ke `casts()`, tepat setelah baris `list_price`.

### 3. TransactionService

Tambahkan `CustomerPriceService` ke konstruktor:

```php
public function __construct(
    private ProductService $products,
    private CustomerService $customers,
    private CustomerPriceService $customerPrices,
) {}
```

Ganti method `applyExistingProduct` (beserta docblock-nya) dengan dua method ini:

```php
/**
 * Returns the list price in effect just before this transaction (null if none).
 */
private function applyExistingProduct(Product $product, int $unitPrice, string $priceMode, Transaction $transaction, ?int $customerPrice = null): ?int
{
    $current = $product->currentPrice;
    $listPrice = $current?->sell_price;

    if ($listPrice === null) {
        $this->products->addPrice($product, $unitPrice, null, null, $transaction->id);
    } elseif ($priceMode === 'new_price' && $unitPrice !== $listPrice) {
        $this->products->addPrice($product, $unitPrice, $current->cost_price, null, $transaction->id);
    } elseif ($priceMode === 'customer_price' && $transaction->customer_id !== null && $unitPrice !== $customerPrice) {
        $this->customerPrices->set($transaction->customer_id, $product->id, $unitPrice, null, $transaction->id);
    }

    return $listPrice;
}

/**
 * Harga pelanggan yang berlaku untuk barang ini sebelum transaksi disimpan, atau null.
 */
private function customerPriceFor(Transaction $transaction, Product $product): ?int
{
    if ($transaction->customer_id === null) {
        return null;
    }

    return $this->customerPrices->priceAt($transaction->customer_id, $product->id);
}
```

Perhatikan pembandingnya:

- `new_price` membandingkan dengan harga umum (`$listPrice`), sama seperti sebelumnya.
- `customer_price` membandingkan dengan harga pelanggan saat ini. Harga pelanggan baru tidak dibuat jika angkanya sama.

Di `createItems`, ada tiga tempat yang diubah. Cabang barang lama (`if (! empty($item['product_id']))`):

```php
$product = Product::with('currentPrice')->findOrFail($item['product_id']);
$this->assertSelectable($product, $index);
$customerPrice = $this->customerPriceFor($transaction, $product);
$listPrice = $this->applyExistingProduct($product, $unitPrice, $priceMode, $transaction, $customerPrice);
```

Cabang barang baru yang ternyata sudah ada (`if ($product)` di dalam `else`):

```php
$product->load('currentPrice');
$customerPrice = $this->customerPriceFor($transaction, $product);
$listPrice = $this->applyExistingProduct($product, $unitPrice, 'this_only', $transaction, $customerPrice);
```

Cabang barang yang benar-benar baru: tambahkan `$customerPrice = null;` tepat di bawah `$listPrice = null;`.

Lalu di `$transaction->items()->create([...])`, tambahkan satu baris tepat di bawah `'list_price' => $listPrice,`:

```php
'customer_price' => $customerPrice,
```

`customerPriceFor` dipanggil SEBELUM `applyExistingProduct`. Jadi `customer_price` di item adalah harga pelanggan sebelum nota ini mengubahnya, sama seperti `list_price` adalah harga umum sebelum nota ini.

Di `validate`, ubah aturan `price_mode`:

```php
'items.*.price_mode' => ['nullable', Rule::in(['this_only', 'new_price', 'customer_price'])],
```

Masih di `validate`, di dalam perulangan `foreach ($data['items'] as $index => $item)`, tambahkan pemeriksaan ini tepat di atas baris `$quantity = (float) $item['quantity'];`:

```php
if (($item['price_mode'] ?? null) === 'customer_price' && empty($data['customer'])) {
    $errors["items.{$index}.price_mode"] = 'Pilih pelanggan dulu untuk menyimpan harga pelanggan.';
}
```

`create`, `update`, `deleteDraft`, `cancel`, dan `updatePaymentStatus` tidak perlu diubah. `update` memakai `createItems`, jadi ikut mendapat salinan `customer_price`.

### 4. Komponen TransactionForm

Tambahkan `use App\Services\CustomerPriceService;` di bagian atas.

Di `emptyRow()`, tambahkan `'customer_price' => null,` tepat di bawah `'list_price' => null,`.

Ganti method `selectProduct` dengan:

```php
public function selectProduct(int $index, int $productId): void
{
    $product = Product::selectable()->with('currentPrice')->find($productId);

    if (! $product || ! isset($this->items[$index])) {
        return;
    }

    $price = $product->currentPrice?->sell_price;
    $customerPrice = $this->customerPriceFor($product->id);

    $this->items[$index] = array_merge($this->items[$index], [
        'mode' => 'existing',
        'product_id' => $product->id,
        'name' => $product->name,
        'unit' => $product->unit,
        'list_price' => $price,
        'customer_price' => $customerPrice,
        'unit_price' => Format::thousands($customerPrice ?? $price),
        'price_mode' => 'this_only',
        'search' => '',
        'results' => [],
    ]);
}
```

Tambahkan `$this->refreshCustomerPrices();` sebagai baris TERAKHIR di tiga method: `selectCustomer`, `startNewCustomer`, dan `clearCustomer`.

Tambahkan dua method privat ini tepat di bawah `clearCustomer`:

```php
/**
 * Harga pelanggan yang berlaku untuk pelanggan terpilih, atau null.
 */
private function customerPriceFor(int $productId): ?int
{
    if ($this->customerMode !== 'existing' || $this->customerId === null) {
        return null;
    }

    return app(CustomerPriceService::class)->priceAt($this->customerId, $productId);
}

/**
 * Dipanggil setiap kali pelanggan berganti. Baris yang harganya belum diubah
 * mengikuti harga acuan baru (harga pelanggan, atau harga umum).
 */
private function refreshCustomerPrices(): void
{
    foreach ($this->items as $index => $row) {
        if ($row['mode'] !== 'existing' || $row['product_id'] === null) {
            continue;
        }

        $oldReference = $row['customer_price'] ?? $row['list_price'];
        $customerPrice = $this->customerPriceFor($row['product_id']);

        if (Parse::rupiah($row['unit_price']) === $oldReference) {
            $this->items[$index]['unit_price'] = Format::thousands($customerPrice ?? $row['list_price']);
        }

        $this->items[$index]['customer_price'] = $customerPrice;

        if ($row['price_mode'] === 'customer_price' && $this->customerMode === 'none') {
            $this->items[$index]['price_mode'] = 'this_only';
        }
    }
}
```

Di `fillFromTransaction`, di dalam `array_merge($this->emptyRow(), [...])`, tambahkan satu baris tepat di bawah `'list_price' => ...`:

```php
'customer_price' => $this->customerPriceFor($item->product_id),
```

`selectCustomer` di `fillFromTransaction` dipanggil sebelum baris dibuat, jadi `customerPriceFor` sudah tahu pelanggannya.

Penjelasan:

- Pelanggan BARU (`customerMode` = `'new'`) belum punya harga khusus, jadi `customerPriceFor` mengembalikan `null`. Pilihan "Jadikan harga pelanggan" tetap tampil, dan harga pelanggan dibuat setelah pelanggan baru tersimpan.
- `refreshCustomerPrices` membandingkan harga satuan dengan harga acuan LAMA. Jika sama, berarti pemilik belum mengubahnya, jadi aman diganti.
- Komponen tidak menyimpan harga pelanggan sendiri. Komponen hanya membaca lewat `CustomerPriceService::priceAt`. Penyimpanan tetap di `TransactionService`.

### 5. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, pada bagian barang lama, tepat di bawah baris `<small class="text-muted">Harga daftar: ...</small>`, tambahkan:

```blade
@if ($row['customer_price'] !== null)
    <small class="text-muted d-block">Harga pelanggan: {{ \App\Support\Format::rupiah($row['customer_price']) }}</small>
@endif
```

Lalu ganti seluruh blok pilihan harga, mulai dari `@if ($row['mode'] === 'existing' && $row['list_price'] !== null && ...` sampai `@endif` penutupnya, dengan:

```blade
                        @php($typedPrice = \App\Support\Parse::rupiah($row['unit_price']))
                        @php($referencePrice = $row['customer_price'] ?? $row['list_price'])
                        @if ($row['mode'] === 'existing' && $row['list_price'] !== null && is_int($typedPrice) && $typedPrice !== (int) $referencePrice)
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="pm-this-{{ $i }}" value="this_only" wire:model="items.{{ $i }}.price_mode">
                                    <label class="form-check-label" for="pm-this-{{ $i }}">Hanya nota ini</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="pm-new-{{ $i }}" value="new_price" wire:model="items.{{ $i }}.price_mode">
                                    <label class="form-check-label" for="pm-new-{{ $i }}">Jadikan harga baru</label>
                                </div>
                                @if ($customerMode !== 'none')
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="pm-customer-{{ $i }}" value="customer_price" wire:model="items.{{ $i }}.price_mode">
                                        <label class="form-check-label" for="pm-customer-{{ $i }}">Jadikan harga pelanggan</label>
                                    </div>
                                @endif
                                @error("items.$i.price_mode") <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        @endif
```

Label "Hanya nota ini" dan "Jadikan harga baru" tidak berubah. Tes lama mencarinya lewat label itu.

### 6. View detail transaksi

Di `resources/views/transactions/show.blade.php`, ubah blok keterangan harga item menjadi:

```blade
@if ($item->list_price === null)
    <span class="badge text-bg-warning">Barang baru</span>
@elseif ($item->customer_price !== null)
    <small class="text-muted">Harga pelanggan: {{ \App\Support\Format::rupiah($item->customer_price) }} · Harga umum: {{ \App\Support\Format::rupiah($item->list_price) }}</small>
@elseif ($item->list_price !== $item->unit_price)
    <small class="text-muted">Harga daftar: {{ \App\Support\Format::rupiah($item->list_price) }}</small>
@endif
```

Tanda `·` adalah titik tengah (U+00B7). Tes e2e mencari teks itu persis.

## Tes yang wajib ada

Buat `tests/Feature/CustomerPriceTransactionTest.php` dengan isi persis ini:

```php
<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CustomerPriceService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPriceTransactionTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    private CustomerPriceService $prices;

    private Product $gula;

    private Customer $sari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->service = app(TransactionService::class);
        $this->prices = app(CustomerPriceService::class);

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);
        $this->sari = Customer::factory()->create(['name' => 'Bu Sari']);
        $this->prices->set($this->sari->id, $this->gula->id, 17000, effectiveFrom: now()->subDay());
    }

    private function data(int $unitPrice, string $priceMode = 'this_only', ?array $customer = null): array
    {
        return [
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'unpaid',
            'customer' => $customer ?? ['id' => $this->sari->id],
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => $unitPrice, 'discount' => 0, 'price_mode' => $priceMode]],
        ];
    }

    // --- Layanan

    public function test_item_stores_customer_price_snapshot(): void
    {
        $transaction = $this->service->create($this->data(17000));

        $item = $transaction->items->first();
        $this->assertSame(17000, $item->customer_price);
        $this->assertSame(18000, $item->list_price);
        $this->assertSame(17000, $item->unit_price);
    }

    public function test_item_without_customer_has_no_customer_price(): void
    {
        $data = $this->data(18000);
        $data['customer'] = null;

        $this->assertNull($this->service->create($data)->items->first()->customer_price);
    }

    public function test_customer_price_mode_adds_customer_price_row(): void
    {
        $transaction = $this->service->create($this->data(16500, 'customer_price'));

        $this->assertSame(16500, $this->prices->priceAt($this->sari->id, $this->gula->id));
        $this->assertSame($transaction->id, CustomerPrice::latest('id')->first()->source_transaction_id);
        $this->assertSame(17000, $transaction->items->first()->customer_price);
        $this->assertSame(1, $this->gula->prices()->count());
    }

    public function test_customer_price_mode_works_for_new_customer(): void
    {
        $this->service->create($this->data(16000, 'customer_price', ['name' => 'Pak Budi', 'phone' => '0813-1111-2222']));

        $budi = Customer::where('name', 'Pak Budi')->firstOrFail();
        $this->assertSame(16000, $this->prices->priceAt($budi->id, $this->gula->id));
    }

    public function test_customer_price_mode_without_customer_is_rejected(): void
    {
        $data = $this->data(16500, 'customer_price');
        $data['customer'] = null;

        try {
            $this->service->create($data);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Pilih pelanggan dulu untuk menyimpan harga pelanggan.', $e->errors()['items.0.price_mode'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_this_only_mode_changes_no_price(): void
    {
        $this->service->create($this->data(15000));

        $this->assertSame(17000, $this->prices->priceAt($this->sari->id, $this->gula->id));
        $this->assertSame(1, $this->gula->prices()->count());
    }

    public function test_new_price_mode_still_changes_general_price(): void
    {
        $this->service->create($this->data(19000, 'new_price'));

        $this->assertSame(19000, $this->gula->fresh()->currentPrice->sell_price);
        $this->assertSame(17000, $this->prices->priceAt($this->sari->id, $this->gula->id));
    }

    public function test_detail_page_shows_customer_and_general_price(): void
    {
        $transaction = $this->service->create($this->data(17000));

        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee('Harga pelanggan: Rp17.000')
            ->assertSee('Harga umum: Rp18.000');
    }

    // --- Form

    public function test_selecting_product_after_customer_uses_customer_price(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->assertSet('items.0.customer_price', 17000)
            ->assertSet('items.0.list_price', 18000)
            ->assertSet('items.0.unit_price', '17.000')
            ->assertSee('Harga pelanggan: Rp17.000');
    }

    public function test_selecting_customer_after_product_updates_untouched_price(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->assertSet('items.0.unit_price', '18.000')
            ->call('selectCustomer', $this->sari->id)
            ->assertSet('items.0.unit_price', '17.000')
            ->call('clearCustomer')
            ->assertSet('items.0.unit_price', '18.000')
            ->assertSet('items.0.customer_price', null);
    }

    public function test_selecting_customer_keeps_price_typed_for_this_note(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '15.000')
            ->call('selectCustomer', $this->sari->id)
            ->assertSet('items.0.unit_price', '15.000')
            ->assertSet('items.0.customer_price', 17000);
    }

    public function test_customer_price_option_only_shown_with_customer(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '16.000')
            ->assertSee('Jadikan harga baru')
            ->assertDontSee('Jadikan harga pelanggan')
            ->call('selectCustomer', $this->sari->id)
            ->assertSee('Jadikan harga pelanggan');
    }

    public function test_price_options_hidden_when_price_equals_customer_price(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->assertDontSee('Hanya nota ini');
    }

    public function test_clearing_customer_resets_customer_price_mode(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '16.000')
            ->set('items.0.price_mode', 'customer_price')
            ->call('clearCustomer')
            ->assertSet('items.0.price_mode', 'this_only');
    }

    public function test_form_saves_customer_price_mode(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '16.500')
            ->set('items.0.price_mode', 'customer_price')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(16500, $this->prices->priceAt($this->sari->id, $this->gula->id));
    }

    public function test_edit_mode_loads_customer_price_for_rows(): void
    {
        $transaction = $this->service->create($this->data(17000));

        Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->assertSet('items.0.customer_price', 17000)
            ->assertSet('items.0.unit_price', '17.000');

        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
    }
}
```

Tambahkan tes ini di akhir `tests/e2e/harga-pelanggan.spec.js`. Fungsi `newProduct`, `newCustomer`, dan `noOverflow` sudah ada di file itu sejak tugas 17.

```js
test('harga pelanggan: dipakai di form transaksi dan bisa diperbarui dari nota', async ({ page }) => {
    const suffix = Date.now();
    const product = `Kopi Pelanggan ${suffix}`;
    const customer = `Pak Harga ${suffix}`;
    await newProduct(page, product, '20000');
    await newCustomer(page, customer);
    await page.getByLabel('Barang').selectOption({ label: `${product.toUpperCase()} (kg) - Rp20.000` });
    await page.getByLabel('Harga pelanggan').fill('19000');
    await page.getByRole('button', { name: 'Simpan harga khusus' }).click();
    await expect(page.getByText('Harga khusus berhasil disimpan.')).toBeVisible();

    // Barang dipilih dulu, lalu pelanggan: harga ikut berubah ke harga pelanggan.
    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(product);
    await page.getByRole('button', { name: new RegExp(product, 'i') }).first().click();
    await expect(page.getByLabel('Harga satuan')).toHaveValue('20.000');

    await page.getByPlaceholder(/Cari nama atau nomor HP/).fill(customer);
    await page.getByRole('button', { name: new RegExp(customer) }).click();
    await expect(page.getByLabel('Harga satuan')).toHaveValue('19.000');
    await expect(page.getByText('Harga pelanggan: Rp19.000')).toBeVisible();

    // Harga diubah lalu disimpan sebagai harga pelanggan baru.
    await page.getByLabel('Harga satuan').fill('18500');
    await page.getByLabel('Diskon').click();
    await page.getByLabel('Jadikan harga pelanggan').check();
    await noOverflow(page);
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText('Harga pelanggan: Rp19.000 · Harga umum: Rp20.000')).toBeVisible();

    await page.getByRole('link', { name: customer }).click();
    await expect(page.getByText('Rp18.500').first()).toBeVisible();
});
```

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

- [ ] `php artisan migrate` berhasil.
- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 16.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] Di browser HP: memilih pelanggan yang punya harga khusus, lalu barangnya, langsung mengisi harga pelanggan.
- [ ] Memilih barang dulu, lalu pelanggan, mengganti harga ke harga pelanggan. Menghapus pelanggan mengembalikan harga umum.
- [ ] Harga yang diketik sendiri tidak tertimpa saat pelanggan diganti.
- [ ] "Jadikan harga pelanggan" menyimpan harga baru, dan harga itu tampil di halaman pelanggan.
- [ ] Detail transaksi menampilkan harga pelanggan dan harga umum.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah arti `list_price`. Kolom itu tetap harga umum.
- Jangan membuat `CustomerPrice` di komponen Livewire atau controller. Semua lewat `CustomerPriceService`, dipanggil dari `TransactionService`.
- Jangan menimpa harga yang sudah diketik pemilik saat pelanggan diganti.
- Jangan menampilkan pilihan "Jadikan harga pelanggan" jika tidak ada pelanggan.
- Jangan mengubah halaman pelanggan. Itu sudah selesai di tugas 17.
- Jangan menghapus atau melemahkan tes agar lulus.

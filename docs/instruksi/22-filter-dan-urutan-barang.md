# Tugas 22: Filter dan Urutan di Daftar Barang

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 21 selesai.

## Tujuan

Daftar barang sekarang hanya punya pencarian nama atau kode dan filter status, dengan urutan nama A-Z. Setelah tugas ini:

| Bagian | Isi |
|---|---|
| Selalu tampil | Pencarian nama atau kode, Status, dan Urutkan |
| Panel "Filter lainnya" (bisa dibuka-tutup) | Satuan, Kategori, Harga minimal, Harga maksimal |
| Pilihan urutan | Nama A-Z (bawaan), Nama Z-A, Harga termurah, Harga termahal, Terbaru, Terlama, Paling laku |

Aturan:

- Panel "Filter lainnya" otomatis terbuka jika salah satu filter di dalamnya sedang dipakai.
- Pilihan kategori diambil dari kategori yang pernah diisi di barang. Tidak ada tabel kategori baru.
- Harga minimal dan maksimal memakai harga jual yang berlaku SEKARANG (`currentPrice`). Harga terjadwal di masa depan tidak dihitung. Barang tanpa harga tidak muncul jika filter harga dipakai.
- Kolom harga memakai titik ribuan (`data-rupiah`), sesuai aturan kolom uang dari tugas 13.
- "Paling laku" mengurutkan jumlah terjual dari transaksi `confirmed` saja. Draf dan transaksi batal tidak dihitung. Saat urutan ini dipilih, setiap baris menampilkan `Terjual N`.
- Urutan selain nama memakai nama A-Z sebagai urutan kedua, agar hasilnya tetap.
- Filter dan urutan terbawa saat pindah halaman (`withQueryString()` yang sudah ada).
- Nilai tidak dikenal untuk `unit` atau `sort` ditolak validasi.

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 04, `ProductController::index` | Parameter `q` dan `status`, urut nama | Ditambah `unit`, `category`, `min_price`, `max_price`, `sort` |
| 04, view `products/index` | Form satu baris: cari, status, tombol Cari | Form di dalam kartu dengan panel "Filter lainnya" yang bisa dibuka-tutup |

Panel dibuka-tutup dengan fitur `collapse` bawaan Bootstrap (`data-bs-toggle="collapse"`). Tidak ada JavaScript baru. Tidak ada rute, kolom, tabel, atau paket baru.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `tests/Feature/ProductListFilterTest.php`
- `tests/e2e/filter-barang.spec.js`

## Yang diubah

- `app/Http/Controllers/ProductController.php` (method `index` dan konstanta baru `SORTS`)
- `resources/views/products/index.blade.php`

## Langkah

### 1. ProductController

Tambahkan `use App\Models\ProductPrice;` di bagian atas. `TransactionStatus`, `TransactionItem`, `Parse`, dan `Rule` sudah di-`use`.

Ganti method `index` dengan konstanta dan method berikut:

```php
/**
 * Pilihan urutan di daftar barang: nilai di URL => label di layar.
 */
public const SORTS = [
    'name_asc' => 'Nama A-Z',
    'name_desc' => 'Nama Z-A',
    'price_asc' => 'Harga termurah',
    'price_desc' => 'Harga termahal',
    'newest' => 'Terbaru',
    'oldest' => 'Terlama',
    'best_selling' => 'Paling laku',
];

public function index(Request $request): View
{
    $request->merge([
        'min_price' => Parse::rupiah($request->query('min_price')),
        'max_price' => Parse::rupiah($request->query('max_price')),
    ]);

    $validated = $request->validate([
        'q' => ['nullable', 'string', 'max:255'],
        'status' => ['nullable', Rule::enum(ProductStatus::class)],
        'unit' => ['nullable', Rule::in(config('shop.units'))],
        'category' => ['nullable', 'string', 'max:255'],
        'min_price' => ['nullable', 'integer', 'min:0'],
        'max_price' => ['nullable', 'integer', 'min:0'],
        'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
    ]);

    $query = Product::query()->select('products.*')->with('currentPrice');

    if (! empty($validated['status'])) {
        $query->where('status', $validated['status']);
    } else {
        $query->where('status', '!=', ProductStatus::Merged);
    }

    if (! empty($validated['q'])) {
        $term = '%'.$validated['q'].'%';
        $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term));
    }

    if (! empty($validated['unit'])) {
        $query->where('unit', $validated['unit']);
    }

    if (! empty($validated['category'])) {
        $query->where('category', $validated['category']);
    }

    if (isset($validated['min_price'])) {
        $query->whereHas('currentPrice', fn ($q) => $q->where('sell_price', '>=', $validated['min_price']));
    }

    if (isset($validated['max_price'])) {
        $query->whereHas('currentPrice', fn ($q) => $q->where('sell_price', '<=', $validated['max_price']));
    }

    $sort = $validated['sort'] ?? 'name_asc';
    $currentSellPrice = ProductPrice::query()
        ->select('sell_price')
        ->whereColumn('product_id', 'products.id')
        ->where('effective_from', '<=', now())
        ->orderByDesc('effective_from')
        ->orderByDesc('id')
        ->limit(1);
    $soldQuantity = TransactionItem::query()
        ->selectRaw('COALESCE(SUM(quantity), 0)')
        ->whereColumn('product_id', 'products.id')
        ->whereHas('transaction', fn ($q) => $q->where('status', TransactionStatus::Confirmed));

    match ($sort) {
        'name_desc' => $query->orderByDesc('name'),
        'price_asc' => $query->orderBy($currentSellPrice),
        'price_desc' => $query->orderByDesc($currentSellPrice),
        'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
        'oldest' => $query->orderBy('created_at')->orderBy('id'),
        'best_selling' => $query->addSelect(['sold_quantity' => $soldQuantity])->orderByDesc($soldQuantity),
        default => $query->orderBy('name'),
    };

    if ($sort !== 'name_asc' && $sort !== 'name_desc') {
        $query->orderBy('name');
    }

    $filtersOpen = ! empty($validated['unit']) || ! empty($validated['category'])
        || isset($validated['min_price']) || isset($validated['max_price']);

    return view('products.index', [
        'products' => $query->paginate(20)->withQueryString(),
        'statuses' => [ProductStatus::Active, ProductStatus::NeedsReview, ProductStatus::Inactive],
        'categories' => Product::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
        'sorts' => self::SORTS,
        'sort' => $sort,
        'filtersOpen' => $filtersOpen,
    ]);
}
```

Penjelasan:

- `select('products.*')` wajib, agar `addSelect` untuk "Paling laku" tidak menghapus kolom barang.
- Urutan harga memakai subquery `$currentSellPrice`, yang sama dengan aturan harga berlaku (`effective_from` terbaru yang tidak melewati sekarang). Jangan memakai `join` ke `product_prices`, karena satu barang punya banyak baris harga.
- `COALESCE(SUM(quantity), 0)` membuat barang yang belum pernah terjual bernilai 0, bukan `null`. Fungsi ini ada di SQLite dan MySQL.
- Jangan memakai SQL khusus satu database.

### 2. View daftar barang

Di `resources/views/products/index.blade.php`, ganti seluruh `<form method="GET" ...> ... </form>` dengan:

```blade
    <form method="GET" action="{{ route('products.index') }}" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-12">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau kode" aria-label="Cari">
            </div>
            <div class="col-6">
                <select name="status" class="form-select" aria-label="Status">
                    <option value="">Semua</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <select name="sort" class="form-select" aria-label="Urutkan">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="collapse {{ $filtersOpen ? 'show' : '' }}" id="productFilters">
            <div class="row g-2 mt-0">
                <div class="col-6">
                    <label for="filter-unit" class="form-label small mb-0">Satuan</label>
                    <select id="filter-unit" name="unit" class="form-select">
                        <option value="">Semua satuan</option>
                        @foreach (config('shop.units') as $unit)
                            <option value="{{ $unit }}" @selected(request('unit') === $unit)>{{ $unit }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label for="filter-category" class="form-label small mb-0">Kategori</label>
                    <select id="filter-category" name="category" class="form-select">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label for="filter-min-price" class="form-label small mb-0">Harga minimal</label>
                    <input type="text" id="filter-min-price" name="min_price" value="{{ \App\Support\Format::thousands(request('min_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('min_price') is-invalid @enderror">
                    @error('min_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label for="filter-max-price" class="form-label small mb-0">Harga maksimal</label>
                    <input type="text" id="filter-max-price" name="max_price" value="{{ \App\Support\Format::thousands(request('max_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('max_price') is-invalid @enderror">
                    @error('max_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center mt-2">
            <button type="submit" class="btn btn-outline-primary">Cari</button>
            <button type="button" class="btn btn-link px-0" data-bs-toggle="collapse" data-bs-target="#productFilters" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" aria-controls="productFilters">Filter lainnya</button>
            <a href="{{ route('products.index') }}" class="ms-auto">Reset</a>
        </div>
    </form>
```

Lalu di daftar barang, tepat di bawah baris `<small class="text-muted">{{ $product->unit }}</small>`, tambahkan:

```blade
@if ($sort === 'best_selling')
    <small class="text-muted">· Terjual {{ \App\Support\Format::quantity($product->sold_quantity) }}</small>
@endif
```

Aturan:

- Kolom pencarian tetap bernama `q` dengan placeholder `Cari nama atau kode`, dan tombolnya tetap "Cari". Tes lama memakainya.
- Label kolom di panel harus persis "Satuan", "Kategori", "Harga minimal", "Harga maksimal". `aria-label` select urutan harus "Urutkan". Tes e2e mencari lewat label itu.

## Tes yang wajib ada

Buat `tests/Feature/ProductListFilterTest.php` dengan isi persis ini:

```php
<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function product(string $name, string $unit, ?int $price, array $extra = []): Product
    {
        $product = Product::factory()->create(array_merge(['name' => $name, 'unit' => $unit], $extra));

        if ($price !== null) {
            ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'effective_from' => now()->subDay()]);
        }

        return $product;
    }

    private function sell(Product $product, int|string $quantity, TransactionStatus $status = TransactionStatus::Confirmed): void
    {
        app(TransactionService::class)->create([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 1000, 'discount' => 0]],
        ], $status);
    }

    public function test_filter_by_unit(): void
    {
        $this->product('GULA', 'kg', 18000);
        $this->product('SABUN', 'pcs', 4000);

        $this->get('/products?unit=kg')->assertOk()->assertSee('GULA')->assertDontSee('SABUN');
    }

    public function test_unit_outside_config_is_rejected(): void
    {
        $this->get('/products?unit=ton')->assertSessionHasErrors('unit');
    }

    public function test_filter_by_category_and_category_options(): void
    {
        $this->product('GULA', 'kg', 18000, ['category' => 'Sembako']);
        $this->product('SABUN', 'pcs', 4000, ['category' => 'Kebersihan']);
        $this->product('TANPA KATEGORI', 'pcs', 1000, ['category' => null]);

        $this->get('/products')
            ->assertOk()
            ->assertSee('<option value="Kebersihan"', false)
            ->assertSee('<option value="Sembako"', false);

        $this->get('/products?category=Sembako')->assertOk()->assertSee('GULA')->assertDontSee('SABUN')->assertDontSee('TANPA KATEGORI');
    }

    public function test_filter_by_price_range_with_thousand_dots(): void
    {
        $this->product('MURAH', 'pcs', 2000);
        $this->product('SEDANG', 'pcs', 15000);
        $this->product('MAHAL', 'pcs', 90000);
        $this->product('TANPA HARGA', 'pcs', null);

        $this->get('/products?min_price=10.000&max_price=50.000')
            ->assertOk()
            ->assertSee('SEDANG')
            ->assertDontSee('MURAH')
            ->assertDontSee('MAHAL')
            ->assertDontSee('TANPA HARGA')
            ->assertSee('value="10.000"', false);
    }

    public function test_price_filter_uses_current_price_not_future_price(): void
    {
        $gula = $this->product('GULA', 'kg', 18000);
        ProductPrice::factory()->for($gula)->create(['sell_price' => 25000, 'effective_from' => now()->addDay()]);

        $this->get('/products?min_price=20000')->assertOk()->assertDontSee('GULA');
        $this->get('/products?max_price=18000')->assertOk()->assertSee('GULA');
    }

    public function test_sort_by_name(): void
    {
        $this->product('BERAS', 'kg', 12000);
        $this->product('APEL', 'kg', 30000);
        $this->product('CABAI', 'kg', 50000);

        $this->get('/products')->assertSeeInOrder(['APEL', 'BERAS', 'CABAI']);
        $this->get('/products?sort=name_desc')->assertSeeInOrder(['CABAI', 'BERAS', 'APEL']);
    }

    public function test_sort_by_price(): void
    {
        $this->product('BERAS', 'kg', 12000);
        $this->product('APEL', 'kg', 30000);
        $this->product('CABAI', 'kg', 5000);

        $this->get('/products?sort=price_asc')->assertSeeInOrder(['CABAI', 'BERAS', 'APEL']);
        $this->get('/products?sort=price_desc')->assertSeeInOrder(['APEL', 'BERAS', 'CABAI']);
    }

    public function test_sort_by_created_date(): void
    {
        $this->product('LAMA', 'kg', 1000, ['created_at' => now()->subDays(3)]);
        $this->product('BARU', 'kg', 1000, ['created_at' => now()->subDay()]);
        $this->product('TENGAH', 'kg', 1000, ['created_at' => now()->subDays(2)]);

        $this->get('/products?sort=newest')->assertSeeInOrder(['BARU', 'TENGAH', 'LAMA']);
        $this->get('/products?sort=oldest')->assertSeeInOrder(['LAMA', 'TENGAH', 'BARU']);
    }

    public function test_sort_by_best_selling_counts_only_confirmed_transactions(): void
    {
        $gula = $this->product('GULA', 'kg', 18000);
        $kopi = $this->product('KOPI', 'pcs', 2000);
        $teh = $this->product('TEH', 'pak', 8000);

        $this->sell($gula, '2,5');
        $this->sell($kopi, 3);
        $this->sell($teh, 10, TransactionStatus::Draft);

        $this->get('/products?sort=best_selling')
            ->assertOk()
            ->assertSeeInOrder(['KOPI', 'Terjual 3', 'GULA', 'Terjual 2,5', 'TEH', 'Terjual 0']);
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $this->get('/products?sort=acak')->assertSessionHasErrors('sort');
    }

    public function test_extra_filters_open_only_when_used(): void
    {
        $this->get('/products')->assertOk()->assertDontSee('collapse show', false);
        $this->get('/products?unit=kg')->assertOk()->assertSee('collapse show', false);
    }

    public function test_pagination_keeps_filters_and_sort(): void
    {
        foreach (range(1, 21) as $i) {
            $this->product(sprintf('BARANG %02d', $i), 'kg', 1000 * $i);
        }

        $this->get('/products?unit=kg&sort=price_desc')
            ->assertOk()
            ->assertSee('unit=kg', false)
            ->assertSee('sort=price_desc', false)
            ->assertSee('page=2', false);
    }
}
```

Buat `tests/e2e/filter-barang.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

async function newProduct(page, name, unit, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption(unit);
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

test('daftar barang: filter satuan dan rentang harga, lalu urutkan', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Filter Beras ${suffix}`, 'kg', '12000');
    await newProduct(page, `Filter Gula ${suffix}`, 'kg', '18000');
    await newProduct(page, `Filter Sabun ${suffix}`, 'pcs', '4000');

    await page.goto('/products');
    await page.getByPlaceholder('Cari nama atau kode').fill(String(suffix));

    await page.getByRole('button', { name: 'Filter lainnya' }).click();
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga minimal').pressSequentially('15000');
    await expect(page.getByLabel('Harga minimal')).toHaveValue('15.000');
    await page.getByRole('button', { name: 'Cari' }).click();

    await expect(page.getByText(`FILTER GULA ${suffix}`)).toBeVisible();
    await expect(page.getByText(`FILTER BERAS ${suffix}`)).toHaveCount(0);
    await expect(page.getByText(`FILTER SABUN ${suffix}`)).toHaveCount(0);
    // Panel filter tetap terbuka karena ada filter yang dipakai.
    await expect(page.getByLabel('Satuan')).toHaveValue('kg');

    await page.getByRole('link', { name: 'Reset' }).click();
    await page.getByPlaceholder('Cari nama atau kode').fill(String(suffix));
    await page.getByLabel('Urutkan').selectOption('price_desc');
    await page.getByRole('button', { name: 'Cari' }).click();

    const names = await page.locator('.list-group-item .fw-semibold').allTextContents();
    expect(names).toEqual([`FILTER GULA ${suffix}`, `FILTER BERAS ${suffix}`, `FILTER SABUN ${suffix}`]);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});
```

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 12.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] Di browser HP: "Filter lainnya" membuka dan menutup panel. Filter satuan, kategori, dan harga bekerja. Setiap pilihan urutan bekerja.
- [ ] Setelah filter dipakai dan halaman dimuat ulang, panel tetap terbuka dan isian tetap ada.
- [ ] "Reset" mengembalikan daftar ke semula.
- [ ] Halaman rapi di lebar 375px.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan membuat tabel kategori.
- Jangan memakai Livewire atau JavaScript sendiri di halaman barang.
- Jangan mengubah halaman lain.
- Jangan menghapus atau melemahkan tes agar lulus.

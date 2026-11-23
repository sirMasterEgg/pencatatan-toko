# Tugas 19: Ubah Harga Massal

Baca dulu: `00-aturan-umum.md` dan bagian "Aturan harga pelanggan" di `17-harga-pelanggan.md`
Prasyarat: tugas 01 sampai 18 selesai.

## Tujuan

Saat harga dari pemasok naik, pemilik perlu menaikkan harga banyak barang sekaligus. Setelah tugas ini, ada halaman "Ubah harga massal" (tombol di halaman daftar barang):

1. Pemilik mencari dan mencentang barang yang akan diubah. Ada kotak "Pilih semua".
2. Pemilik memilih arah ("Naik" atau "Turun"), mengisi perubahan harga jual dalam rupiah, dan boleh mengisi perubahan harga modal.
3. Pemilik memilih tanggal "Berlaku mulai" (bawaan: sekarang, boleh tanggal ke depan).
4. Centang "Ubah juga harga pelanggan" (bawaan tercentang) membuat harga pelanggan untuk barang terpilih ikut berubah sebesar angka yang sama.
5. Tombol "Lihat pratinjau" menampilkan harga lama → harga baru per barang. Belum ada yang disimpan.
6. Tombol "Simpan perubahan harga" menyimpan semuanya sekaligus.

## Aturan

| Hal | Aturan |
|---|---|
| Cara menyimpan | Menambah baris baru di `product_prices` (dan `customer_prices` jika dicentang). Baris lama tidak diubah, sesuai aturan bisnis 2 |
| Barang yang bisa dipilih | Status `active` atau `needs_review` yang sudah punya harga |
| Harga dasar | Harga yang berlaku pada waktu "Berlaku mulai", bukan selalu harga hari ini. Jadi jika sudah ada harga terjadwal, perubahan dihitung dari harga itu |
| Arah | "Naik" menambah, "Turun" mengurangi. Angka yang diisi selalu positif. Tidak ada isian minus, karena papan ketik angka di HP sering tanpa tanda minus dan skrip titik ribuan dari tugas 13 membuang tanda minus |
| Harga modal | Kosong berarti harga modal tetap (disalin dari harga dasar). Jika diisi, harga modal berubah sebesar angka itu dengan arah yang sama. Barang yang belum punya harga modal tetap tanpa harga modal |
| Harga pelanggan | Jika dicentang, setiap harga pelanggan yang berlaku untuk barang terpilih ikut berubah sebesar perubahan harga jual. Harga pelanggan yang sudah dihentikan tidak dihidupkan lagi |
| Di bawah Rp0 | Jika ada harga jual, harga modal, atau harga pelanggan yang akan menjadi di bawah Rp0, seluruh perubahan ditolak dengan pesan per barang. Tidak ada yang tersimpan |
| Berlaku mulai | Waktu yang sudah lewat dianggap sekarang. Harga tidak pernah berlaku mundur |
| Catatan riwayat | Setiap baris baru diberi catatan `Perubahan harga massal +Rp500` atau `Perubahan harga massal -Rp500` |
| Utuh | Semua baris disimpan di dalam satu `DB::transaction` |

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 04, `ProductService::addPrice` | `effective_from` selalu `now()` | Parameter terakhir baru `?CarbonInterface $effectiveFrom = null`. Bawaan tetap `now()`, jadi pemanggil lama tidak berubah |
| 04, halaman daftar barang | Satu tombol "Tambah barang" | Ditambah tombol "Ubah harga massal" di sebelahnya |
| 04, "Jangan memakai Livewire di halaman barang" | Berlaku | Tetap berlaku. Halaman ini memakai form biasa |
| 07 dan 13, JavaScript | Hanya `resources/js/rupiah.js` | Ditambah `resources/js/check-all.js` untuk kotak "Pilih semua", tanpa paket baru |
| 00, daftar rute | Tidak ada rute ubah harga massal | Ditambah tiga rute di bawah, didaftarkan SEBELUM `Route::resource('products', ...)` |

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `app/Services/BulkPriceService.php`
- `app/Http/Controllers/BulkPriceController.php`
- `resources/js/check-all.js`
- `resources/views/products/bulk-price.blade.php`
- `resources/views/products/bulk-price-preview.blade.php`
- `tests/Feature/BulkPriceTest.php`
- `tests/e2e/harga-massal.spec.js`

## Yang diubah

- `app/Services/ProductService.php` (hanya `addPrice`)
- `resources/js/app.js`
- `routes/web.php`
- `resources/views/products/index.blade.php`
- `README.md` di akar proyek

## Langkah

### 1. ProductService::addPrice

Tambahkan `use Carbon\CarbonInterface;` di bagian atas. Ubah `addPrice` menjadi:

```php
public function addPrice(
    Product $product,
    int $sellPrice,
    ?int $costPrice = null,
    ?string $note = null,
    ?int $sourceTransactionId = null,
    ?CarbonInterface $effectiveFrom = null,
): ProductPrice {
    return $product->prices()->create([
        'sell_price' => $sellPrice,
        'cost_price' => $costPrice,
        'effective_from' => $effectiveFrom ?? now(),
        'source_transaction_id' => $sourceTransactionId,
        'note' => $note,
    ]);
}
```

### 2. BulkPriceService

Buat `app/Services/BulkPriceService.php` dengan isi persis ini:

```php
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkPriceService
{
    public function __construct(
        private ProductService $products,
        private CustomerPriceService $customerPrices,
    ) {}

    /**
     * Hitung harga lama dan baru untuk setiap barang terpilih, tanpa menyimpan apa pun.
     * Barang yang tidak aktif atau belum punya harga dilewati.
     *
     * @param  array<int>  $productIds
     * @return array<int, array{product: Product, old_sell: int, new_sell: int, old_cost: ?int, new_cost: ?int, customer_prices: array<int, array{old: int, new: int}>}>
     */
    public function preview(array $productIds, int $sellChange, ?int $costChange, CarbonInterface $effectiveFrom, bool $includeCustomerPrices): array
    {
        $effectiveFrom = $this->notInPast($effectiveFrom);
        $rows = [];

        $products = Product::selectable()->whereKey($productIds)->orderBy('name')->get();

        foreach ($products as $product) {
            $base = $this->priceAt($product, $effectiveFrom);

            if ($base === null) {
                continue;
            }

            $customerPrices = [];
            if ($includeCustomerPrices) {
                foreach ($this->customerPrices->activeForProduct($product->id, $effectiveFrom) as $customerId => $price) {
                    $customerPrices[$customerId] = ['old' => $price, 'new' => $price + $sellChange];
                }
            }

            $rows[] = [
                'product' => $product,
                'old_sell' => $base->sell_price,
                'new_sell' => $base->sell_price + $sellChange,
                'old_cost' => $base->cost_price,
                'new_cost' => $base->cost_price === null || $costChange === null ? $base->cost_price : $base->cost_price + $costChange,
                'customer_prices' => $customerPrices,
            ];
        }

        return $rows;
    }

    /**
     * Lempar ValidationException jika ada harga yang akan menjadi di bawah Rp0.
     */
    public function assertValid(array $rows): void
    {
        $errors = [];

        foreach ($rows as $row) {
            $name = $row['product']->name;

            if ($row['new_sell'] < 0) {
                $errors[] = "Harga jual {$name} akan menjadi di bawah Rp0.";
            }
            if ($row['new_cost'] !== null && $row['new_cost'] < 0) {
                $errors[] = "Harga modal {$name} akan menjadi di bawah Rp0.";
            }
            foreach ($row['customer_prices'] as $price) {
                if ($price['new'] < 0) {
                    $errors[] = "Harga pelanggan {$name} akan menjadi di bawah Rp0.";
                    break;
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['product_ids' => $errors]);
        }
    }

    /**
     * Simpan perubahan harga. Mengembalikan jumlah barang yang harganya diubah.
     */
    public function apply(array $productIds, int $sellChange, ?int $costChange, CarbonInterface $effectiveFrom, bool $includeCustomerPrices): int
    {
        $effectiveFrom = $this->notInPast($effectiveFrom);
        $rows = $this->preview($productIds, $sellChange, $costChange, $effectiveFrom, $includeCustomerPrices);
        $this->assertValid($rows);

        $sign = $sellChange >= 0 ? '+' : '-';
        $note = 'Perubahan harga massal '.$sign.Format::rupiah(abs($sellChange));

        DB::transaction(function () use ($rows, $note, $effectiveFrom) {
            foreach ($rows as $row) {
                $this->products->addPrice($row['product'], $row['new_sell'], $row['new_cost'], $note, null, $effectiveFrom);

                foreach ($row['customer_prices'] as $customerId => $price) {
                    $this->customerPrices->set($customerId, $row['product']->id, $price['new'], $note, null, $effectiveFrom);
                }
            }
        });

        return count($rows);
    }

    /**
     * Harga tidak boleh berlaku mundur. Waktu yang sudah lewat diganti dengan sekarang.
     */
    private function notInPast(CarbonInterface $effectiveFrom): CarbonInterface
    {
        return $effectiveFrom->isPast() ? now() : $effectiveFrom;
    }

    /**
     * Baris harga umum yang berlaku pada waktu tertentu.
     */
    private function priceAt(Product $product, CarbonInterface $at): ?ProductPrice
    {
        return $product->prices()
            ->where('effective_from', '<=', $at)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
```

Penjelasan:

- Service ini tidak membuat `ProductPrice` atau `CustomerPrice` sendiri. Ia memanggil `ProductService::addPrice` dan `CustomerPriceService::set`, yang tetap menjadi satu-satunya tempat membuat baris harga.
- `preview` dan `apply` memakai perhitungan yang sama. `apply` menghitung ulang dari database, tidak memakai angka dari halaman pratinjau.

### 3. BulkPriceController

Buat `app/Http/Controllers/BulkPriceController.php` dengan isi persis ini:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\BulkPriceService;
use App\Support\Parse;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BulkPriceController extends Controller
{
    public function __construct(private BulkPriceService $bulk) {}

    public function create(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $query = Product::selectable()->with('currentPrice')->has('currentPrice');

        if ($q !== '') {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%'));
        }

        return view('products.bulk-price', [
            'products' => $query->orderBy('name')->get(),
        ]);
    }

    public function preview(Request $request): View
    {
        $input = $this->validated($request);
        $rows = $this->bulk->preview(...$input);

        if ($rows === []) {
            throw ValidationException::withMessages(['product_ids' => 'Barang terpilih belum punya harga.']);
        }

        $this->bulk->assertValid($rows);

        return view('products.bulk-price-preview', [
            'rows' => $rows,
            'input' => $request->only(['product_ids', 'direction', 'sell_amount', 'cost_amount', 'effective_from', 'include_customer_prices']),
            'sellChange' => $input['sellChange'],
            'costChange' => $input['costChange'],
            'effectiveFrom' => $input['effectiveFrom']->isPast() ? now() : $input['effectiveFrom'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $this->validated($request);
        $count = $this->bulk->apply(...$input);

        if ($count === 0) {
            throw ValidationException::withMessages(['product_ids' => 'Barang terpilih belum punya harga.']);
        }

        $message = $input['effectiveFrom']->isFuture()
            ? "Harga {$count} barang akan berubah mulai {$input['effectiveFrom']->format('d/m/Y H:i')}."
            : "Harga {$count} barang berhasil diubah.";

        return redirect()->route('products.index')->with('success', $message);
    }

    /**
     * @return array{productIds: array<int>, sellChange: int, costChange: ?int, effectiveFrom: Carbon, includeCustomerPrices: bool}
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'sell_amount' => Parse::rupiah($request->input('sell_amount')),
            'cost_amount' => Parse::rupiah($request->input('cost_amount')),
        ]);

        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'direction' => ['required', 'in:up,down'],
            'sell_amount' => ['required', 'integer', 'min:1'],
            'cost_amount' => ['nullable', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'include_customer_prices' => ['boolean'],
        ], [
            'product_ids.required' => 'Pilih minimal satu barang.',
        ], [
            'sell_amount' => 'perubahan harga jual',
            'cost_amount' => 'perubahan harga modal',
            'effective_from' => 'berlaku mulai',
        ]);

        $sign = $data['direction'] === 'down' ? -1 : 1;

        return [
            'productIds' => array_map('intval', $data['product_ids']),
            'sellChange' => $sign * (int) $data['sell_amount'],
            'costChange' => isset($data['cost_amount']) ? $sign * (int) $data['cost_amount'] : null,
            'effectiveFrom' => Carbon::parse($data['effective_from']),
            'includeCustomerPrices' => (bool) ($data['include_customer_prices'] ?? false),
        ];
    }
}
```

Penjelasan:

- `$this->bulk->preview(...$input)` meneruskan array berkunci nama sebagai argumen bernama. Kunci `productIds`, `sellChange`, `costChange`, `effectiveFrom`, dan `includeCustomerPrices` harus sama persis dengan nama parameter di `BulkPriceService`.
- Kolom uang dirapikan dengan `Parse::rupiah`, sesuai aturan dari tugas 13.
- Jika pratinjau gagal validasi, pengguna kembali ke halaman pilih barang dengan centang dan isian sebelumnya.

### 4. JavaScript "Pilih semua"

Buat `resources/js/check-all.js` dengan isi persis ini:

```js
// Kotak centang bertanda data-check-all="<selektor>" mencentang atau mengosongkan semua kotak yang cocok.
document.addEventListener('change', (event) => {
    const source = event.target;

    if (!(source instanceof HTMLInputElement) || !source.matches('[data-check-all]')) {
        return;
    }

    document.querySelectorAll(source.dataset.checkAll).forEach((box) => {
        box.checked = source.checked;
    });
});
```

Isi `resources/js/app.js` menjadi:

```js
import 'bootstrap';
import './rupiah';
import './check-all';
```

### 5. Rute

Di `routes/web.php`, tambahkan `use App\Http\Controllers\BulkPriceController;` di bagian atas. Tambahkan tiga rute ini TEPAT DI ATAS `Route::resource('products', ProductController::class)->except(['destroy']);`:

```php
Route::get('/products/bulk-price', [BulkPriceController::class, 'create'])->name('products.bulk-price.create');
Route::post('/products/bulk-price/preview', [BulkPriceController::class, 'preview'])->name('products.bulk-price.preview');
Route::post('/products/bulk-price', [BulkPriceController::class, 'store'])->name('products.bulk-price.store');
```

Urutan ini wajib. Jika didaftarkan setelah `Route::resource`, `/products/bulk-price` dianggap sebagai `/products/{product}` dan menghasilkan 404.

### 6. View halaman pilih barang

Buat `resources/views/products/bulk-price.blade.php` dengan isi persis ini:

```blade
@extends('layouts.app')

@section('title', 'Ubah harga massal')

@section('content')
    <h1 class="h4 mb-3">Ubah harga massal</h1>

    <form method="GET" action="{{ route('products.bulk-price.create') }}" class="d-flex gap-2 mb-3">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau kode" aria-label="Cari">
        <button type="submit" class="btn btn-outline-primary">Cari</button>
    </form>

    @if ($errors->has('product_ids'))
        <div class="alert alert-danger">
            @foreach ($errors->get('product_ids') as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    @if ($products->isEmpty())
        <p class="text-muted">Tidak ada barang yang cocok.</p>
    @else
        <form method="POST" action="{{ route('products.bulk-price.preview') }}">
            @csrf
            <div class="card mb-3">
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check-all" data-check-all="input[name='product_ids[]']">
                        <label class="form-check-label fw-semibold" for="check-all">Pilih semua ({{ $products->count() }})</label>
                    </div>
                    <div class="list-group">
                        @foreach ($products as $product)
                            <label class="list-group-item d-flex gap-2 align-items-start">
                                <input class="form-check-input mt-1" type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, array_map('intval', old('product_ids', [])), true))>
                                <span class="flex-fill">
                                    <span class="fw-semibold">{{ $product->name }}</span>
                                    <small class="text-muted">({{ $product->unit }})</small>
                                </span>
                                <span>{{ \App\Support\Format::rupiah($product->currentPrice->sell_price) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-2">
                        <label for="direction" class="form-label">Arah perubahan</label>
                        <select id="direction" name="direction" class="form-select @error('direction') is-invalid @enderror">
                            <option value="up" @selected(old('direction', 'up') === 'up')>Naik</option>
                            <option value="down" @selected(old('direction') === 'down')>Turun</option>
                        </select>
                        @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label for="sell_amount" class="form-label">Perubahan harga jual</label>
                        <input type="text" id="sell_amount" name="sell_amount" value="{{ \App\Support\Format::thousands(old('sell_amount')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('sell_amount') is-invalid @enderror" required>
                        @error('sell_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label for="cost_amount" class="form-label">Perubahan harga modal</label>
                        <input type="text" id="cost_amount" name="cost_amount" value="{{ \App\Support\Format::thousands(old('cost_amount')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('cost_amount') is-invalid @enderror">
                        @error('cost_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Kosongkan jika harga modal tetap.</div>
                    </div>
                    <div class="mb-2">
                        <label for="effective_from" class="form-label">Berlaku mulai</label>
                        <input type="datetime-local" id="effective_from" name="effective_from" value="{{ old('effective_from', now()->format('Y-m-d\TH:i')) }}" class="form-control @error('effective_from') is-invalid @enderror" required>
                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="include_customer_prices" value="0">
                        <input class="form-check-input" type="checkbox" id="include_customer_prices" name="include_customer_prices" value="1" @checked(old('include_customer_prices', '1') === '1')>
                        <label class="form-check-label" for="include_customer_prices">Ubah juga harga pelanggan</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Lihat pratinjau</button>
                </div>
            </div>
        </form>
    @endif
@endsection
```

Penjelasan:

- Daftar barang tidak memakai pagination, agar centang tidak hilang saat pindah halaman. Pakai pencarian untuk mempersempit daftar.
- Input tersembunyi `include_customer_prices` bernilai `0` sebelum kotak centang. Jika kotak tidak dicentang, nilai `0` yang terkirim, sehingga pilihan "tidak dicentang" tetap teringat saat validasi gagal.

### 7. View pratinjau

Buat `resources/views/products/bulk-price-preview.blade.php` dengan isi persis ini:

```blade
@extends('layouts.app')

@section('title', 'Pratinjau perubahan harga')

@section('content')
    <h1 class="h4 mb-3">Pratinjau perubahan harga</h1>

    <div class="alert alert-info">
        Harga jual {{ $sellChange >= 0 ? 'naik' : 'turun' }} {{ \App\Support\Format::rupiah(abs($sellChange)) }}.
        @if ($costChange === null)
            Harga modal tetap.
        @else
            Harga modal {{ $costChange >= 0 ? 'naik' : 'turun' }} {{ \App\Support\Format::rupiah(abs($costChange)) }}.
        @endif
        Berlaku mulai {{ $effectiveFrom->format('d/m/Y H:i') }}.
    </div>

    <ul class="list-group mb-3">
        @foreach ($rows as $row)
            <li class="list-group-item">
                <div class="fw-semibold">{{ $row['product']->name }} <small class="text-muted">({{ $row['product']->unit }})</small></div>
                <div>Harga jual: {{ \App\Support\Format::rupiah($row['old_sell']) }} → <strong>{{ \App\Support\Format::rupiah($row['new_sell']) }}</strong></div>
                @if ($row['old_cost'] !== null)
                    <div class="small text-muted">Harga modal: {{ \App\Support\Format::rupiah($row['old_cost']) }} → {{ \App\Support\Format::rupiah($row['new_cost']) }}</div>
                @endif
                @if (count($row['customer_prices']) > 0)
                    <div class="small text-muted">Harga pelanggan ikut berubah: {{ count($row['customer_prices']) }} pelanggan</div>
                @endif
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('products.bulk-price.store') }}">
        @csrf
        @foreach ($input['product_ids'] as $productId)
            <input type="hidden" name="product_ids[]" value="{{ $productId }}">
        @endforeach
        <input type="hidden" name="direction" value="{{ $input['direction'] }}">
        <input type="hidden" name="sell_amount" value="{{ $input['sell_amount'] }}">
        <input type="hidden" name="cost_amount" value="{{ $input['cost_amount'] }}">
        <input type="hidden" name="effective_from" value="{{ $input['effective_from'] }}">
        <input type="hidden" name="include_customer_prices" value="{{ $input['include_customer_prices'] ?? '0' }}">
        <button type="submit" class="btn btn-primary w-100 mb-2">Simpan perubahan harga ({{ count($rows) }} barang)</button>
        <a href="{{ route('products.bulk-price.create') }}" class="btn btn-outline-secondary w-100">Batal</a>
    </form>
@endsection
```

Pratinjau memakai `list-group`, bukan tabel, agar tidak perlu digeser ke samping di layar 375px.

### 8. Tombol di daftar barang

Di `resources/views/products/index.blade.php`, ubah bagian judul menjadi:

```blade
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Barang</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('products.bulk-price.create') }}" class="btn btn-outline-primary btn-sm">Ubah harga massal</a>
        <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">Tambah barang</a>
    </div>
</div>
```

### 9. README proyek

Tugas ini menutup rangkaian tugas 17 sampai 19. Perbarui `README.md` di akar proyek:

- Pada baris `Status`, ganti `Tugas 01 sampai 16` menjadi `Tugas 01 sampai 19`.
- Di bagian "Fitur tahap 1", tambahkan dua butir ini di paling bawah:

  ```
  - **Harga pelanggan**: harga tetap per pelanggan per barang, diatur di halaman pelanggan atau dari form transaksi ("Jadikan harga pelanggan"). Urutan harga di nota: harga khusus nota, lalu harga pelanggan, lalu harga umum
  - **Ubah harga massal**: pilih banyak barang, naikkan atau turunkan harganya sekian rupiah, lihat pratinjau, lalu simpan. Bisa dijadwalkan dan bisa ikut mengubah harga pelanggan
  ```

- Di bagian "Rute", baris Barang, tambahkan `GET /products/bulk-price`, `POST /products/bulk-price/preview`, `POST /products/bulk-price`. Baris Pelanggan, tambahkan `POST /customers/{id}/prices`, `POST /customers/{id}/prices/{product}/stop`.

Bagian lain README tidak diubah.

## Tes yang wajib ada

Buat `tests/Feature/BulkPriceTest.php` dengan isi persis ini:

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

class BulkPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function product(string $name, int $sell, ?int $cost = null): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => $sell, 'cost_price' => $cost, 'effective_from' => now()->subDay()]);

        return $product;
    }

    private function form(array $productIds, array $extra = []): array
    {
        return array_merge([
            'product_ids' => $productIds,
            'direction' => 'up',
            'sell_amount' => '500',
            'cost_amount' => '',
            'effective_from' => now()->format('Y-m-d\TH:i'),
            'include_customer_prices' => '1',
        ], $extra);
    }

    // --- Halaman pilih barang

    public function test_create_page_lists_products_with_price(): void
    {
        $this->product('GULA', 18000);
        Product::factory()->create(['name' => 'TANPA HARGA']);
        $this->product('LAMA', 1000)->update(['status' => ProductStatus::Inactive]);

        $this->get('/products/bulk-price')
            ->assertOk()
            ->assertSee('GULA')
            ->assertSee('Rp18.000')
            ->assertSee('data-check-all', false)
            ->assertDontSee('TANPA HARGA')
            ->assertDontSee('LAMA');
    }

    public function test_create_page_search_filters_products(): void
    {
        $this->product('GULA PASIR', 18000);
        $this->product('KOPI', 2000);

        $this->get('/products/bulk-price?q=gula')->assertOk()->assertSee('GULA PASIR')->assertDontSee('KOPI');
    }

    public function test_product_list_has_bulk_price_button(): void
    {
        $this->get('/products')->assertOk()->assertSee('Ubah harga massal');
    }

    // --- Pratinjau

    public function test_preview_shows_old_and_new_prices_without_saving(): void
    {
        $gula = $this->product('GULA', 18000, 15000);
        $kopi = $this->product('KOPI', 2000);

        $this->post('/products/bulk-price/preview', $this->form([$gula->id, $kopi->id], ['sell_amount' => '1.500', 'cost_amount' => '1.000']))
            ->assertOk()
            ->assertSee('Harga jual naik Rp1.500.')
            ->assertSee('Harga modal naik Rp1.000.')
            ->assertSee('Rp19.500')
            ->assertSee('Rp16.000')
            ->assertSee('Rp3.500')
            ->assertSee('Simpan perubahan harga (2 barang)');

        $this->assertSame(2, ProductPrice::count());
    }

    public function test_preview_requires_products_and_amount(): void
    {
        $this->post('/products/bulk-price/preview', $this->form([], ['sell_amount' => '']))
            ->assertSessionHasErrors(['product_ids', 'sell_amount']);
    }

    public function test_preview_rejects_price_below_zero(): void
    {
        $gula = $this->product('GULA', 300);

        $this->post('/products/bulk-price/preview', $this->form([$gula->id], ['direction' => 'down']))
            ->assertSessionHasErrors(['product_ids' => 'Harga jual GULA akan menjadi di bawah Rp0.']);
    }

    // --- Simpan

    public function test_store_adds_new_price_rows_and_keeps_old_rows(): void
    {
        $gula = $this->product('GULA', 18000, 15000);
        $kopi = $this->product('KOPI', 2000, 1500);
        $teh = $this->product('TEH', 5000);

        $this->post('/products/bulk-price', $this->form([$gula->id, $kopi->id]))
            ->assertRedirect('/products')
            ->assertSessionHas('success', 'Harga 2 barang berhasil diubah.');

        $this->assertSame(18500, $gula->fresh()->currentPrice->sell_price);
        $this->assertSame(15000, $gula->fresh()->currentPrice->cost_price);
        $this->assertSame(2500, $kopi->fresh()->currentPrice->sell_price);
        $this->assertSame(5000, $teh->fresh()->currentPrice->sell_price);
        $this->assertSame(2, $gula->prices()->count());
        $this->assertSame('Perubahan harga massal +Rp500', $gula->fresh()->currentPrice->note);
    }

    public function test_store_decrease_with_cost_change(): void
    {
        $gula = $this->product('GULA', 18000, 15000);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['direction' => 'down', 'sell_amount' => '1.000', 'cost_amount' => '500']));

        $price = $gula->fresh()->currentPrice;
        $this->assertSame(17000, $price->sell_price);
        $this->assertSame(14500, $price->cost_price);
        $this->assertSame('Perubahan harga massal -Rp1.000', $price->note);
    }

    public function test_store_keeps_empty_cost_price_empty(): void
    {
        $gula = $this->product('GULA', 18000);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['cost_amount' => '500']));

        $this->assertNull($gula->fresh()->currentPrice->cost_price);
    }

    public function test_store_changes_customer_prices_when_checked(): void
    {
        $gula = $this->product('GULA', 18000);
        $sari = Customer::factory()->create();
        $prices = app(CustomerPriceService::class);
        $prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subDay());

        $this->post('/products/bulk-price', $this->form([$gula->id]));

        $this->assertSame(17500, $prices->priceAt($sari->id, $gula->id));
    }

    public function test_store_leaves_customer_prices_when_unchecked(): void
    {
        $gula = $this->product('GULA', 18000);
        $sari = Customer::factory()->create();
        $prices = app(CustomerPriceService::class);
        $prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subDay());

        $this->post('/products/bulk-price', $this->form([$gula->id], ['include_customer_prices' => '0']));

        $this->assertSame(17000, $prices->priceAt($sari->id, $gula->id));
        $this->assertSame(1, CustomerPrice::count());
    }

    public function test_store_does_not_revive_stopped_customer_price(): void
    {
        $gula = $this->product('GULA', 18000);
        $sari = Customer::factory()->create();
        $prices = app(CustomerPriceService::class);
        $prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subDay());
        $prices->stop($sari->id, $gula->id);

        $this->post('/products/bulk-price', $this->form([$gula->id]));

        $this->assertNull($prices->priceAt($sari->id, $gula->id));
    }

    public function test_future_change_is_scheduled(): void
    {
        $gula = $this->product('GULA', 18000);
        $date = now()->addDays(3)->setTime(8, 0);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['effective_from' => $date->format('Y-m-d\TH:i')]))
            ->assertSessionHas('success', 'Harga 1 barang akan berubah mulai '.$date->format('d/m/Y H:i').'.');

        $this->assertSame(18000, $gula->fresh()->currentPrice->sell_price);
        $this->travelTo($date->copy()->addMinute());
        $this->assertSame(18500, $gula->fresh()->currentPrice->sell_price);
    }

    public function test_past_date_is_treated_as_now(): void
    {
        $gula = $this->product('GULA', 18000);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['effective_from' => now()->subYear()->format('Y-m-d\TH:i')]));

        $latest = $gula->prices()->latest('id')->first();
        $this->assertTrue($latest->effective_from->greaterThan(now()->subMinute()));
        $this->assertSame(18500, $gula->fresh()->currentPrice->sell_price);
    }

    public function test_store_with_invalid_result_saves_nothing(): void
    {
        $gula = $this->product('GULA', 18000);
        $murah = $this->product('MURAH', 200);

        $this->post('/products/bulk-price', $this->form([$gula->id, $murah->id], ['direction' => 'down']))
            ->assertSessionHasErrors('product_ids');

        $this->assertSame(2, ProductPrice::count());
    }
}
```

Buat `tests/e2e/harga-massal.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function newProduct(page, name, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption('pcs');
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

test('harga massal: pilih barang, pratinjau, simpan', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Massal A ${suffix}`, '10000');
    await newProduct(page, `Massal B ${suffix}`, '2500');

    await page.goto('/products');
    await page.getByRole('link', { name: 'Ubah harga massal' }).click();
    await page.waitForURL(/\/products\/bulk-price$/);

    await page.getByPlaceholder('Cari nama atau kode').fill(String(suffix));
    await page.getByRole('button', { name: 'Cari' }).click();
    await page.getByLabel(/Pilih semua \(2\)/).check();
    await expect(page.locator("input[name='product_ids[]']:checked")).toHaveCount(2);

    await page.getByLabel('Perubahan harga jual').pressSequentially('1500');
    await expect(page.getByLabel('Perubahan harga jual')).toHaveValue('1.500');
    await noOverflow(page);
    await page.getByRole('button', { name: 'Lihat pratinjau' }).click();

    await expect(page.getByRole('heading', { name: 'Pratinjau perubahan harga' })).toBeVisible();
    await expect(page.getByText('Harga jual naik Rp1.500.')).toBeVisible();
    await expect(page.getByText('Rp11.500')).toBeVisible();
    await expect(page.getByText('Rp4.000')).toBeVisible();
    await noOverflow(page);

    await page.getByRole('button', { name: 'Simpan perubahan harga (2 barang)' }).click();
    await page.waitForURL(/\/products$/);
    await expect(page.getByText('Harga 2 barang berhasil diubah.')).toBeVisible();

    await page.goto(`/products?q=${suffix}`);
    await expect(page.getByText('Rp11.500')).toBeVisible();
    await expect(page.getByText('Rp4.000')).toBeVisible();
});

test('harga massal: penurunan di bawah Rp0 ditolak', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Murah ${suffix}`, '300');

    await page.goto(`/products/bulk-price?q=${suffix}`);
    await page.getByLabel(new RegExp(`Murah ${suffix}`, 'i')).check();
    await page.getByLabel('Arah perubahan').selectOption('down');
    await page.getByLabel('Perubahan harga jual').fill('500');
    await page.getByRole('button', { name: 'Lihat pratinjau' }).click();

    await expect(page.getByText(new RegExp(`Harga jual MURAH ${suffix} akan menjadi di bawah Rp0\\.`))).toBeVisible();
    await expect(page.getByLabel(new RegExp(`Murah ${suffix}`, 'i'))).toBeChecked();
});
```

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

Jalankan sesuai urutan ini. `npm run build` wajib diulang karena JavaScript berubah.

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 15.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2.
- [ ] `php artisan route:list` menampilkan tiga rute `products.bulk-price.*`, dan `/products/bulk-price` tidak menghasilkan 404.
- [ ] Di browser HP: dari daftar barang, buka "Ubah harga massal", cari, centang "Pilih semua", isi `1500` (tampil `1.500`), lihat pratinjau, simpan, lalu harga di daftar barang berubah.
- [ ] Penurunan yang membuat harga di bawah Rp0 ditolak, dan centang tetap ada.
- [ ] Perubahan dengan tanggal ke depan belum mengubah harga hari ini.
- [ ] Halaman pilih barang dan pratinjau rapi di lebar 375px.
- [ ] `README.md` proyek sudah diperbarui.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah atau menghapus baris harga lama.
- Jangan membuat perubahan dalam persen. Perubahan selalu dalam rupiah.
- Jangan memakai Livewire, Alpine, atau paket JavaScript.
- Jangan mendaftarkan rute `bulk-price` setelah `Route::resource('products', ...)`.
- Jangan menyimpan apa pun di langkah pratinjau.
- Jangan menghapus atau melemahkan tes agar lulus.

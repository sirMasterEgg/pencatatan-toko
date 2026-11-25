<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\TransactionItem;
use App\Services\ProductService;
use App\Support\Parse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

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

    public function create(): View
    {
        return view('products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'sell_price' => Parse::rupiah($request->input('sell_price')),
            'cost_price' => Parse::rupiah($request->input('cost_price')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::in(config('shop.units'))],
            'sell_price' => ['required', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'unique:products,code'],
        ]);

        if ($this->products->findByNameAndUnit($data['name'], $data['unit'])) {
            throw ValidationException::withMessages(['name' => 'Barang dengan nama dan satuan ini sudah ada.']);
        }

        $this->products->create(
            name: $data['name'],
            unit: $data['unit'],
            sellPrice: (int) $data['sell_price'],
            costPrice: isset($data['cost_price']) ? (int) $data['cost_price'] : null,
            status: ProductStatus::Active,
            category: $data['category'] ?? null,
            code: $data['code'] ?? null,
        );

        $route = $request->input('after_save') === 'add_another' ? 'products.create' : 'products.index';

        return redirect()->route($route)->with('success', 'Barang berhasil ditambahkan.');
    }

    public function show(Product $product): View
    {
        $product->load('currentPrice');

        $recentItems = TransactionItem::query()
            ->where('product_id', $product->id)
            ->whereHas('transaction', fn ($q) => $q->where('status', TransactionStatus::Confirmed))
            ->with('transaction')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('products.show', [
            'product' => $product,
            'prices' => $product->prices()->orderByDesc('effective_from')->orderByDesc('id')->get(),
            'recentItems' => $recentItems,
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.edit', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::in(config('shop.units'))],
            'category' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('products', 'code')->ignore($product->id)],
            'status' => ['nullable', Rule::in([ProductStatus::Active->value, ProductStatus::Inactive->value])],
        ]);

        $unitChanged = $data['unit'] !== $product->unit;
        $nameChanged = mb_strtolower(trim($data['name'])) !== mb_strtolower($product->name);

        if ($unitChanged && $product->items()->exists()) {
            throw ValidationException::withMessages(['unit' => 'Satuan tidak bisa diubah karena barang sudah dipakai di transaksi.']);
        }

        if ($nameChanged || $unitChanged) {
            $existing = $this->products->findByNameAndUnit($data['name'], $data['unit']);
            if ($existing && $existing->id !== $product->id) {
                throw ValidationException::withMessages(['name' => 'Barang dengan nama dan satuan ini sudah ada.']);
            }
        }

        $product->fill([
            'name' => ProductService::normalizeName($data['name']),
            'unit' => $data['unit'],
            'category' => $data['category'] ?? null,
            'code' => $data['code'] ?? null,
        ]);

        if ($product->status !== ProductStatus::NeedsReview && ! empty($data['status'])) {
            $product->status = ProductStatus::from($data['status']);
        }

        $product->save();

        return redirect()->route('products.show', $product)->with('success', 'Barang berhasil diperbarui.');
    }

    public function markReviewed(Product $product): RedirectResponse
    {
        if ($product->status === ProductStatus::NeedsReview) {
            $product->update(['status' => ProductStatus::Active]);
        }

        return back()->with('success', 'Barang ditandai sudah diperiksa.');
    }
}

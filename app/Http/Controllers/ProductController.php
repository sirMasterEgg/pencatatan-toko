<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\TransactionItem;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
        ]);

        $query = Product::query()->with('currentPrice');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            $query->where('status', '!=', ProductStatus::Merged);
        }

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term));
        }

        return view('products.index', [
            'products' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'statuses' => [ProductStatus::Active, ProductStatus::NeedsReview, ProductStatus::Inactive],
        ]);
    }

    public function create(): View
    {
        return view('products.create');
    }

    public function store(Request $request): RedirectResponse
    {
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

        $product = $this->products->create(
            name: $data['name'],
            unit: $data['unit'],
            sellPrice: (int) $data['sell_price'],
            costPrice: isset($data['cost_price']) ? (int) $data['cost_price'] : null,
            status: ProductStatus::Active,
            category: $data['category'] ?? null,
            code: $data['code'] ?? null,
        );

        return redirect()->route('products.show', $product)->with('success', 'Barang berhasil ditambahkan.');
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
            'name' => trim($data['name']),
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

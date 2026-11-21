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

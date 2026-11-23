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

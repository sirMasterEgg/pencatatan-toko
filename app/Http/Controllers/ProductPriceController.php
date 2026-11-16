<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    public function store(Request $request, Product $product, ProductService $products): RedirectResponse
    {
        $data = $request->validate([
            'sell_price' => ['required', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $products->addPrice(
            $product,
            (int) $data['sell_price'],
            isset($data['cost_price']) ? (int) $data['cost_price'] : null,
            $data['note'] ?? null,
        );

        return redirect()->route('products.show', $product)->with('success', 'Harga baru berhasil disimpan.');
    }
}

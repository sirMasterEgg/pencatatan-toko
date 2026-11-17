<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public static function normalizeName(string $name): string
    {
        return mb_strtoupper(trim($name));
    }

    public function findByNameAndUnit(string $name, string $unit): ?Product
    {
        $lowerName = mb_strtolower(trim($name));

        return Product::selectable()
            ->whereRaw('LOWER(name) = ?', [$lowerName])
            ->where('unit', $unit)
            ->first();
    }

    public function create(
        string $name,
        string $unit,
        int $sellPrice,
        ?int $costPrice = null,
        ProductStatus $status = ProductStatus::Active,
        ?int $sourceTransactionId = null,
        ?string $category = null,
        ?string $code = null,
    ): Product {
        return DB::transaction(function () use ($name, $unit, $sellPrice, $costPrice, $status, $sourceTransactionId, $category, $code) {
            $product = Product::create([
                'name' => self::normalizeName($name),
                'unit' => $unit,
                'category' => $category,
                'code' => $code,
                'status' => $status,
                'created_from_transaction_id' => $sourceTransactionId,
            ]);

            $this->addPrice($product, $sellPrice, $costPrice, null, $sourceTransactionId);

            return $product;
        });
    }

    public function addPrice(
        Product $product,
        int $sellPrice,
        ?int $costPrice = null,
        ?string $note = null,
        ?int $sourceTransactionId = null,
    ): ProductPrice {
        return $product->prices()->create([
            'sell_price' => $sellPrice,
            'cost_price' => $costPrice,
            'effective_from' => now(),
            'source_transaction_id' => $sourceTransactionId,
            'note' => $note,
        ]);
    }
}

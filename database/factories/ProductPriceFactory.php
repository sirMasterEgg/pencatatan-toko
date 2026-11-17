<?php

namespace Database\Factories;

use App\Models\ProductPrice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sell_price' => 10000,
            'cost_price' => 8000,
            'effective_from' => now()->subDay(),
        ];
    }
}

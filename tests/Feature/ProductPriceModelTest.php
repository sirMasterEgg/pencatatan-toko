<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ProductPriceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_price_ignores_future_and_picks_latest_effective(): void
    {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['sell_price' => 10000, 'effective_from' => now()->subDay()]);
        ProductPrice::factory()->for($product)->create(['sell_price' => 12000, 'effective_from' => now()->subHour()]);
        ProductPrice::factory()->for($product)->create(['sell_price' => 15000, 'effective_from' => now()->addDay()]);

        $this->assertSame(12000, $product->currentPrice->sell_price);
    }

    public function test_product_without_price_has_null_current_price(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($product->currentPrice);
    }

    public function test_price_row_cannot_be_updated(): void
    {
        $price = ProductPrice::factory()->create();

        $this->expectException(LogicException::class);
        $price->update(['sell_price' => 99999]);
    }

    public function test_price_row_cannot_be_deleted(): void
    {
        $price = ProductPrice::factory()->create();

        $this->expectException(LogicException::class);
        $price->delete();
    }

    public function test_selectable_scope_excludes_inactive_and_merged(): void
    {
        $active = Product::factory()->create(['status' => ProductStatus::Active]);
        $review = Product::factory()->create(['status' => ProductStatus::NeedsReview]);
        Product::factory()->create(['status' => ProductStatus::Inactive]);
        Product::factory()->create(['status' => ProductStatus::Merged]);

        $ids = Product::selectable()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$active->id, $review->id], $ids);
    }
}

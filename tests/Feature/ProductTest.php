<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();
        $this->get('/products')->assertRedirect('/login');
    }

    public function test_store_creates_active_product_and_first_price(): void
    {
        $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 15000, 'cost_price' => 12000])
            ->assertRedirect();

        $product = Product::firstOrFail();
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertSame(1, ProductPrice::count());
        $this->assertSame(15000, $product->prices()->first()->sell_price);
    }

    public function test_duplicate_name_and_unit_is_rejected_case_insensitive(): void
    {
        Product::factory()->create(['name' => 'gula', 'unit' => 'kg']);

        $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 1000])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Product::count());
    }

    public function test_same_name_with_different_unit_is_accepted(): void
    {
        Product::factory()->create(['name' => 'Gula', 'unit' => 'kg']);

        $this->post('/products', ['name' => 'Gula', 'unit' => 'karton', 'sell_price' => 1000])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Product::count());
    }

    public function test_unit_outside_config_is_rejected(): void
    {
        $this->post('/products', ['name' => 'Gula', 'unit' => 'ton', 'sell_price' => 1000])
            ->assertSessionHasErrors('unit');
    }

    public function test_adding_price_changes_current_price_and_keeps_old_row(): void
    {
        $product = app(ProductService::class)->create('Gula', 'kg', 10000);
        ProductPrice::query()->update(['effective_from' => now()->subDay()]);

        $this->post("/products/{$product->id}/prices", ['sell_price' => 12000])->assertRedirect();

        $this->assertSame(2, $product->prices()->count());
        $this->assertSame(12000, $product->fresh()->currentPrice->sell_price);
    }

    public function test_show_page_displays_price_history(): void
    {
        $product = app(ProductService::class)->create('Gula', 'kg', 15000);

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertSee('Riwayat harga')
            ->assertSee('Rp15.000')
            ->assertSee('Belum ada transaksi.');
    }

    public function test_mark_reviewed_changes_status_to_active(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::NeedsReview]);

        $this->post("/products/{$product->id}/mark-reviewed")->assertRedirect();

        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
    }

    public function test_search_only_shows_matching_products(): void
    {
        Product::factory()->create(['name' => 'Gula Pasir']);
        Product::factory()->create(['name' => 'Kopi Hitam']);

        $this->get('/products?q=gula')
            ->assertOk()
            ->assertSee('Gula Pasir')
            ->assertDontSee('Kopi Hitam');
    }

    public function test_delete_route_does_not_exist(): void
    {
        $product = Product::factory()->create();

        $this->delete("/products/{$product->id}")->assertStatus(405);
    }
}

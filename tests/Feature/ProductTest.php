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

    public function test_store_without_after_save_redirects_to_index(): void
    {
        $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 15000])
            ->assertRedirect('/products')
            ->assertSessionHas('success', 'Barang berhasil ditambahkan.');
    }

    public function test_store_with_add_another_redirects_to_create_form(): void
    {
        $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 15000, 'after_save' => 'add_another'])
            ->assertRedirect('/products/create')
            ->assertSessionHas('success', 'Barang berhasil ditambahkan.');

        $this->assertSame(1, Product::count());
    }

    public function test_store_with_other_after_save_value_redirects_to_index(): void
    {
        $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 15000, 'after_save' => 'lainnya'])
            ->assertRedirect('/products');
    }

    public function test_store_with_add_another_and_invalid_data_returns_errors(): void
    {
        $this->post('/products', ['name' => '', 'unit' => 'kg', 'sell_price' => 15000, 'after_save' => 'add_another'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Product::count());
    }

    public function test_add_another_button_only_on_create_form(): void
    {
        $product = Product::factory()->create();

        $this->get('/products/create')->assertOk()->assertSee('Simpan dan tambah lagi');
        $this->get("/products/{$product->id}/edit")->assertOk()->assertDontSee('Simpan dan tambah lagi');
    }

    public function test_store_accepts_thousand_separators(): void
    {
        $this->post('/products', ['name' => 'Minyak', 'unit' => 'liter', 'sell_price' => '15.000', 'cost_price' => '12.500'])
            ->assertSessionDoesntHaveErrors();

        $price = Product::firstOrFail()->prices()->first();
        $this->assertSame(15000, $price->sell_price);
        $this->assertSame(12500, $price->cost_price);
    }

    public function test_store_rejects_decimal_comma_in_price(): void
    {
        $this->post('/products', ['name' => 'Minyak', 'unit' => 'liter', 'sell_price' => '15.000,50'])
            ->assertSessionHasErrors('sell_price');

        $this->assertSame(0, Product::count());
    }

    public function test_adding_price_accepts_thousand_separators(): void
    {
        $product = app(ProductService::class)->create('Gula', 'kg', 10000);
        ProductPrice::query()->update(['effective_from' => now()->subDay()]);

        $this->post("/products/{$product->id}/prices", ['sell_price' => '17.500'])->assertSessionDoesntHaveErrors();

        $this->assertSame(17500, $product->fresh()->currentPrice->sell_price);
    }

    public function test_show_page_formats_cost_price_for_input(): void
    {
        $product = app(ProductService::class)->create('Gula', 'kg', 15000, 12000);

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertSee('value="12.000"', false)
            ->assertSee('data-rupiah', false);
    }

    public function test_store_saves_name_uppercase_and_trimmed(): void
    {
        $this->post('/products', ['name' => '  gula pasir ', 'unit' => 'kg', 'sell_price' => 15000])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('GULA PASIR', Product::firstOrFail()->name);
    }

    public function test_update_saves_name_uppercase(): void
    {
        $product = Product::factory()->create(['name' => 'TEH', 'unit' => 'pak']);

        $this->put("/products/{$product->id}", ['name' => 'Gula Merah', 'unit' => 'pak'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('GULA MERAH', $product->fresh()->name);
    }

    public function test_duplicate_is_still_detected_case_insensitively_after_uppercase(): void
    {
        $this->post('/products', ['name' => 'Gula', 'unit' => 'kg', 'sell_price' => 1000]);
        $this->post('/products', ['name' => 'gula', 'unit' => 'kg', 'sell_price' => 1000])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Product::count());
    }

    public function test_create_form_has_uppercase_class(): void
    {
        $this->get('/products/create')->assertOk()->assertSee('text-uppercase', false);
    }

    public function test_migration_uppercases_existing_names(): void
    {
        $product = Product::factory()->create(['name' => 'kopi bubuk']);

        $migration = require database_path('migrations/2026_11_18_000001_uppercase_product_names.php');
        $migration->up();

        $this->assertSame('KOPI BUBUK', $product->fresh()->name);
    }
}

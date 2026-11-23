<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\CustomerPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function product(string $name, int $sell, ?int $cost = null): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => $sell, 'cost_price' => $cost, 'effective_from' => now()->subDay()]);

        return $product;
    }

    private function form(array $productIds, array $extra = []): array
    {
        return array_merge([
            'product_ids' => $productIds,
            'direction' => 'up',
            'sell_amount' => '500',
            'cost_amount' => '',
            'effective_from' => now()->format('Y-m-d\TH:i'),
            'include_customer_prices' => '1',
        ], $extra);
    }

    // --- Halaman pilih barang

    public function test_create_page_lists_products_with_price(): void
    {
        $this->product('GULA', 18000);
        Product::factory()->create(['name' => 'TANPA HARGA']);
        $this->product('LAMA', 1000)->update(['status' => ProductStatus::Inactive]);

        $this->get('/products/bulk-price')
            ->assertOk()
            ->assertSee('GULA')
            ->assertSee('Rp18.000')
            ->assertSee('data-check-all', false)
            ->assertDontSee('TANPA HARGA')
            ->assertDontSee('LAMA');
    }

    public function test_create_page_search_filters_products(): void
    {
        $this->product('GULA PASIR', 18000);
        $this->product('KOPI', 2000);

        $this->get('/products/bulk-price?q=gula')->assertOk()->assertSee('GULA PASIR')->assertDontSee('KOPI');
    }

    public function test_product_list_has_bulk_price_button(): void
    {
        $this->get('/products')->assertOk()->assertSee('Ubah harga massal');
    }

    // --- Pratinjau

    public function test_preview_shows_old_and_new_prices_without_saving(): void
    {
        $gula = $this->product('GULA', 18000, 15000);
        $kopi = $this->product('KOPI', 2000);

        $this->post('/products/bulk-price/preview', $this->form([$gula->id, $kopi->id], ['sell_amount' => '1.500', 'cost_amount' => '1.000']))
            ->assertOk()
            ->assertSee('Harga jual naik Rp1.500.')
            ->assertSee('Harga modal naik Rp1.000.')
            ->assertSee('Rp19.500')
            ->assertSee('Rp16.000')
            ->assertSee('Rp3.500')
            ->assertSee('Simpan perubahan harga (2 barang)');

        $this->assertSame(2, ProductPrice::count());
    }

    public function test_preview_requires_products_and_amount(): void
    {
        $this->post('/products/bulk-price/preview', $this->form([], ['sell_amount' => '']))
            ->assertSessionHasErrors(['product_ids', 'sell_amount']);
    }

    public function test_preview_rejects_price_below_zero(): void
    {
        $gula = $this->product('GULA', 300);

        $this->post('/products/bulk-price/preview', $this->form([$gula->id], ['direction' => 'down']))
            ->assertSessionHasErrors(['product_ids' => 'Harga jual GULA akan menjadi di bawah Rp0.']);
    }

    // --- Simpan

    public function test_store_adds_new_price_rows_and_keeps_old_rows(): void
    {
        $gula = $this->product('GULA', 18000, 15000);
        $kopi = $this->product('KOPI', 2000, 1500);
        $teh = $this->product('TEH', 5000);

        $this->post('/products/bulk-price', $this->form([$gula->id, $kopi->id]))
            ->assertRedirect('/products')
            ->assertSessionHas('success', 'Harga 2 barang berhasil diubah.');

        $this->assertSame(18500, $gula->fresh()->currentPrice->sell_price);
        $this->assertSame(15000, $gula->fresh()->currentPrice->cost_price);
        $this->assertSame(2500, $kopi->fresh()->currentPrice->sell_price);
        $this->assertSame(5000, $teh->fresh()->currentPrice->sell_price);
        $this->assertSame(2, $gula->prices()->count());
        $this->assertSame('Perubahan harga massal +Rp500', $gula->fresh()->currentPrice->note);
    }

    public function test_store_decrease_with_cost_change(): void
    {
        $gula = $this->product('GULA', 18000, 15000);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['direction' => 'down', 'sell_amount' => '1.000', 'cost_amount' => '500']));

        $price = $gula->fresh()->currentPrice;
        $this->assertSame(17000, $price->sell_price);
        $this->assertSame(14500, $price->cost_price);
        $this->assertSame('Perubahan harga massal -Rp1.000', $price->note);
    }

    public function test_store_keeps_empty_cost_price_empty(): void
    {
        $gula = $this->product('GULA', 18000);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['cost_amount' => '500']));

        $this->assertNull($gula->fresh()->currentPrice->cost_price);
    }

    public function test_store_changes_customer_prices_when_checked(): void
    {
        $gula = $this->product('GULA', 18000);
        $sari = Customer::factory()->create();
        $prices = app(CustomerPriceService::class);
        $prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subDay());

        $this->post('/products/bulk-price', $this->form([$gula->id]));

        $this->assertSame(17500, $prices->priceAt($sari->id, $gula->id));
    }

    public function test_store_leaves_customer_prices_when_unchecked(): void
    {
        $gula = $this->product('GULA', 18000);
        $sari = Customer::factory()->create();
        $prices = app(CustomerPriceService::class);
        $prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subDay());

        $this->post('/products/bulk-price', $this->form([$gula->id], ['include_customer_prices' => '0']));

        $this->assertSame(17000, $prices->priceAt($sari->id, $gula->id));
        $this->assertSame(1, CustomerPrice::count());
    }

    public function test_store_does_not_revive_stopped_customer_price(): void
    {
        $gula = $this->product('GULA', 18000);
        $sari = Customer::factory()->create();
        $prices = app(CustomerPriceService::class);
        $prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subDay());
        $prices->stop($sari->id, $gula->id);

        $this->post('/products/bulk-price', $this->form([$gula->id]));

        $this->assertNull($prices->priceAt($sari->id, $gula->id));
    }

    public function test_future_change_is_scheduled(): void
    {
        $gula = $this->product('GULA', 18000);
        $date = now()->addDays(3)->setTime(8, 0);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['effective_from' => $date->format('Y-m-d\TH:i')]))
            ->assertSessionHas('success', 'Harga 1 barang akan berubah mulai '.$date->format('d/m/Y H:i').'.');

        $this->assertSame(18000, $gula->fresh()->currentPrice->sell_price);
        $this->travelTo($date->copy()->addMinute());
        $this->assertSame(18500, $gula->fresh()->currentPrice->sell_price);
    }

    public function test_past_date_is_treated_as_now(): void
    {
        $gula = $this->product('GULA', 18000);

        $this->post('/products/bulk-price', $this->form([$gula->id], ['effective_from' => now()->subYear()->format('Y-m-d\TH:i')]));

        $latest = $gula->prices()->latest('id')->first();
        $this->assertTrue($latest->effective_from->greaterThan(now()->subMinute()));
        $this->assertSame(18500, $gula->fresh()->currentPrice->sell_price);
    }

    public function test_store_with_invalid_result_saves_nothing(): void
    {
        $gula = $this->product('GULA', 18000);
        $murah = $this->product('MURAH', 200);

        $this->post('/products/bulk-price', $this->form([$gula->id, $murah->id], ['direction' => 'down']))
            ->assertSessionHasErrors('product_ids');

        $this->assertSame(2, ProductPrice::count());
    }
}

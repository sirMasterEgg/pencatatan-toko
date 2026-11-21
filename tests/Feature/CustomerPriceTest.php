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

class CustomerPriceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerPriceService $prices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->prices = app(CustomerPriceService::class);
    }

    private function product(string $name = 'GULA', int $price = 18000): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'effective_from' => now()->subDay()]);

        return $product;
    }

    // --- Layanan

    public function test_price_at_returns_null_without_special_price(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNull($this->prices->priceAt($customer->id, $this->product()->id));
    }

    public function test_set_then_price_at_returns_latest_price(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->set($customer->id, $gula->id, 16500);

        $this->assertSame(16500, $this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame(2, CustomerPrice::count());
    }

    public function test_future_price_is_not_active_yet(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->set($customer->id, $gula->id, 17500, effectiveFrom: now()->addDay());

        $this->assertSame(17000, $this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame(17500, $this->prices->priceAt($customer->id, $gula->id, now()->addDays(2)));
    }

    public function test_stop_ends_special_price_but_keeps_history(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->stop($customer->id, $gula->id);

        $this->assertNull($this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame(2, CustomerPrice::count());
    }

    public function test_active_for_customer_and_for_product(): void
    {
        $sari = Customer::factory()->create();
        $budi = Customer::factory()->create();
        $gula = $this->product('GULA');
        $kopi = $this->product('KOPI', 2000);

        $this->prices->set($sari->id, $gula->id, 17000, effectiveFrom: now()->subHour());
        $this->prices->set($sari->id, $kopi->id, 1800, effectiveFrom: now()->subHour());
        $this->prices->stop($sari->id, $kopi->id);
        $this->prices->set($budi->id, $gula->id, 17500, effectiveFrom: now()->subHour());

        $this->assertSame([$gula->id => 17000], $this->prices->activeForCustomer($sari->id));
        $this->assertSame([$sari->id => 17000, $budi->id => 17500], $this->prices->activeForProduct($gula->id));
    }

    public function test_history_rows_cannot_be_changed_or_deleted(): void
    {
        $customer = Customer::factory()->create();
        $row = $this->prices->set($customer->id, $this->product()->id, 17000);

        try {
            $row->update(['sell_price' => 1]);
            $this->fail('Seharusnya ditolak.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_deleting_customer_removes_its_special_prices(): void
    {
        $customer = Customer::factory()->create();
        $this->prices->set($customer->id, $this->product()->id, 17000);

        $this->delete("/customers/{$customer->id}")->assertRedirect('/customers');

        $this->assertSame(0, CustomerPrice::count());
    }

    // --- Halaman

    public function test_store_sets_special_price_with_thousand_dots(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->post("/customers/{$customer->id}/prices", ['product_id' => $gula->id, 'sell_price' => '17.000', 'note' => 'Langganan'])
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('success', 'Harga khusus berhasil disimpan.');

        $this->assertSame(17000, $this->prices->priceAt($customer->id, $gula->id));
        $this->assertSame('Langganan', CustomerPrice::firstOrFail()->note);
    }

    public function test_store_validates_input(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->post("/customers/{$customer->id}/prices", ['product_id' => $gula->id, 'sell_price' => ''])
            ->assertSessionHasErrors('sell_price');
        $this->post("/customers/{$customer->id}/prices", ['product_id' => 999, 'sell_price' => '1.000'])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(0, CustomerPrice::count());
    }

    public function test_store_rejects_inactive_product(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['status' => ProductStatus::Inactive]);

        $this->post("/customers/{$customer->id}/prices", ['product_id' => $product->id, 'sell_price' => '1.000'])
            ->assertSessionHasErrors('product_id');
    }

    public function test_stop_route_ends_special_price(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();
        $this->prices->set($customer->id, $gula->id, 17000, effectiveFrom: now()->subMinute());

        $this->post("/customers/{$customer->id}/prices/{$gula->id}/stop")
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('success', 'Harga khusus dihentikan.');

        $this->assertNull($this->prices->priceAt($customer->id, $gula->id));
    }

    public function test_stop_route_without_special_price_shows_error(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product();

        $this->from("/customers/{$customer->id}")
            ->post("/customers/{$customer->id}/prices/{$gula->id}/stop")
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('error', 'Barang ini tidak punya harga khusus untuk pelanggan ini.');

        $this->assertSame(0, CustomerPrice::count());
    }

    public function test_customer_page_lists_special_prices_and_history(): void
    {
        $customer = Customer::factory()->create();
        $gula = $this->product('GULA', 18000);
        $this->prices->set($customer->id, $gula->id, 17000, 'Langganan', effectiveFrom: now()->subMinute());

        $this->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Harga khusus')
            ->assertSee('GULA')
            ->assertSee('Harga umum: Rp18.000')
            ->assertSee('Rp17.000')
            ->assertSee('Riwayat harga khusus')
            ->assertSee('Langganan')
            ->assertSee('Simpan harga khusus');
    }

    public function test_customer_page_without_special_price(): void
    {
        $customer = Customer::factory()->create();

        $this->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Belum ada harga khusus.')
            ->assertDontSee('Riwayat harga khusus');
    }
}

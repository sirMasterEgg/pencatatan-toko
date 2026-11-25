<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function product(string $name, string $unit, ?int $price, array $extra = []): Product
    {
        $product = Product::factory()->create(array_merge(['name' => $name, 'unit' => $unit], $extra));

        if ($price !== null) {
            ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'effective_from' => now()->subDay()]);
        }

        return $product;
    }

    private function sell(Product $product, int|string $quantity, TransactionStatus $status = TransactionStatus::Confirmed): void
    {
        app(TransactionService::class)->create([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 1000, 'discount' => 0]],
        ], $status);
    }

    public function test_filter_by_unit(): void
    {
        $this->product('GULA', 'kg', 18000);
        $this->product('SABUN', 'pcs', 4000);

        $this->get('/products?unit=kg')->assertOk()->assertSee('GULA')->assertDontSee('SABUN');
    }

    public function test_unit_outside_config_is_rejected(): void
    {
        $this->get('/products?unit=ton')->assertSessionHasErrors('unit');
    }

    public function test_filter_by_category_and_category_options(): void
    {
        $this->product('GULA', 'kg', 18000, ['category' => 'Sembako']);
        $this->product('SABUN', 'pcs', 4000, ['category' => 'Kebersihan']);
        $this->product('TANPA KATEGORI', 'pcs', 1000, ['category' => null]);

        $this->get('/products')
            ->assertOk()
            ->assertSee('<option value="Kebersihan"', false)
            ->assertSee('<option value="Sembako"', false);

        $this->get('/products?category=Sembako')->assertOk()->assertSee('GULA')->assertDontSee('SABUN')->assertDontSee('TANPA KATEGORI');
    }

    public function test_filter_by_price_range_with_thousand_dots(): void
    {
        $this->product('MURAH', 'pcs', 2000);
        $this->product('SEDANG', 'pcs', 15000);
        $this->product('MAHAL', 'pcs', 90000);
        $this->product('TANPA HARGA', 'pcs', null);

        $this->get('/products?min_price=10.000&max_price=50.000')
            ->assertOk()
            ->assertSee('SEDANG')
            ->assertDontSee('MURAH')
            ->assertDontSee('MAHAL')
            ->assertDontSee('TANPA HARGA')
            ->assertSee('value="10.000"', false);
    }

    public function test_price_filter_uses_current_price_not_future_price(): void
    {
        $gula = $this->product('GULA', 'kg', 18000);
        ProductPrice::factory()->for($gula)->create(['sell_price' => 25000, 'effective_from' => now()->addDay()]);

        $this->get('/products?min_price=20000')->assertOk()->assertDontSee('GULA');
        $this->get('/products?max_price=18000')->assertOk()->assertSee('GULA');
    }

    public function test_sort_by_name(): void
    {
        $this->product('BERAS', 'kg', 12000);
        $this->product('APEL', 'kg', 30000);
        $this->product('CABAI', 'kg', 50000);

        $this->get('/products')->assertSeeInOrder(['APEL', 'BERAS', 'CABAI']);
        $this->get('/products?sort=name_desc')->assertSeeInOrder(['CABAI', 'BERAS', 'APEL']);
    }

    public function test_sort_by_price(): void
    {
        $this->product('BERAS', 'kg', 12000);
        $this->product('APEL', 'kg', 30000);
        $this->product('CABAI', 'kg', 5000);

        $this->get('/products?sort=price_asc')->assertSeeInOrder(['CABAI', 'BERAS', 'APEL']);
        $this->get('/products?sort=price_desc')->assertSeeInOrder(['APEL', 'BERAS', 'CABAI']);
    }

    public function test_sort_by_created_date(): void
    {
        $this->product('LAMA', 'kg', 1000, ['created_at' => now()->subDays(3)]);
        $this->product('BARU', 'kg', 1000, ['created_at' => now()->subDay()]);
        $this->product('TENGAH', 'kg', 1000, ['created_at' => now()->subDays(2)]);

        $this->get('/products?sort=newest')->assertSeeInOrder(['BARU', 'TENGAH', 'LAMA']);
        $this->get('/products?sort=oldest')->assertSeeInOrder(['LAMA', 'TENGAH', 'BARU']);
    }

    public function test_sort_by_best_selling_counts_only_confirmed_transactions(): void
    {
        $gula = $this->product('GULA', 'kg', 18000);
        $kopi = $this->product('KOPI', 'pcs', 2000);
        $teh = $this->product('TEH', 'pak', 8000);

        $this->sell($gula, '2,5');
        $this->sell($kopi, 3);
        $this->sell($teh, 10, TransactionStatus::Draft);

        $this->get('/products?sort=best_selling')
            ->assertOk()
            ->assertSeeInOrder(['KOPI', 'Terjual 3', 'GULA', 'Terjual 2,5', 'TEH', 'Terjual 0']);
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $this->get('/products?sort=acak')->assertSessionHasErrors('sort');
    }

    public function test_extra_filters_open_only_when_used(): void
    {
        $this->get('/products')->assertOk()->assertDontSee('collapse show', false);
        $this->get('/products?unit=kg')->assertOk()->assertSee('collapse show', false);
    }

    public function test_pagination_keeps_filters_and_sort(): void
    {
        foreach (range(1, 21) as $i) {
            $this->product(sprintf('BARANG %02d', $i), 'kg', 1000 * $i);
        }

        $this->get('/products?unit=kg&sort=price_desc')
            ->assertOk()
            ->assertSee('unit=kg', false)
            ->assertSee('sort=price_desc', false)
            ->assertSee('page=2', false);
    }
}

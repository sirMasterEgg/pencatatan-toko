<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionService::class);
    }

    private function productWithPrice(string $name, string $unit, int $price, ?int $cost = null): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => $unit]);
        ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'cost_price' => $cost]);

        return $product;
    }

    private function base(array $items, array $extra = []): array
    {
        return array_merge([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'note' => null,
            'customer' => null,
            'items' => $items,
        ], $extra);
    }

    private function existingItem(Product $product, array $extra = []): array
    {
        return array_merge(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'price_mode' => 'this_only'], $extra);
    }

    private function newItem(string $name = 'Gula', string $unit = 'kg', array $extra = []): array
    {
        return array_merge(['new_product' => ['name' => $name, 'unit' => $unit, 'cost_price' => null], 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0], $extra);
    }

    public function test_main_scenario_bu_sari(): void
    {
        $mi = $this->productWithPrice('Mi Instan', 'dus', 118000);

        $trx = $this->service->create($this->base([
            $this->existingItem($mi, ['quantity' => 2, 'unit_price' => 115000]),
            $this->newItem('Gula', 'kg'),
        ], ['customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234', 'address' => null]]));

        $this->assertSame(248000, $trx->total);
        $this->assertNotNull(Customer::where('phone', '6281200001234')->first());

        $gula = Product::where('name', 'Gula')->firstOrFail();
        $this->assertSame(ProductStatus::NeedsReview, $gula->status);
        $this->assertSame($trx->id, $gula->created_from_transaction_id);
        $this->assertSame(1, $gula->prices()->count());
        $this->assertSame(18000, $gula->prices()->first()->sell_price);
        $this->assertSame($trx->id, $gula->prices()->first()->source_transaction_id);

        $miItem = $trx->items->firstWhere('product_id', $mi->id);
        $this->assertSame(115000, $miItem->unit_price);
        $this->assertSame(118000, $miItem->list_price);
        $this->assertSame(230000, $miItem->subtotal);
        $this->assertSame('dus', $miItem->unit);

        $gulaItem = $trx->items->firstWhere('product_id', $gula->id);
        $this->assertNull($gulaItem->list_price);
        $this->assertSame(18000, $gulaItem->subtotal);

        $this->assertSame(118000, $mi->fresh()->currentPrice->sell_price);
        $this->assertSame(1, $mi->prices()->count());

        $this->assertSame(TransactionStatus::Confirmed, $trx->status);
        $this->assertSame('TRX-'.now()->subHour()->format('Ymd').'-001', $trx->number);
    }

    public function test_new_price_mode_adds_price_row_and_copies_cost(): void
    {
        $p = $this->productWithPrice('Kopi', 'pak', 10000, 7000);

        $trx = $this->service->create($this->base([$this->existingItem($p, ['unit_price' => 12000, 'price_mode' => 'new_price'])]));

        $this->assertSame(2, $p->prices()->count());
        $current = $p->fresh()->currentPrice;
        $this->assertSame(12000, $current->sell_price);
        $this->assertSame(7000, $current->cost_price);
        $this->assertSame(10000, $trx->items->first()->list_price);
    }

    public function test_new_price_mode_with_same_price_adds_nothing(): void
    {
        $p = $this->productWithPrice('Kopi', 'pak', 10000);

        $this->service->create($this->base([$this->existingItem($p, ['unit_price' => 10000, 'price_mode' => 'new_price'])]));

        $this->assertSame(1, $p->prices()->count());
    }

    public function test_new_product_matching_existing_name_does_not_duplicate(): void
    {
        $gula = $this->productWithPrice('Gula', 'kg', 17000);

        $trx = $this->service->create($this->base([$this->newItem('gula', 'kg')]));

        $this->assertSame(1, Product::count());
        $this->assertSame($gula->id, $trx->items->first()->product_id);
    }

    public function test_same_new_product_twice_creates_single_product(): void
    {
        $this->service->create($this->base([$this->newItem('Teh', 'pak'), $this->newItem('Teh', 'pak')]));

        $this->assertSame(1, Product::count());
    }

    public function test_transaction_without_customer(): void
    {
        $trx = $this->service->create($this->base([$this->newItem()]));

        $this->assertNull($trx->customer_id);
    }

    public function test_new_customer_with_existing_phone_reuses_customer(): void
    {
        $existing = Customer::factory()->create(['phone' => '6281200001234']);

        $trx = $this->service->create($this->base([$this->newItem()], ['customer' => ['name' => 'Lain', 'phone' => '0812-0000-1234']]));

        $this->assertSame($existing->id, $trx->customer_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_empty_items_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create($this->base([]));
    }

    public function test_zero_quantity_error_key(): void
    {
        try {
            $this->service->create($this->base([$this->newItem('Gula', 'kg', ['quantity' => 0])]));
            $this->fail('Seharusnya melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.quantity', $e->errors());
        }
    }

    public function test_future_date_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create($this->base([$this->newItem()], ['transacted_at' => now()->addDay()->format('Y-m-d H:i')]));
    }

    public function test_discount_larger_than_line_throws_with_key(): void
    {
        try {
            $this->service->create($this->base([$this->newItem('Gula', 'kg', ['discount' => 20000])]));
            $this->fail('Seharusnya melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.discount', $e->errors());
        }
    }

    public function test_inactive_product_throws_with_key(): void
    {
        $p = Product::factory()->create(['status' => ProductStatus::Inactive]);

        try {
            $this->service->create($this->base([$this->existingItem($p)]));
            $this->fail('Seharusnya melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.product_id', $e->errors());
        }
    }

    public function test_atomicity_nothing_saved_on_failure(): void
    {
        $counts = fn () => [Customer::count(), Product::count(), ProductPrice::count(), Transaction::count(), TransactionItem::count()];
        $before = $counts();

        try {
            $this->service->create($this->base([
                $this->newItem('Gula', 'kg'),
                $this->newItem('Teh', 'pak', ['discount' => 99999]),
            ], ['customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234']]));
            $this->fail('Seharusnya melempar ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame($before, $counts());
    }

    public function test_atomicity_rolls_back_when_failure_happens_inside_transaction(): void
    {
        $before = [Customer::count(), Product::count(), ProductPrice::count(), Transaction::count(), TransactionItem::count()];

        // Unit baru valid saat validasi awal, tapi item kedua gagal di dalam DB::transaction
        // (barang lama dinonaktifkan setelah validasi lewat event model).
        $inactive = Product::factory()->create(['status' => ProductStatus::Inactive]);
        $before[1]++;

        try {
            $this->service->create($this->base([
                $this->newItem('Gula', 'kg'),
                ['product_id' => $inactive->id, 'quantity' => 1, 'unit_price' => 1000],
            ], ['customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234']]));
            $this->fail('Seharusnya melempar ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame($before, [Customer::count(), Product::count(), ProductPrice::count(), Transaction::count(), TransactionItem::count()]);
    }

    public function test_decimal_quantity_subtotal(): void
    {
        $trx = $this->service->create($this->base([$this->newItem('Gula', 'kg', ['quantity' => 0.5])]));

        $this->assertSame(9000, $trx->items->first()->subtotal);
    }

    public function test_sequence_numbers_per_date(): void
    {
        $day = now()->subDays(2)->format('Y-m-d');
        $a = $this->service->create($this->base([$this->newItem('A', 'pcs')], ['transacted_at' => "$day 09:00"]));
        $b = $this->service->create($this->base([$this->newItem('B', 'pcs')], ['transacted_at' => "$day 10:00"]));
        $other = $this->service->create($this->base([$this->newItem('C', 'pcs')], ['transacted_at' => now()->subDays(3)->format('Y-m-d').' 10:00']));

        $prefix = 'TRX-'.now()->subDays(2)->format('Ymd').'-';
        $this->assertSame($prefix.'001', $a->number);
        $this->assertSame($prefix.'002', $b->number);
        $this->assertSame('TRX-'.now()->subDays(3)->format('Ymd').'-001', $other->number);
    }

    public function test_cancel_keeps_products_and_prices(): void
    {
        $trx = $this->service->create($this->base([$this->newItem()]));

        $cancelled = $this->service->cancel($trx, 'Salah catat');

        $this->assertSame(TransactionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, ProductPrice::count());
    }

    public function test_cancel_twice_throws(): void
    {
        $trx = $this->service->create($this->base([$this->newItem()]));
        $this->service->cancel($trx);

        $this->expectException(ValidationException::class);
        $this->service->cancel($trx->fresh());
    }

    public function test_update_payment_status_keeps_total(): void
    {
        $trx = $this->service->create($this->base([$this->newItem()], ['payment_status' => 'unpaid']));

        $updated = $this->service->updatePaymentStatus($trx, PaymentStatus::Paid);

        $this->assertSame(PaymentStatus::Paid, $updated->payment_status);
        $this->assertSame(18000, $updated->total);
    }

    public function test_unit_cannot_change_once_product_is_used(): void
    {
        $trx = $this->service->create($this->base([$this->newItem('Gula', 'kg')]));
        $product = $trx->items->first()->product;

        $this->actingAs(User::factory()->create())
            ->put("/products/{$product->id}", ['name' => 'Gula', 'unit' => 'karton'])
            ->assertSessionHasErrors('unit');
    }

    public function test_comma_quantity_for_kg_is_stored_as_decimal(): void
    {
        $p = $this->productWithPrice('Gula', 'kg', 18000);

        $trx = $this->service->create($this->base([$this->existingItem($p, ['quantity' => '0,5', 'unit_price' => 18000])]));

        $this->assertSame(9000, $trx->total);
        $this->assertSame('0.500', $trx->items->first()->quantity);
    }

    public function test_every_decimal_unit_accepts_fractions(): void
    {
        $units = config('shop.decimal_units');
        $items = [];
        foreach ($units as $unit) {
            $p = $this->productWithPrice('Barang '.$unit, $unit, 1000);
            $items[] = $this->existingItem($p, ['quantity' => '1,5', 'unit_price' => 1000]);
        }

        $trx = $this->service->create($this->base($items));

        $this->assertCount(count($units), $trx->items);
    }

    public function test_fraction_is_rejected_for_pcs_with_clear_message(): void
    {
        $product = $this->productWithPrice('Sabun', 'pcs', 3000);

        try {
            $this->service->create($this->base([$this->existingItem($product, ['quantity' => '1,5'])]));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Jumlah untuk satuan pcs harus bilangan bulat.', $e->errors()['items.0.quantity'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_fraction_is_rejected_for_new_pcs_product(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create($this->base([$this->newItem('Sabun', 'pcs', ['quantity' => '0,5'])]));
    }

    public function test_whole_number_written_with_decimals_is_accepted_for_pcs(): void
    {
        $product = $this->productWithPrice('Sabun', 'pcs', 3000);

        $trx = $this->service->create($this->base([$this->existingItem($product, ['quantity' => '2,0', 'unit_price' => 3000])]));

        $this->assertSame(6000, $trx->total);
    }

    public function test_more_than_three_decimals_is_rejected(): void
    {
        $product = $this->productWithPrice('Gula', 'kg', 18000);

        $this->expectException(ValidationException::class);
        $this->service->create($this->base([$this->existingItem($product, ['quantity' => '0,1234'])]));
    }

    public function test_malformed_quantity_is_rejected(): void
    {
        $product = $this->productWithPrice('Gula', 'kg', 18000);

        $this->expectException(ValidationException::class);
        $this->service->create($this->base([$this->existingItem($product, ['quantity' => '1,2,3'])]));
    }
}

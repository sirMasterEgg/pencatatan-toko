<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CustomerPriceService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPriceTransactionTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    private CustomerPriceService $prices;

    private Product $gula;

    private Customer $sari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->service = app(TransactionService::class);
        $this->prices = app(CustomerPriceService::class);

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);
        $this->sari = Customer::factory()->create(['name' => 'Bu Sari']);
        $this->prices->set($this->sari->id, $this->gula->id, 17000, effectiveFrom: now()->subDay());
    }

    private function data(int $unitPrice, string $priceMode = 'this_only', ?array $customer = null): array
    {
        return [
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'unpaid',
            'customer' => $customer ?? ['id' => $this->sari->id],
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => $unitPrice, 'discount' => 0, 'price_mode' => $priceMode]],
        ];
    }

    // --- Layanan

    public function test_item_stores_customer_price_snapshot(): void
    {
        $transaction = $this->service->create($this->data(17000));

        $item = $transaction->items->first();
        $this->assertSame(17000, $item->customer_price);
        $this->assertSame(18000, $item->list_price);
        $this->assertSame(17000, $item->unit_price);
    }

    public function test_item_without_customer_has_no_customer_price(): void
    {
        $data = $this->data(18000);
        $data['customer'] = null;

        $this->assertNull($this->service->create($data)->items->first()->customer_price);
    }

    public function test_customer_price_mode_adds_customer_price_row(): void
    {
        $transaction = $this->service->create($this->data(16500, 'customer_price'));

        $this->assertSame(16500, $this->prices->priceAt($this->sari->id, $this->gula->id));
        $this->assertSame($transaction->id, CustomerPrice::latest('id')->first()->source_transaction_id);
        $this->assertSame(17000, $transaction->items->first()->customer_price);
        $this->assertSame(1, $this->gula->prices()->count());
    }

    public function test_customer_price_mode_works_for_new_customer(): void
    {
        $this->service->create($this->data(16000, 'customer_price', ['name' => 'Pak Budi', 'phone' => '0813-1111-2222']));

        $budi = Customer::where('name', 'Pak Budi')->firstOrFail();
        $this->assertSame(16000, $this->prices->priceAt($budi->id, $this->gula->id));
    }

    public function test_customer_price_mode_without_customer_is_rejected(): void
    {
        $data = $this->data(16500, 'customer_price');
        $data['customer'] = null;

        try {
            $this->service->create($data);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Pilih pelanggan dulu untuk menyimpan harga pelanggan.', $e->errors()['items.0.price_mode'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_this_only_mode_changes_no_price(): void
    {
        $this->service->create($this->data(15000));

        $this->assertSame(17000, $this->prices->priceAt($this->sari->id, $this->gula->id));
        $this->assertSame(1, $this->gula->prices()->count());
    }

    public function test_new_price_mode_still_changes_general_price(): void
    {
        $this->service->create($this->data(19000, 'new_price'));

        $this->assertSame(19000, $this->gula->fresh()->currentPrice->sell_price);
        $this->assertSame(17000, $this->prices->priceAt($this->sari->id, $this->gula->id));
    }

    public function test_detail_page_shows_customer_and_general_price(): void
    {
        $transaction = $this->service->create($this->data(17000));

        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee('Harga pelanggan: Rp17.000')
            ->assertSee('Harga umum: Rp18.000');
    }

    // --- Form

    public function test_selecting_product_after_customer_uses_customer_price(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->assertSet('items.0.customer_price', 17000)
            ->assertSet('items.0.list_price', 18000)
            ->assertSet('items.0.unit_price', '17.000')
            ->assertSee('Harga pelanggan: Rp17.000');
    }

    public function test_selecting_customer_after_product_updates_untouched_price(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->assertSet('items.0.unit_price', '18.000')
            ->call('selectCustomer', $this->sari->id)
            ->assertSet('items.0.unit_price', '17.000')
            ->call('clearCustomer')
            ->assertSet('items.0.unit_price', '18.000')
            ->assertSet('items.0.customer_price', null);
    }

    public function test_selecting_customer_keeps_price_typed_for_this_note(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '15.000')
            ->call('selectCustomer', $this->sari->id)
            ->assertSet('items.0.unit_price', '15.000')
            ->assertSet('items.0.customer_price', 17000);
    }

    public function test_customer_price_option_only_shown_with_customer(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '16.000')
            ->assertSee('Jadikan harga baru')
            ->assertDontSee('Jadikan harga pelanggan')
            ->call('selectCustomer', $this->sari->id)
            ->assertSee('Jadikan harga pelanggan');
    }

    public function test_price_options_hidden_when_price_equals_customer_price(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->assertDontSee('Hanya nota ini');
    }

    public function test_clearing_customer_resets_customer_price_mode(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '16.000')
            ->set('items.0.price_mode', 'customer_price')
            ->call('clearCustomer')
            ->assertSet('items.0.price_mode', 'this_only');
    }

    public function test_form_saves_customer_price_mode(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectCustomer', $this->sari->id)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.unit_price', '16.500')
            ->set('items.0.price_mode', 'customer_price')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(16500, $this->prices->priceAt($this->sari->id, $this->gula->id));
    }

    public function test_edit_mode_loads_customer_price_for_rows(): void
    {
        $transaction = $this->service->create($this->data(17000));

        Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->assertSet('items.0.customer_price', 17000)
            ->assertSet('items.0.unit_price', '17.000');

        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
    }
}

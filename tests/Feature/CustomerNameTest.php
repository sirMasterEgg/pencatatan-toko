<?php

namespace Tests\Feature;

use App\Livewire\TransactionForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_normalize_text(): void
    {
        $this->assertSame('BU SARI', CustomerService::normalizeText('  bu sari '));
        $this->assertNull(CustomerService::normalizeText('   '));
        $this->assertNull(CustomerService::normalizeText(null));
    }

    public function test_store_saves_name_and_address_in_uppercase(): void
    {
        $this->post('/customers', ['name' => '  bu sari ', 'address' => 'jl. mawar 1'])->assertSessionDoesntHaveErrors();

        $customer = Customer::firstOrFail();
        $this->assertSame('BU SARI', $customer->name);
        $this->assertSame('JL. MAWAR 1', $customer->address);
    }

    public function test_empty_address_is_saved_as_null(): void
    {
        $this->post('/customers', ['name' => 'Pak Budi', 'address' => '  ']);

        $this->assertNull(Customer::firstOrFail()->address);
    }

    public function test_update_saves_name_and_address_in_uppercase(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => null]);

        $this->put("/customers/{$customer->id}", ['name' => 'Bu Sari Rahayu', 'address' => 'Jl. Melati 2'])->assertSessionDoesntHaveErrors();

        $this->assertSame('BU SARI RAHAYU', $customer->fresh()->name);
        $this->assertSame('JL. MELATI 2', $customer->fresh()->address);
    }

    public function test_customer_from_transaction_form_is_saved_in_uppercase(): void
    {
        app(CustomerService::class)->findOrCreate('pak budi', null, 'gang kenari');

        $customer = Customer::firstOrFail();
        $this->assertSame('PAK BUDI', $customer->name);
        $this->assertSame('GANG KENARI', $customer->address);
    }

    public function test_display_name(): void
    {
        $this->assertSame('BU SARI - JL. MAWAR 1', (new Customer(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1']))->displayName());
        $this->assertSame('PAK BUDI', (new Customer(['name' => 'PAK BUDI', 'address' => null]))->displayName());
    }

    public function test_customer_pages_show_display_name_and_search_ignores_case(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1']);

        $this->get('/customers?q=sari')->assertOk()->assertSee('BU SARI - JL. MAWAR 1');
        $this->get("/customers/{$customer->id}")->assertOk()->assertSee('<h1 class="h4 mb-0">BU SARI - JL. MAWAR 1</h1>', false);
    }

    public function test_transaction_pages_show_display_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1']);
        $product = Product::factory()->create(['unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => 1000]);

        $transaction = app(TransactionService::class)->create([
            'transacted_at' => now()->subMinute()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => ['id' => $customer->id],
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0]],
        ]);

        foreach (['/transactions', "/transactions/{$transaction->id}", '/', '/reports/daily'] as $url) {
            $this->get($url)->assertOk()->assertSee('BU SARI - JL. MAWAR 1');
        }
    }

    public function test_transaction_form_uses_display_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1', 'phone' => '6281200001234']);

        Livewire::test(TransactionForm::class)
            ->set('customerSearch', 'sari')
            ->assertSet('customerResults.0.label', 'BU SARI - JL. MAWAR 1')
            ->assertSee('BU SARI - JL. MAWAR 1')
            ->call('selectCustomer', $customer->id)
            ->assertSet('customerLabel', 'BU SARI - JL. MAWAR 1 (6281200001234)');
    }

    public function test_name_and_address_inputs_are_shown_in_uppercase(): void
    {
        $this->get('/customers/create')
            ->assertOk()
            ->assertSee('id="name" name="name" value="" class="form-control text-uppercase', false)
            ->assertSee('id="address" name="address" value="" class="form-control text-uppercase', false);
    }

    public function test_migration_uppercases_existing_names_and_addresses(): void
    {
        $customer = Customer::factory()->create(['name' => 'bu sari', 'address' => 'jl. mawar 1']);
        $noAddress = Customer::factory()->create(['name' => 'pak budi', 'address' => '']);

        $migration = require database_path('migrations/2026_11_25_000001_uppercase_customer_names.php');
        $migration->up();

        $this->assertSame('BU SARI', $customer->fresh()->name);
        $this->assertSame('JL. MAWAR 1', $customer->fresh()->address);
        $this->assertSame('PAK BUDI', $noAddress->fresh()->name);
        $this->assertNull($noAddress->fresh()->address);
    }
}

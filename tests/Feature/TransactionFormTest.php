<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Livewire\TransactionForm;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function miInstan(): Product
    {
        $p = Product::factory()->create(['name' => 'Mi Instan', 'unit' => 'dus']);
        ProductPrice::factory()->for($p)->create(['sell_price' => 118000]);

        return $p;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();
        $this->get('/transactions/create')->assertRedirect('/login');
    }

    public function test_page_contains_component(): void
    {
        $this->get('/transactions/create')->assertOk()->assertSeeLivewire(TransactionForm::class);
    }

    public function test_new_component_has_one_search_row(): void
    {
        Livewire::test(TransactionForm::class)
            ->assertCount('items', 1)
            ->assertSet('items.0.mode', 'search');
    }

    public function test_search_fills_results(): void
    {
        $this->miInstan();

        Livewire::test(TransactionForm::class)
            ->set('items.0.search', 'mi')
            ->assertCount('items.0.results', 1);
    }

    public function test_select_product_sets_existing_mode_with_list_price(): void
    {
        $p = $this->miInstan();

        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $p->id)
            ->assertSet('items.0.mode', 'existing')
            ->assertSet('items.0.unit_price', 118000);
    }

    public function test_start_new_product_uses_search_text(): void
    {
        Livewire::test(TransactionForm::class)
            ->set('items.0.search', 'Gula')
            ->call('startNewProduct', 0)
            ->assertSet('items.0.mode', 'new')
            ->assertSet('items.0.name', 'Gula');
    }

    public function test_add_and_remove_rows(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('addRow')
            ->assertCount('items', 2)
            ->call('removeRow', 1)
            ->call('removeRow', 0)
            ->assertCount('items', 1)
            ->assertSet('items.0.mode', 'search');
    }

    public function test_full_scenario_saves_and_redirects(): void
    {
        $mi = $this->miInstan();

        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $mi->id)
            ->set('items.0.quantity', '2')
            ->set('items.0.unit_price', '115000')
            ->call('addRow')
            ->set('items.1.search', 'Gula')
            ->call('startNewProduct', 1)
            ->set('items.1.unit', 'kg')
            ->set('items.1.unit_price', '18000')
            ->set('customerSearch', 'Bu Sari')
            ->call('startNewCustomer')
            ->set('customerPhone', '0812-0000-1234')
            ->call('save');

        $transaction = Transaction::firstOrFail();
        $component->assertRedirect('/transactions/'.$transaction->id);

        $this->assertSame(248000, $transaction->total);
        $this->assertSame(ProductStatus::NeedsReview, Product::where('name', 'Gula')->firstOrFail()->status);
        $this->assertSame(1, $mi->prices()->count());
    }

    public function test_new_price_mode_adds_price_row(): void
    {
        $mi = $this->miInstan();

        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $mi->id)
            ->set('items.0.unit_price', '120000')
            ->set('items.0.price_mode', 'new_price')
            ->call('save');

        $this->assertSame(2, $mi->prices()->count());
        $this->assertSame(120000, $mi->fresh()->currentPrice->sell_price);
    }

    public function test_save_with_pending_search_row_errors_and_creates_nothing(): void
    {
        Livewire::test(TransactionForm::class)
            ->set('items.0.search', 'Gula')
            ->call('save')
            ->assertHasErrors('items.0.search');

        $this->assertSame(0, Transaction::count());
    }

    public function test_save_with_no_rows_errors_on_items(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('save')
            ->assertHasErrors('items');

        $this->assertSame(0, Transaction::count());
    }

    public function test_empty_quantity_errors(): void
    {
        $mi = $this->miInstan();

        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $mi->id)
            ->set('items.0.quantity', '')
            ->call('save')
            ->assertHasErrors('items.0.quantity');

        $this->assertSame(0, Transaction::count());
    }

    public function test_total_of_two_rows(): void
    {
        $component = Livewire::test(TransactionForm::class);
        $instance = $component->instance();

        $instance->items = [
            ['quantity' => '2', 'unit_price' => 115000, 'discount' => 0],
            ['quantity' => '1', 'unit_price' => 18000, 'discount' => 0],
        ];

        $this->assertSame(248000, $instance->total());
    }

    public function test_row_subtotal_understands_comma_quantity(): void
    {
        $instance = Livewire::test(TransactionForm::class)->instance();

        $this->assertSame(9000, $instance->rowSubtotal(['quantity' => '0,5', 'unit_price' => 18000, 'discount' => 0]));
    }
}

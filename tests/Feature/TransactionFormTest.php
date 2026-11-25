<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
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
            ->assertSet('items.0.unit_price', '118.000');
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
        $this->assertSame(ProductStatus::NeedsReview, Product::where('name', 'GULA')->firstOrFail()->status);
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

    public function test_row_subtotal_understands_thousand_separators(): void
    {
        $instance = Livewire::test(TransactionForm::class)->instance();

        $this->assertSame(35000, $instance->rowSubtotal(['quantity' => '2', 'unit_price' => '18.000', 'discount' => '1.000']));
    }

    public function test_save_with_dotted_prices_stores_integers(): void
    {
        $mi = $this->miInstan();

        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $mi->id)
            ->set('items.0.quantity', '2')
            ->set('items.0.unit_price', '115.000')
            ->set('items.0.discount', '1.000')
            ->call('save')
            ->assertHasNoErrors();

        $item = Transaction::firstOrFail()->items->first();
        $this->assertSame(115000, $item->unit_price);
        $this->assertSame(1000, $item->discount);
        $this->assertSame(229000, $item->subtotal);
    }

    private function unpaidTransaction(array $extra = []): Transaction
    {
        $gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);

        return app(TransactionService::class)->create(array_merge([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'whatsapp',
            'payment_status' => 'unpaid',
            'note' => 'Antar sore',
            'customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234'],
            'items' => [['product_id' => $gula->id, 'quantity' => '1,5', 'unit_price' => 18000, 'discount' => 1000, 'price_mode' => 'this_only']],
        ], $extra));
    }

    public function test_save_draft_creates_draft_and_redirects(): void
    {
        $product = Product::factory()->create(['name' => 'MI INSTAN', 'unit' => 'dus']);
        ProductPrice::factory()->for($product)->create(['sell_price' => 118000]);

        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $product->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(TransactionStatus::Draft, $transaction->status);
        $component->assertRedirect('/transactions/'.$transaction->id);
        $this->assertSame('Draf '.$transaction->number.' berhasil disimpan.', session('success'));
    }

    public function test_edit_mode_loads_transaction_into_form(): void
    {
        $transaction = $this->unpaidTransaction();

        Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->assertSet('transactionId', $transaction->id)
            ->assertSet('originalStatus', 'confirmed')
            ->assertSet('channel', 'whatsapp')
            ->assertSet('paymentStatus', 'unpaid')
            ->assertSet('note', 'Antar sore')
            ->assertSet('customerMode', 'existing')
            ->assertSet('customerId', Customer::firstOrFail()->id)
            ->assertSet('items.0.mode', 'existing')
            ->assertSet('items.0.name', 'GULA')
            ->assertSet('items.0.quantity', '1,5')
            ->assertSet('items.0.unit_price', '18.000')
            ->assertSet('items.0.discount', '1.000')
            ->assertSet('items.0.list_price', 18000)
            ->assertSee('Simpan perubahan')
            ->assertDontSee('Simpan draf');
    }

    public function test_edit_mode_save_updates_same_transaction(): void
    {
        $transaction = $this->unpaidTransaction();

        $component = Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->set('items.0.quantity', '2')
            ->set('items.0.discount', '0')
            ->set('paymentStatus', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $component->assertRedirect('/transactions/'.$transaction->id);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(36000, $transaction->fresh()->total);
        $this->assertFalse($transaction->fresh()->isEditable());
        $this->assertSame('Transaksi '.$transaction->number.' berhasil diperbarui.', session('success'));
    }

    public function test_edit_mode_of_confirmed_transaction_cannot_save_as_draft(): void
    {
        $transaction = $this->unpaidTransaction();

        Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->call('saveDraft')
            ->assertHasErrors('status')
            ->assertSee('Transaksi yang sudah dikonfirmasi tidak bisa dijadikan draf.');

        $this->assertSame(TransactionStatus::Confirmed, $transaction->fresh()->status);
    }

    public function test_draft_edit_mode_shows_both_buttons_and_confirms(): void
    {
        $draft = $this->unpaidTransaction();
        $draft->update(['status' => TransactionStatus::Draft]);

        Livewire::test(TransactionForm::class, ['transaction' => $draft->fresh()])
            ->assertSee('Simpan draf')
            ->assertSee('Simpan transaksi')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(TransactionStatus::Confirmed, $draft->fresh()->status);
        $this->assertSame('Transaksi '.$draft->number.' berhasil disimpan.', session('success'));
    }

    public function test_transaction_paid_after_form_opened_is_rejected(): void
    {
        $transaction = $this->unpaidTransaction();

        $component = Livewire::test(TransactionForm::class, ['transaction' => $transaction]);
        $transaction->update(['payment_status' => 'paid']);

        $component->set('items.0.quantity', '5')
            ->call('save')
            ->assertHasErrors('status');

        $this->assertSame(26000, $transaction->fresh()->total);
    }

    public function test_transaction_id_cannot_be_changed_from_browser(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::test(TransactionForm::class)->set('transactionId', 1);
    }

    public function test_quantity_and_discount_inputs_are_cleared_on_first_focus(): void
    {
        $product = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => 18000]);

        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $product->id)
            ->assertSet('items.0.quantity', '1')
            ->assertSeeHtml('data-clear-default="1"')
            ->assertSeeHtml('data-clear-default="0"');
    }

    public function test_search_results_are_grouped_by_unit_then_sorted_by_name(): void
    {
        foreach ([['GULA PASIR', 'kg'], ['GULA MERAH', 'kg'], ['GULA SACHET', 'pcs'], ['GULA BATU', 'pak']] as [$name, $unit]) {
            $product = Product::factory()->create(['name' => $name, 'unit' => $unit]);
            ProductPrice::factory()->for($product)->create(['sell_price' => 1000]);
        }

        $component = Livewire::test(TransactionForm::class)->set('items.0.search', 'gula');

        $results = $component->get('items.0.results');
        $this->assertSame(['GULA SACHET', 'GULA BATU', 'GULA MERAH', 'GULA PASIR'], array_column($results, 'name'));
        $this->assertSame(['pcs', 'pak', 'kg', 'kg'], array_column($results, 'unit'));

        $component->assertSeeInOrder(['pcs', 'GULA SACHET', 'pak', 'GULA BATU', 'kg', 'GULA MERAH', 'GULA PASIR']);
    }

    public function test_search_returns_at_most_twenty_results(): void
    {
        foreach (range(1, 25) as $n) {
            Product::factory()->create(['name' => sprintf('KOPI %02d', $n), 'unit' => 'pcs']);
        }

        $results = Livewire::test(TransactionForm::class)->set('items.0.search', 'kopi')->get('items.0.results');

        $this->assertCount(20, $results);
        $this->assertSame('KOPI 01', $results[0]['name']);
        $this->assertSame('KOPI 20', $results[19]['name']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionSaveAndNewTest extends TestCase
{
    use RefreshDatabase;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);
    }

    private function draft(): Transaction
    {
        return app(TransactionService::class)->create([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0]],
        ], TransactionStatus::Draft);
    }

    public function test_save_and_new_saves_confirmed_transaction_and_opens_new_form(): void
    {
        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '2')
            ->call('saveAndNew')
            ->assertHasNoErrors()
            ->assertRedirect('/transactions/create');

        $transaction = Transaction::firstOrFail();
        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
        $this->assertSame(36000, $transaction->total);
        $this->assertSame('Transaksi '.$transaction->number.' berhasil disimpan.', session('success'));
        $this->assertSame(['id' => $transaction->id, 'number' => $transaction->number], session('last_transaction'));
    }

    public function test_save_and_new_with_errors_saves_nothing_and_stays(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '')
            ->call('saveAndNew')
            ->assertHasErrors('items.0.quantity')
            ->assertNoRedirect();

        $this->assertSame(0, Transaction::count());
    }

    public function test_save_and_new_confirms_a_draft(): void
    {
        $draft = $this->draft();

        Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->assertSee('Simpan &amp; transaksi baru', false)
            ->call('saveAndNew')
            ->assertHasNoErrors()
            ->assertRedirect('/transactions/create');

        $this->assertSame(TransactionStatus::Confirmed, $draft->fresh()->status);
        $this->assertSame(1, Transaction::count());
    }

    public function test_button_is_shown_on_new_form_but_not_when_editing_confirmed_transaction(): void
    {
        Livewire::test(TransactionForm::class)->assertSee('Simpan &amp; transaksi baru', false);

        $draft = $this->draft();
        $draft->update(['status' => TransactionStatus::Confirmed, 'payment_status' => 'unpaid']);

        Livewire::test(TransactionForm::class, ['transaction' => $draft->fresh()])
            ->assertDontSee('Simpan &amp; transaksi baru', false);
    }

    public function test_save_and_new_on_confirmed_edit_only_updates_same_transaction(): void
    {
        $draft = $this->draft();
        $draft->update(['status' => TransactionStatus::Confirmed, 'payment_status' => 'unpaid']);

        Livewire::test(TransactionForm::class, ['transaction' => $draft->fresh()])
            ->set('items.0.quantity', '3')
            ->call('saveAndNew')
            ->assertRedirect('/transactions/create');

        // Tombolnya tidak tampil di sini. Jika aksinya tetap dipanggil, hanya transaksi yang sama yang diperbarui.
        $this->assertSame(1, Transaction::count());
        $this->assertSame(54000, $draft->fresh()->total);
    }

    public function test_create_page_shows_link_to_last_saved_transaction(): void
    {
        $transaction = $this->draft();

        $this->withSession(['last_transaction' => ['id' => $transaction->id, 'number' => $transaction->number]])
            ->get('/transactions/create')
            ->assertOk()
            ->assertSee('Lihat '.$transaction->number)
            ->assertSee(route('transactions.show', $transaction), false);
    }

    public function test_create_page_without_last_transaction_has_no_link(): void
    {
        $this->get('/transactions/create')->assertOk()->assertDontSee('Lihat TRX-');
    }
}

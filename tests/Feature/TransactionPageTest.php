<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function make(array $extra = [], array $item = []): Transaction
    {
        return app(TransactionService::class)->create(array_merge([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [array_merge(['new_product' => ['name' => 'Gula '.uniqid(), 'unit' => 'kg'], 'quantity' => 1, 'unit_price' => 18000], $item)],
        ], $extra));
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();
        $this->get('/transactions')->assertRedirect('/login');
    }

    public function test_index_lists_transaction_numbers(): void
    {
        $t = $this->make();

        $this->get('/transactions')->assertOk()->assertSee($t->number);
    }

    public function test_date_filter(): void
    {
        $old = $this->make(['transacted_at' => now()->subDays(10)->format('Y-m-d H:i')]);
        $recent = $this->make();

        $from = now()->subDays(2)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $this->get("/transactions?from=$from&to=$to")
            ->assertSee($recent->number)
            ->assertDontSee($old->number);
    }

    public function test_payment_status_filter(): void
    {
        $paid = $this->make(['payment_status' => 'paid']);
        $unpaid = $this->make(['payment_status' => 'unpaid']);

        $this->get('/transactions?payment_status=unpaid')
            ->assertSee($unpaid->number)
            ->assertDontSee($paid->number);
    }

    public function test_search_by_customer_name(): void
    {
        $a = $this->make(['customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234']]);
        $b = $this->make();

        $this->get('/transactions?q=Sari')->assertSee($a->number)->assertDontSee($b->number);
    }

    public function test_show_displays_product_total_and_customer(): void
    {
        $t = $this->make(['customer' => ['name' => 'Bu Sari']], ['new_product' => ['name' => 'Gula Pasir', 'unit' => 'kg']]);

        $this->get("/transactions/{$t->id}")
            ->assertOk()
            ->assertSee('Gula Pasir')
            ->assertSee('Rp18.000')
            ->assertSee('Bu Sari');
    }

    public function test_show_displays_list_price_when_different(): void
    {
        $p = Product::factory()->create(['name' => 'Mi Instan', 'unit' => 'dus']);
        ProductPrice::factory()->for($p)->create(['sell_price' => 118000]);

        $t = $this->make([], ['new_product' => null, 'product_id' => $p->id, 'unit_price' => 115000, 'price_mode' => 'this_only']);

        $this->get("/transactions/{$t->id}")->assertSee('Harga daftar');
    }

    public function test_cancel_sets_status_and_reason(): void
    {
        $t = $this->make();

        $this->post("/transactions/{$t->id}/cancel", ['reason' => 'Salah catat'])->assertRedirect("/transactions/{$t->id}");

        $t->refresh();
        $this->assertSame(TransactionStatus::Cancelled, $t->status);
        $this->assertSame('Salah catat', $t->cancel_reason);
    }

    public function test_cancel_already_cancelled_returns_error(): void
    {
        $t = $this->make();
        app(TransactionService::class)->cancel($t, 'Pertama');

        $this->post("/transactions/{$t->id}/cancel", ['reason' => 'Kedua'])->assertSessionHasErrors('status');

        $this->assertSame('Pertama', $t->fresh()->cancel_reason);
    }

    public function test_update_payment_status(): void
    {
        $t = $this->make(['payment_status' => 'unpaid']);

        $this->post("/transactions/{$t->id}/payment-status", ['payment_status' => 'paid'])->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $t->fresh()->payment_status);
    }

    public function test_invalid_payment_status_is_rejected(): void
    {
        $t = $this->make(['payment_status' => 'unpaid']);

        $this->post("/transactions/{$t->id}/payment-status", ['payment_status' => 'bebas'])->assertSessionHasErrors('payment_status');

        $this->assertSame(PaymentStatus::Unpaid, $t->fresh()->payment_status);
    }

    public function test_cancelled_transaction_has_no_cancel_button(): void
    {
        $t = $this->make();
        app(TransactionService::class)->cancel($t);

        $this->get("/transactions/{$t->id}")->assertOk()->assertDontSee('Batalkan transaksi');
    }

    public function test_create_route_is_not_treated_as_id(): void
    {
        $this->get('/transactions/create')->assertOk()->assertSeeLivewire(\App\Livewire\TransactionForm::class);
    }
}

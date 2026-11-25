<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->service = app(TransactionService::class);

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 20000, 'effective_from' => now()->subDay()]);
    }

    private function data(string $payment, mixed $paidAmount = null, int $quantity = 5): array
    {
        return [
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => $payment,
            'paid_amount' => $paidAmount,
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => $quantity, 'unit_price' => 20000, 'discount' => 0]],
        ];
    }

    // --- Layanan

    public function test_paid_amount_follows_payment_status(): void
    {
        $this->assertSame(100000, $this->service->create($this->data('paid'))->paid_amount);
        $this->assertSame(0, $this->service->create($this->data('unpaid'))->paid_amount);
        $this->assertSame(40000, $this->service->create($this->data('partial', 40000))->paid_amount);
    }

    public function test_partial_accepts_amount_with_thousand_dots(): void
    {
        $transaction = $this->service->create($this->data('partial', '40.000'));

        $this->assertSame(40000, $transaction->paid_amount);
        $this->assertSame(60000, $transaction->remainingAmount());
    }

    public function test_partial_without_amount_is_rejected(): void
    {
        try {
            $this->service->create($this->data('partial'));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Isi nominal yang sudah dibayar.', $e->errors()['paid_amount'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_partial_amount_must_be_less_than_total(): void
    {
        try {
            $this->service->create($this->data('partial', 100000));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Nominal dibayar harus kurang dari total. Pilih "Lunas" jika sudah dibayar penuh.', $e->errors()['paid_amount'][0]);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_paid_amount_is_ignored_for_paid_and_unpaid(): void
    {
        $this->assertSame(100000, $this->service->create($this->data('paid', 1))->paid_amount);
        $this->assertSame(0, $this->service->create($this->data('unpaid', 50000))->paid_amount);
    }

    public function test_update_recalculates_paid_amount(): void
    {
        $draft = $this->service->create($this->data('partial', 40000), TransactionStatus::Draft);

        $updated = $this->service->update($draft, $this->data('partial', 10000, 2), TransactionStatus::Draft);
        $this->assertSame(10000, $updated->paid_amount);
        $this->assertSame(30000, $updated->remainingAmount());

        $this->expectException(ValidationException::class);
        $this->service->update($updated, $this->data('partial', 50000, 2), TransactionStatus::Draft);
    }

    public function test_update_payment_status_with_amount(): void
    {
        $transaction = $this->service->create($this->data('unpaid'));

        $this->service->updatePaymentStatus($transaction, PaymentStatus::Partial, 25000);
        $this->assertSame(25000, $transaction->fresh()->paid_amount);

        $this->service->updatePaymentStatus($transaction->fresh(), PaymentStatus::Paid);
        $this->assertSame(100000, $transaction->fresh()->paid_amount);

        $this->service->updatePaymentStatus($transaction->fresh(), PaymentStatus::Unpaid);
        $this->assertSame(0, $transaction->fresh()->paid_amount);
    }

    public function test_report_counts_only_remaining_amount_as_receivable(): void
    {
        $this->service->create($this->data('paid'));
        $this->service->create($this->data('unpaid'));
        $this->service->create($this->data('partial', 40000));

        $report = app(DailyReportService::class)->forDate(now());

        $this->assertSame(160000, $report['unpaid_total']);
        $this->assertSame(2, $report['unpaid_count']);
    }

    public function test_migration_sets_paid_amount_for_old_transactions(): void
    {
        $paid = $this->service->create($this->data('paid'));
        $unpaid = $this->service->create($this->data('unpaid'));
        Transaction::query()->update(['paid_amount' => 0]);

        $migration = require database_path('migrations/2026_11_25_000002_add_paid_amount_to_transactions_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(100000, $paid->fresh()->paid_amount);
        $this->assertSame(0, $unpaid->fresh()->paid_amount);
    }

    // --- Halaman

    public function test_payment_status_route_saves_partial_amount(): void
    {
        $transaction = $this->service->create($this->data('unpaid'));

        $this->post("/transactions/{$transaction->id}/payment-status", ['payment_status' => 'partial', 'paid_amount' => '30.000'])
            ->assertRedirect("/transactions/{$transaction->id}")
            ->assertSessionHas('success', 'Status bayar diperbarui.');

        $this->assertSame(30000, $transaction->fresh()->paid_amount);
        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee('Dibayar')
            ->assertSee('Rp30.000')
            ->assertSee('Kurang')
            ->assertSee('Rp70.000')
            ->assertSee('value="30.000"', false);
    }

    public function test_payment_status_route_rejects_partial_without_amount(): void
    {
        $transaction = $this->service->create($this->data('unpaid'));

        $this->from("/transactions/{$transaction->id}")
            ->post("/transactions/{$transaction->id}/payment-status", ['payment_status' => 'partial', 'paid_amount' => ''])
            ->assertSessionHasErrors('paid_amount');

        $this->assertSame(PaymentStatus::Unpaid, $transaction->fresh()->payment_status);
    }

    public function test_transaction_list_shows_remaining_for_partial(): void
    {
        $this->service->create($this->data('partial', 40000));

        $this->get('/transactions')->assertOk()->assertSee('Kurang Rp60.000');
    }

    // --- Form

    public function test_form_shows_amount_field_only_for_partial_and_computes_remaining(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '5')
            ->assertDontSee('Nominal dibayar')
            ->set('paymentStatus', 'partial')
            ->assertSee('Nominal dibayar')
            ->set('paidAmount', '40.000')
            ->assertSee('Kurang: <strong>Rp60.000</strong>', false);
    }

    public function test_form_saves_partial_amount(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('items.0.quantity', '5')
            ->set('paymentStatus', 'partial')
            ->set('paidAmount', '40.000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(40000, Transaction::firstOrFail()->paid_amount);
    }

    public function test_form_shows_error_when_partial_amount_missing(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('paymentStatus', 'partial')
            ->call('save')
            ->assertHasErrors('paid_amount');

        $this->assertSame(0, Transaction::count());
    }

    public function test_edit_mode_loads_partial_amount_of_draft(): void
    {
        $draft = $this->service->create($this->data('partial', 40000), TransactionStatus::Draft);

        Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->assertSet('paymentStatus', 'partial')
            ->assertSet('paidAmount', '40.000');
    }
}

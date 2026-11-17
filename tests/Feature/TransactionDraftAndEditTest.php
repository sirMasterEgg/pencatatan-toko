<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionDraftAndEditTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->service = app(TransactionService::class);
    }

    private function productWithPrice(string $name, string $unit, int $price): Product
    {
        $product = Product::factory()->create(['name' => $name, 'unit' => $unit]);
        ProductPrice::factory()->for($product)->create(['sell_price' => $price, 'effective_from' => now()->subDay()]);

        return $product;
    }

    private function data(array $items, array $extra = []): array
    {
        return array_merge([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'unpaid',
            'note' => null,
            'customer' => null,
            'items' => $items,
        ], $extra);
    }

    private function item(Product $product, int|string $quantity, int $unitPrice): array
    {
        return ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount' => 0, 'price_mode' => 'this_only'];
    }

    // --- Model

    public function test_is_editable_follows_status_and_payment_status(): void
    {
        $cases = [
            [TransactionStatus::Draft, PaymentStatus::Paid, true],
            [TransactionStatus::Draft, PaymentStatus::Unpaid, true],
            [TransactionStatus::Confirmed, PaymentStatus::Unpaid, true],
            [TransactionStatus::Confirmed, PaymentStatus::Partial, false],
            [TransactionStatus::Confirmed, PaymentStatus::Paid, false],
            [TransactionStatus::Cancelled, PaymentStatus::Unpaid, false],
        ];

        foreach ($cases as [$status, $payment, $expected]) {
            $transaction = new Transaction(['status' => $status, 'payment_status' => $payment]);
            $this->assertSame($expected, $transaction->isEditable(), "{$status->value} + {$payment->value}");
        }
    }

    // --- Draf

    public function test_create_as_draft_is_not_counted_in_report(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        $draft = $this->service->create($this->data([$this->item($gula, 2, 18000)]), TransactionStatus::Draft);

        $this->assertSame(TransactionStatus::Draft, $draft->status);
        $this->assertSame(36000, $draft->total);
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-\d{3}$/', $draft->number);
        $this->assertSame(0, app(DailyReportService::class)->forDate(now())['revenue']);
    }

    public function test_create_without_status_is_still_confirmed(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
    }

    public function test_cannot_save_as_cancelled(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        $this->expectException(ValidationException::class);

        $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Cancelled);
    }

    public function test_delete_draft_removes_transaction_and_items_but_keeps_new_product(): void
    {
        $draft = $this->service->create($this->data([
            ['new_product' => ['name' => 'Teh', 'unit' => 'pak'], 'quantity' => 1, 'unit_price' => 8000, 'discount' => 0],
        ]), TransactionStatus::Draft);

        $this->service->deleteDraft($draft);

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, TransactionItem::count());
        $this->assertSame(ProductStatus::NeedsReview, Product::firstOrFail()->status);
    }

    public function test_confirmed_transaction_cannot_be_deleted(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->expectException(ValidationException::class);

        $this->service->deleteDraft($transaction);
    }

    // --- Ubah

    public function test_update_replaces_items_and_total_but_keeps_number(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $kopi = $this->productWithPrice('KOPI', 'pcs', 2000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));
        $number = $transaction->number;

        $updated = $this->service->update($transaction, $this->data([
            $this->item($gula, '0,5', 18000),
            $this->item($kopi, 3, 2000),
        ], ['note' => 'Dikoreksi']), TransactionStatus::Confirmed);

        $this->assertSame($number, $updated->number);
        $this->assertSame(15000, $updated->total);
        $this->assertSame('Dikoreksi', $updated->note);
        $this->assertSame(2, TransactionItem::count());
        $this->assertSame(1, Transaction::count());
    }

    public function test_draft_can_be_confirmed_by_update(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $confirmed = $this->service->update($draft, $this->data([$this->item($gula, 2, 18000)], ['payment_status' => 'paid']), TransactionStatus::Confirmed);

        $this->assertSame(TransactionStatus::Confirmed, $confirmed->status);
        $this->assertSame(36000, app(DailyReportService::class)->forDate(now())['revenue']);
    }

    public function test_draft_with_paid_status_can_still_be_edited(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']), TransactionStatus::Draft);

        $updated = $this->service->update($draft, $this->data([$this->item($gula, 3, 18000)], ['payment_status' => 'paid']), TransactionStatus::Draft);

        $this->assertSame(54000, $updated->total);
    }

    public function test_confirmed_transaction_cannot_go_back_to_draft(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->expectException(ValidationException::class);

        $this->service->update($transaction, $this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);
    }

    public function test_paid_partial_and_cancelled_transactions_cannot_be_updated(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);

        foreach (['paid', 'partial'] as $payment) {
            $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => $payment]));
            $this->assertUpdateRejected($transaction, $gula);
        }

        $cancelled = $this->service->create($this->data([$this->item($gula, 1, 18000)]));
        $this->service->cancel($cancelled);
        $this->assertUpdateRejected($cancelled->fresh(), $gula);
    }

    private function assertUpdateRejected(Transaction $transaction, Product $product): void
    {
        $before = $transaction->total;

        try {
            $this->service->update($transaction, $this->data([$this->item($product, 5, 18000)]), TransactionStatus::Confirmed);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame($before, $transaction->fresh()->total);
    }

    public function test_paid_transaction_is_editable_again_after_payment_set_back_to_unpaid(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']));

        $this->service->updatePaymentStatus($transaction, PaymentStatus::Unpaid);
        $updated = $this->service->update($transaction->fresh(), $this->data([$this->item($gula, 2, 18000)]), TransactionStatus::Confirmed);

        $this->assertSame(36000, $updated->total);
    }

    public function test_invalid_update_changes_nothing(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        try {
            $this->service->update($transaction, $this->data([$this->item($gula, '', 18000)]), TransactionStatus::Confirmed);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException) {
        }

        $this->assertSame(18000, $transaction->fresh()->total);
        $this->assertSame(1, $transaction->items()->count());
    }

    public function test_update_keeps_new_product_marker(): void
    {
        $transaction = $this->service->create($this->data([
            ['new_product' => ['name' => 'Teh', 'unit' => 'pak'], 'quantity' => 1, 'unit_price' => 8000, 'discount' => 0],
        ]));
        $teh = Product::firstOrFail();

        $updated = $this->service->update($transaction, $this->data([$this->item($teh, 2, 8000)]), TransactionStatus::Confirmed);

        $this->assertNull($updated->items->first()->list_price);
        $this->assertSame(1, $teh->prices()->count());
    }

    public function test_update_with_new_price_mode_adds_price_history(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->service->update($transaction, $this->data([
            array_merge($this->item($gula, 1, 19000), ['price_mode' => 'new_price']),
        ]), TransactionStatus::Confirmed);

        $this->assertSame(2, $gula->prices()->count());
        $this->assertSame(19000, $gula->fresh()->currentPrice->sell_price);
    }

    // --- Halaman

    public function test_show_page_has_edit_button_only_when_editable(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $unpaid = $this->service->create($this->data([$this->item($gula, 1, 18000)]));
        $paid = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']));

        $this->get("/transactions/{$unpaid->id}")->assertOk()->assertSee('Ubah transaksi');
        $this->get("/transactions/{$paid->id}")->assertOk()->assertDontSee('Ubah transaksi');
    }

    public function test_draft_show_page_has_delete_button_and_no_cancel_or_payment_form(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $this->get("/transactions/{$draft->id}")
            ->assertOk()
            ->assertSee('Ini draf.')
            ->assertSee('Hapus draf')
            ->assertSee('Ubah transaksi')
            ->assertDontSee('Batalkan transaksi')
            ->assertDontSee('Simpan status bayar');
    }

    public function test_edit_page_shows_form_for_editable_transaction(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->get("/transactions/{$transaction->id}/edit")
            ->assertOk()
            ->assertSee('Ubah transaksi '.$transaction->number)
            ->assertSeeLivewire('transaction-form');
    }

    public function test_edit_page_redirects_when_paid(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)], ['payment_status' => 'paid']));

        $this->get("/transactions/{$transaction->id}/edit")
            ->assertRedirect("/transactions/{$transaction->id}")
            ->assertSessionHas('error', 'Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.');
    }

    public function test_delete_route_removes_draft(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $draft = $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $this->delete("/transactions/{$draft->id}")
            ->assertRedirect('/transactions')
            ->assertSessionHas('success', 'Draf '.$draft->number.' berhasil dihapus.');

        $this->assertSame(0, Transaction::count());
    }

    public function test_delete_route_rejects_confirmed_transaction(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $transaction = $this->service->create($this->data([$this->item($gula, 1, 18000)]));

        $this->from("/transactions/{$transaction->id}")
            ->delete("/transactions/{$transaction->id}")
            ->assertSessionHasErrors('status');

        $this->assertSame(1, Transaction::count());
    }

    public function test_index_shows_draft_badge(): void
    {
        $gula = $this->productWithPrice('GULA', 'kg', 18000);
        $this->service->create($this->data([$this->item($gula, 1, 18000)]), TransactionStatus::Draft);

        $this->get('/transactions?status=draft')->assertOk()->assertSee('badge text-bg-secondary">Draf', false);
    }
}

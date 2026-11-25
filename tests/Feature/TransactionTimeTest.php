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

class TransactionTimeTest extends TestCase
{
    use RefreshDatabase;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setTime(10, 0));

        $this->gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($this->gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);
    }

    public function test_new_form_uses_automatic_time(): void
    {
        Livewire::test(TransactionForm::class)
            ->assertSet('autoTime', true)
            ->assertSet('transactedAt', now()->format('Y-m-d\TH:i'))
            ->assertSeeHtml('data-live-clock')
            ->assertSee('Mengikuti waktu sekarang.')
            ->assertDontSee('Pakai waktu sekarang');
    }

    public function test_automatic_time_is_refreshed_on_every_request(): void
    {
        $component = Livewire::test(TransactionForm::class);

        $this->travel(7)->minutes();
        $component->call('addRow')->assertSet('transactedAt', now()->format('Y-m-d\TH:i'));
    }

    public function test_save_with_automatic_time_uses_time_of_saving(): void
    {
        $component = Livewire::test(TransactionForm::class)->call('selectProduct', 0, $this->gula->id);

        $this->travel(25)->minutes();
        $component->call('save')->assertHasNoErrors();

        $this->assertSame('10:25', Transaction::firstOrFail()->transacted_at->format('H:i'));
    }

    public function test_manual_time_stops_automatic_time_and_is_saved(): void
    {
        $manual = now()->subDay()->setTime(8, 15)->format('Y-m-d\TH:i');

        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('transactedAt', $manual)
            ->assertSet('autoTime', false)
            ->assertDontSeeHtml('data-live-clock')
            ->assertSee('Pakai waktu sekarang');

        $this->travel(30)->minutes();
        $component->call('addRow')->assertSet('transactedAt', $manual);
        $component->call('save')->assertHasNoErrors();

        $this->assertSame(str_replace('T', ' ', $manual), Transaction::firstOrFail()->transacted_at->format('Y-m-d H:i'));
    }

    public function test_use_current_time_returns_to_automatic_time(): void
    {
        Livewire::test(TransactionForm::class)
            ->set('transactedAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('useCurrentTime')
            ->assertSet('autoTime', true)
            ->assertSet('transactedAt', now()->format('Y-m-d\TH:i'))
            ->assertSeeHtml('data-live-clock');
    }

    public function test_future_manual_time_is_still_rejected(): void
    {
        Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $this->gula->id)
            ->set('transactedAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('transacted_at');

        $this->assertSame(0, Transaction::count());
    }

    public function test_edit_mode_keeps_original_time(): void
    {
        $draft = app(TransactionService::class)->create([
            'transacted_at' => now()->subDays(2)->setTime(9, 30)->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0]],
        ], TransactionStatus::Draft);

        $component = Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->assertSet('autoTime', false)
            ->assertSee('Pakai waktu sekarang')
            ->assertDontSeeHtml('data-live-clock');

        $this->travel(1)->hours();
        $component->call('save')->assertHasNoErrors();

        $this->assertSame('09:30', $draft->fresh()->transacted_at->format('H:i'));
    }

    public function test_edit_mode_can_switch_to_current_time(): void
    {
        $draft = app(TransactionService::class)->create([
            'transacted_at' => now()->subDays(2)->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => null,
            'items' => [['product_id' => $this->gula->id, 'quantity' => 1, 'unit_price' => 18000, 'discount' => 0]],
        ], TransactionStatus::Draft);

        Livewire::test(TransactionForm::class, ['transaction' => $draft])
            ->call('useCurrentTime')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($draft->fresh()->transacted_at->isToday());
    }
}

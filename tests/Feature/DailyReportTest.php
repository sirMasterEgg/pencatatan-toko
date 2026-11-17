<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $yesterday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->yesterday = now()->subDay();
    }

    private function seedData(): void
    {
        $service = app(TransactionService::class);
        $mi = Product::factory()->create(['name' => 'Mi Instan', 'unit' => 'dus']);
        ProductPrice::factory()->for($mi)->create(['sell_price' => 118000]);

        $at = fn (Carbon $d, string $time) => $d->format('Y-m-d').' '.$time;
        $mk = fn (array $o) => $service->create(array_merge(['customer' => null], $o));

        $mk(['transacted_at' => $at($this->yesterday, '10:00'), 'channel' => 'store', 'payment_status' => 'paid',
            'items' => [['product_id' => $mi->id, 'quantity' => 2, 'unit_price' => 115000]]]);

        $mk(['transacted_at' => $at($this->yesterday, '11:00'), 'channel' => 'whatsapp', 'payment_status' => 'unpaid',
            'items' => [
                ['product_id' => $mi->id, 'quantity' => 1, 'unit_price' => 118000],
                ['new_product' => ['name' => 'Gula', 'unit' => 'kg'], 'quantity' => 1, 'unit_price' => 18000],
            ]]);

        $c = $mk(['transacted_at' => $at($this->yesterday, '12:00'), 'channel' => 'store', 'payment_status' => 'paid',
            'items' => [['new_product' => ['name' => 'Kopi', 'unit' => 'pak'], 'quantity' => 1, 'unit_price' => 50000]]]);
        $service->cancel($c, 'Batal');

        $mk(['transacted_at' => $at(now()->subDays(2), '09:00'), 'channel' => 'store', 'payment_status' => 'paid',
            'items' => [['new_product' => ['name' => 'Teh', 'unit' => 'pak'], 'quantity' => 1, 'unit_price' => 99000]]]);
    }

    public function test_report_numbers_for_date(): void
    {
        $this->seedData();

        $r = app(DailyReportService::class)->forDate($this->yesterday);

        $this->assertSame(366000, $r['revenue']);
        $this->assertSame(2, $r['transaction_count']);
        $this->assertSame(183000, $r['average']);
        $this->assertSame(136000, $r['unpaid_total']);
        $this->assertSame(1, $r['unpaid_count']);
        $this->assertSame(1, $r['cancelled_count']);

        $channels = collect($r['by_channel'])->keyBy(fn ($row) => $row['channel']->value);
        $this->assertSame([1, 230000], [$channels['store']['count'], $channels['store']['revenue']]);
        $this->assertSame([1, 136000], [$channels['whatsapp']['count'], $channels['whatsapp']['revenue']]);
        $this->assertSame([0, 0], [$channels['phone']['count'], $channels['phone']['revenue']]);

        $this->assertSame('Mi Instan', $r['top_products'][0]['name']);
        $this->assertSame(3.0, $r['top_products'][0]['quantity']);
        $this->assertSame(348000, $r['top_products'][0]['revenue']);
        $this->assertSame('GULA', $r['top_products'][1]['name']);
        $this->assertSame(18000, $r['top_products'][1]['revenue']);

        $this->assertCount(2, $r['transactions']);
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();
        $this->get('/reports/daily')->assertRedirect('/login');
    }

    public function test_page_shows_revenue_for_date(): void
    {
        $this->seedData();

        $this->get('/reports/daily?date='.$this->yesterday->format('Y-m-d'))->assertOk()->assertSee('Rp366.000');
    }

    public function test_page_defaults_to_today(): void
    {
        $this->get('/reports/daily')->assertOk();
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->get('/reports/daily?date=salah')->assertSessionHasErrors('date');
    }

    public function test_dashboard_is_ok_and_has_new_transaction_button(): void
    {
        $this->get('/')->assertOk()->assertSee('Transaksi baru');
    }

    public function test_empty_date_shows_zero_without_error(): void
    {
        $this->get('/reports/daily?date=2020-01-01')->assertOk()->assertSee('Rp0');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Livewire\TransactionForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FullFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_from_product_to_cancelled_report(): void
    {
        $this->actingAs(User::factory()->create());

        // 2. Barang lewat halaman barang
        $this->post('/products', ['name' => 'Mi Instan', 'unit' => 'dus', 'sell_price' => 118000, 'cost_price' => 105000])
            ->assertRedirect();
        $mi = Product::where('name', 'MI INSTAN')->firstOrFail();

        // 3. Form transaksi
        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $mi->id)
            ->set('items.0.quantity', '2')
            ->set('items.0.unit_price', '115000')
            ->set('items.0.price_mode', 'this_only')
            ->call('addRow')
            ->set('items.1.search', 'Gula')
            ->call('startNewProduct', 1)
            ->set('items.1.unit', 'kg')
            ->set('items.1.quantity', '1')
            ->set('items.1.unit_price', '18000')
            ->set('customerSearch', 'Bu Sari')
            ->call('startNewCustomer')
            ->set('customerPhone', '0812-0000-1234')
            ->call('save');

        // 4. Database
        $transaction = Transaction::firstOrFail();
        $component->assertRedirect(route('transactions.show', $transaction));
        $this->assertSame(248000, $transaction->total);
        $this->assertSame(TransactionStatus::Confirmed, $transaction->status);
        $this->assertNotNull(Customer::where('phone', '6281200001234')->first());
        $gula = Product::where('name', 'GULA')->firstOrFail();
        $this->assertSame(ProductStatus::NeedsReview, $gula->status);
        $this->assertSame(118000, $mi->fresh()->currentPrice->sell_price);

        // 5. Detail transaksi
        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee('Rp248.000')
            ->assertSee('BU SARI')
            ->assertSee('Harga daftar');

        // 6. Barang perlu dirapikan
        $this->get('/products?status=needs_review')->assertOk()->assertSee('GULA');

        // 7. Tandai sudah diperiksa
        $this->post("/products/{$gula->id}/mark-reviewed")->assertRedirect();
        $this->assertSame(ProductStatus::Active, $gula->fresh()->status);

        // 8. Laporan harian
        $this->get('/reports/daily')->assertOk()->assertSee('Rp248.000');

        // 9. Batalkan
        $this->post("/transactions/{$transaction->id}/cancel", ['reason' => 'Salah input'])->assertRedirect();
        $this->assertSame(TransactionStatus::Cancelled, $transaction->fresh()->status);

        // 10. Omzet kembali Rp0, barang tetap ada
        $this->get('/reports/daily')->assertOk()->assertSee('Rp0');
        $this->assertNotNull(Product::where('name', 'GULA')->first());
    }
}

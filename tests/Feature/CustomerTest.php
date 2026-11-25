<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_phone_is_normalized_on_store(): void
    {
        $this->post('/customers', ['name' => 'Bu Sari', 'phone' => '0812-0000-1234'])->assertRedirect();

        $this->assertSame('6281200001234', Customer::firstOrFail()->phone);
    }

    public function test_duplicate_phone_after_normalization_is_rejected(): void
    {
        Customer::factory()->create(['phone' => '6281200001234']);

        $this->post('/customers', ['name' => 'Lain', 'phone' => '+62 812 0000 1234'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, Customer::count());
    }

    public function test_two_customers_without_phone_are_allowed(): void
    {
        $this->post('/customers', ['name' => 'A'])->assertSessionDoesntHaveErrors();
        $this->post('/customers', ['name' => 'B'])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Customer::count());
    }

    public function test_update_without_changing_own_phone_has_no_unique_error(): void
    {
        $customer = Customer::factory()->create(['phone' => '6281200001234']);

        $this->put("/customers/{$customer->id}", ['name' => 'Nama Baru', 'phone' => '0812-0000-1234'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('NAMA BARU', $customer->fresh()->name);
    }

    public function test_search_by_phone_prefix_finds_customer(): void
    {
        Customer::factory()->create(['name' => 'Bu Sari', 'phone' => '6281200001234']);
        Customer::factory()->create(['name' => 'Pak Budi', 'phone' => '6285500009999']);

        $this->get('/customers?q=0812')
            ->assertOk()
            ->assertSee('Bu Sari')
            ->assertDontSee('Pak Budi');
    }

    public function test_customer_without_transactions_can_be_deleted(): void
    {
        $customer = Customer::factory()->create();

        $this->delete("/customers/{$customer->id}")->assertRedirect('/customers');

        $this->assertSame(0, Customer::count());
    }

    public function test_find_or_create_is_idempotent_by_phone(): void
    {
        $service = app(CustomerService::class);

        $first = $service->findOrCreate('Bu Sari', '0812-0000-1234');
        $second = $service->findOrCreate('Bu Sari', '0812-0000-1234');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Customer::count());
    }

    public function test_find_or_create_keeps_existing_name(): void
    {
        $service = app(CustomerService::class);
        $first = $service->findOrCreate('Bu Sari', '0812-0000-1234');

        $found = $service->findOrCreate('Nama Lain', '081200001234');

        $this->assertSame($first->id, $found->id);
        $this->assertSame('BU SARI', $found->fresh()->name);
    }

    public function test_find_or_create_without_phone_creates_each_time(): void
    {
        $service = app(CustomerService::class);

        $service->findOrCreate('Tanpa Nomor');
        $service->findOrCreate('Tanpa Nomor');

        $this->assertSame(2, Customer::count());
    }

    public function test_store_without_after_save_redirects_to_index(): void
    {
        $this->post('/customers', ['name' => 'Bu Sari'])
            ->assertRedirect('/customers')
            ->assertSessionHas('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function test_store_with_add_another_redirects_to_create_form(): void
    {
        $this->post('/customers', ['name' => 'Bu Sari', 'after_save' => 'add_another'])
            ->assertRedirect('/customers/create')
            ->assertSessionHas('success', 'Pelanggan berhasil ditambahkan.');

        $this->assertSame(1, Customer::count());
    }

    public function test_store_with_other_after_save_value_redirects_to_index(): void
    {
        $this->post('/customers', ['name' => 'Bu Sari', 'after_save' => 'lainnya'])
            ->assertRedirect('/customers');
    }

    public function test_store_with_add_another_and_invalid_data_returns_errors(): void
    {
        $this->post('/customers', ['name' => '', 'after_save' => 'add_another'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Customer::count());
    }

    public function test_add_another_button_only_on_create_form(): void
    {
        $customer = Customer::factory()->create();

        $this->get('/customers/create')->assertOk()->assertSee('Simpan dan tambah lagi');
        $this->get("/customers/{$customer->id}/edit")->assertOk()->assertDontSee('Simpan dan tambah lagi');
    }
}

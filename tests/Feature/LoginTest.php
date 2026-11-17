<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OwnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_visible_to_guest(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-123'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-123']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
    }

    public function test_logout_makes_user_guest(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_owner_seeder_creates_user_when_password_set(): void
    {
        config(['shop.owner.password' => 'rahasia-123']);

        $this->seed(OwnerSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(config('shop.owner.email'), User::first()->email);
    }

    public function test_owner_seeder_throws_when_password_empty(): void
    {
        config(['shop.owner.password' => null]);

        $this->expectException(RuntimeException::class);
        $this->seed(OwnerSeeder::class);
    }
}

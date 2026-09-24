<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_a_protected_route(): void
    {
        $response = $this->get('/home');

        $response->assertRedirect('/login');
    }

    public function test_user_can_log_in_with_correct_credentials_and_reach_a_protected_route(): void
    {
        $user = User::factory()->create([
            'password' => 'senha-correta',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-correta',
        ]);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);

        $this->get('/home')->assertOk();
    }

    public function test_user_cannot_log_in_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'password' => 'senha-correta',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'senha-correta']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'senha-errada']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'senha-correta']);

        $response->assertStatus(429);
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}

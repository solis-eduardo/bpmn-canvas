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
        $response = $this->get('/diagrams');

        $response->assertRedirect('/login');
    }

    public function test_user_can_log_in_with_correct_credentials_and_reach_the_diagram_list(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('diagrams.index'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('diagrams.index'))->assertOk();
    }

    public function test_user_cannot_log_in_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertStatus(429);
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['private-session-data' => 'must be discarded'])
            ->post('/logout')
            ->assertRedirect('/login')
            ->assertSessionMissing('private-session-data');

        $this->assertGuest();
    }

    public function test_diagram_list_exposes_a_way_to_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('diagrams.index'))
            ->assertSee(route('logout'), false);
    }

    public function test_login_screen_shows_keycloak_option_when_configured(): void
    {
        config([
            'services.keycloak.client_id' => 'client-id',
            'services.keycloak.client_secret' => 'client-secret',
            'services.keycloak.base_url' => 'https://keycloak.example.test',
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee(route('login.keycloak'), false);
    }

    public function test_login_screen_hides_keycloak_option_when_not_configured(): void
    {
        config([
            'services.keycloak.client_id' => null,
            'services.keycloak.client_secret' => null,
            'services.keycloak.base_url' => null,
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee(route('login.keycloak'), false);
    }

    public function test_login_screen_hides_keycloak_option_when_only_partially_configured(): void
    {
        config([
            'services.keycloak.client_id' => 'client-id',
            'services.keycloak.client_secret' => null,
            'services.keycloak.base_url' => 'https://keycloak.example.test',
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee(route('login.keycloak'), false);
    }
}

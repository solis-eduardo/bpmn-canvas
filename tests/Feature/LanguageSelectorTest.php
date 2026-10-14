<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_lists_every_language_with_flag_and_name(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('🇺🇸', false)->assertSee('English')
            ->assertSee('🇧🇷', false)->assertSee('Português')
            ->assertSee('🇪🇸', false)->assertSee('Español');
    }

    public function test_current_language_is_marked_and_others_are_not(): void
    {
        $html = $this->withSession(['locale' => 'pt_BR'])->get('/login')->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*<span[^>]*>🇧🇷/u', $html);
    }

    public function test_selector_has_an_accessible_label_translated_to_the_locale(): void
    {
        $this->withSession(['locale' => 'pt_BR'])->get('/login')
            ->assertSee('aria-label="Idioma: Português"', false);
        $this->withSession(['locale' => 'en'])->get('/login')
            ->assertSee('aria-label="Language: English"', false);
    }

    public function test_flags_are_decorative_and_hidden_from_assistive_tech(): void
    {
        $this->get('/login')->assertSee('aria-hidden="true">🇺🇸', false);
    }

    public function test_choosing_a_language_still_switches_locale(): void
    {
        $this->post('/locale/es')->assertRedirect();
        $this->assertSame('es', session('locale'));
    }

    public function test_switching_language_returns_to_the_page_not_to_a_json_poll(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/diagrams')->assertOk();
        $this->actingAs($user)->getJson('/notifications/unread-count')->assertOk();

        $this->actingAs($user)->post('/locale/pt_BR')
            ->assertRedirect(url('/diagrams'));
    }
}

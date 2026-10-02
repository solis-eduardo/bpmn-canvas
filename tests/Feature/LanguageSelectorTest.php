<?php

namespace Tests\Feature;

use Tests\TestCase;

class LanguageSelectorTest extends TestCase
{
    public function test_login_page_lists_every_language_with_flag_and_name(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('🇺🇸', false)->assertSee('English')
            ->assertSee('🇧🇷', false)->assertSee('Português (Brasil)')
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
            ->assertSee('aria-label="Idioma"', false);
        $this->withSession(['locale' => 'en'])->get('/login')
            ->assertSee('aria-label="Language"', false);
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
}

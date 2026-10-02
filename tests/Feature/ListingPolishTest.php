<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_search_field_has_a_correctly_sized_magnifier_icon(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/home');

        $this->assertMatchesRegularExpression('/data-icon="search"[^>]*class="[^"]*h-4 w-4[^"]*absolute/s', $response->getContent());
    }

    public function test_diagrams_page_uses_the_wide_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/diagrams')->assertSee('max-w-[1600px]', false);
        $this->actingAs($user)->get('/workspaces')->assertDontSee('max-w-[1600px]', false);
    }

    public function test_row_actions_never_wrap(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create();

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('flex flex-nowrap items-center gap-3', false)
            ->assertDontSee('flex-wrap items-center gap-3 text-sm', false);
        $this->actingAs($admin)->get('/workspaces')->assertDontSee('flex flex-col gap-2', false);
    }

    public function test_state_changing_user_action_has_a_fixed_minimum_width(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create();

        $this->actingAs($admin)->get('/admin/users')->assertSee('min-w-44', false);
    }

    public function test_administrator_filter_option_is_not_a_duplicate_of_its_placeholder(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('<option value="1"', false)
            ->assertSee(__('messages.filter_administrators_only'));
    }

    public function test_filter_labels_use_sentence_case_in_every_locale(): void
    {
        foreach (['en', 'pt_BR', 'es'] as $locale) {
            app()->setLocale($locale);

            foreach (['all_workspaces', 'no_workspace', 'filter_by_tag_placeholder', 'filter_by_diagram_owner', 'filter_by_author', 'filter_owner', 'filter_collaborator', 'create_workspace', 'workspace_name'] as $key) {
                $words = explode(' ', preg_replace('/[…]/u', '', __('messages.'.$key)));
                array_shift($words);
                foreach ($words as $word) {
                    $this->assertDoesNotMatchRegularExpression('/^\p{Lu}\p{Ll}+$/u', $word, "$locale.$key has a capitalized word: $word");
                }
            }
        }
    }

    public function test_tag_filter_select_is_styled_like_the_other_filters(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.ts-wrapper .ts-control', $css);
        $this->assertStringContainsString('--color-gray-300', $css);
    }
}

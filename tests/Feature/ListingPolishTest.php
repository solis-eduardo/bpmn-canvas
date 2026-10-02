<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_search_field_has_a_correctly_sized_magnifier_icon(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/home');

        $this->assertMatchesRegularExpression('/data-icon="search"[^>]*class="[^"]*absolute[^"]*h-4 w-4/s', $response->getContent());
    }

    public function test_every_listing_keeps_the_same_page_width(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['/diagrams', '/workspaces', '/admin/users'] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertSee('max-w-6xl', false)
                ->assertDontSee('max-w-[1600px]', false);
        }
    }

    public function test_listing_tables_scroll_horizontally_instead_of_shifting_the_header(): void
    {
        $this->actingAs(User::factory()->create())->get('/diagrams')
            ->assertSee('overflow-x-auto', false)
            ->assertSee('min-w-[1100px]', false);
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

    public function test_grant_and_revoke_buttons_reserve_the_width_of_both_labels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]);
        User::factory()->create(['is_admin' => true]);

        foreach (['en', 'pt_BR', 'es'] as $locale) {
            $html = $this->actingAs($admin)->withSession(['locale' => $locale])->get('/admin/users')->getContent();

            preg_match_all('/<span class="inline-grid" data-admin-toggle-label>(.*?)<\/span>\s*<\/span>/s', $html, $cells);
            $this->assertNotEmpty($cells[1], $locale);

            foreach ($cells[1] as $cell) {
                $this->assertStringContainsString(__('messages.grant_administrator', [], $locale), $cell);
                $this->assertStringContainsString(__('messages.revoke_administrator', [], $locale), $cell);
                $this->assertStringContainsString('invisible', $cell);
            }
        }
    }

    public function test_administrator_action_labels_use_sentence_case_in_every_locale(): void
    {
        foreach (['en', 'pt_BR', 'es'] as $locale) {
            foreach (['grant_administrator', 'revoke_administrator'] as $key) {
                $words = explode(' ', __('messages.'.$key, [], $locale));
                array_shift($words);
                $this->assertSame([strtolower($words[0])], $words, "$locale.$key");
            }
        }
    }

    public function test_create_user_button_has_a_correctly_sized_icon(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertMatchesRegularExpression('/data-icon="plus"[^>]*class="[^"]*h-4 w-4/s', $this->actingAs($admin)->get('/admin/users')->getContent());
    }

    public function test_pill_columns_are_centered_and_actions_columns_shrink_to_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create();

        $users = $this->actingAs($admin)->get('/admin/users')->getContent();
        $this->assertSame(2, preg_match_all('/<th scope="col" class="px-4 py-3 text-center[^"]*">/', $users));
        $this->assertStringContainsString('<td class="whitespace-nowrap px-4 py-3 text-center">', $users);

        foreach (['/admin/users', '/workspaces', '/diagrams'] as $url) {
            $this->assertMatchesRegularExpression('/<th scope="col" class="w-px whitespace-nowrap[^"]*">[^<]*(Actions|Acciones|Ações)/', $this->actingAs($admin)->get($url)->getContent(), $url);
        }

        $this->actingAs($admin)->get('/diagrams')->assertSee('<th scope="col" class="px-4 py-3 text-center">'.__('messages.diagram_column_tags').'</th>', false);
    }

    public function test_last_modified_is_the_penultimate_column_of_every_table(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Workspace::factory()->create(['owner_id' => $admin->id]);
        Diagram::factory()->for($admin, 'author')->create();

        foreach (['/diagrams' => 'diagram_column_actions', '/workspaces' => 'workspace_column_actions', '/admin/users' => 'user_actions'] as $url => $actions) {
            $html = $this->actingAs($admin)->get($url)->getContent();
            $head = substr($html, strpos($html, '<thead'), strpos($html, '</thead>') - strpos($html, '<thead'));
            preg_match_all('/<th\b.*?<\/th>/s', $head, $headers);
            $last = array_slice($headers[0], -2);

            $this->assertStringContainsString(__('messages.diagram_column_modified'), $last[0], $url);
            $this->assertStringContainsString(__('messages.'.$actions), $last[1], $url);
            $this->assertStringContainsString('data-sort-column="updated_at"', $last[0], $url);
            $this->assertStringContainsString('<time datetime=', $html, $url);
        }
    }

    public function test_removed_filters_are_gone_from_the_diagram_filter_bar(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/diagrams');

        $response->assertDontSee('name="locked"', false)->assertDontSee('name="recent"', false);
        $this->assertArrayNotHasKey('filter_locked', trans('messages'));
        $this->assertArrayNotHasKey('filter_recent', trans('messages'));
    }

    public function test_users_cannot_be_sorted_by_email_or_login_method(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $html = $this->actingAs($admin)->get('/admin/users')->getContent();

        $this->assertStringNotContainsString('data-sort-column="email"', $html);
        $this->assertStringNotContainsString('data-sort-column="login_method"', $html);
        $this->assertSame(['name', 'updated_at'], array_keys(UserRepository::SORT_COLUMNS));
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

            foreach (['all_workspaces', 'no_workspace', 'filter_tag', 'filter_diagram_owner', 'filter_author', 'filter_owner', 'filter_collaborator', 'create_workspace', 'workspace_name'] as $key) {
                $words = explode(' ', preg_replace('/[…]/u', '', __('messages.'.$key)));
                array_shift($words);
                foreach ($words as $word) {
                    $this->assertDoesNotMatchRegularExpression('/^\p{Lu}\p{Ll}+$/u', $word, "$locale.$key has a capitalized word: $word");
                }
            }
        }
    }

    public function test_diagram_filter_placeholders_are_short_and_without_ellipsis(): void
    {
        $expected = ['en' => ['Owner', 'Author', 'Workspace'], 'pt_BR' => ['Dono', 'Autor', 'Ambiente'], 'es' => ['Propietario', 'Autor', 'Espacio de trabajo']];

        foreach ($expected as $locale => [$owner, $author, $workspace]) {
            $this->assertSame($owner, __('messages.filter_diagram_owner', [], $locale));
            $this->assertSame($author, __('messages.filter_author', [], $locale));
            $this->assertSame($workspace, __('messages.all_workspaces', [], $locale));
            $this->assertStringNotContainsString('…', __('messages.filter_tag', [], $locale));
        }
    }
}

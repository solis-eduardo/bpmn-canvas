<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_navigation_items_display_an_icon_alongside_their_label(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/home');

        $response->assertOk();
        foreach (['home', 'diagrams', 'workspaces', 'bell', 'users', 'user-circle', 'logout'] as $icon) {
            $response->assertSee('data-icon="'.$icon.'"', false);
        }
    }

    public function test_primary_buttons_display_an_icon(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('diagrams.index'))
            ->assertSee('data-icon="plus"', false)
            ->assertSee('data-icon="funnel"', false)
            ->assertSee('data-icon="search"', false);

        $this->actingAs($user)->get(route('workspaces.index'))
            ->assertSee('data-icon="plus"', false);
    }

    public function test_icons_are_decorative_and_the_icon_only_bell_keeps_its_accessible_name(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/home');

        $this->assertMatchesRegularExpression('/data-icon="home"\s+aria-hidden="true"/', $response->getContent());
        $response->assertSee('aria-label="'.__('messages.notifications').'"', false);
    }

    public function test_editor_icon_only_controls_have_localized_accessible_names(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get(route('diagrams.show', $diagram->slug));

        $response->assertOk();
        foreach (['close', 'zoom_in', 'zoom_out', 'fit_to_screen'] as $key) {
            $response->assertSee('aria-label="'.__('messages.'.$key).'"', false);
        }
        foreach (['save', 'trash', 'x-mark', 'fit'] as $icon) {
            $response->assertSee('data-icon="'.$icon.'"', false);
        }
    }

    public function test_secondary_buttons_display_an_icon(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->get(route('workspaces.index'))
            ->assertSee('data-icon="pencil"', false);

        $workspace = Workspace::first();
        $this->actingAs($user)->get(route('workspaces.edit', $workspace))
            ->assertSee('data-icon="user-plus"', false)
            ->assertSee('data-icon="transfer"', false);

        $this->actingAs($user)->get(route('account.edit'))
            ->assertSee('data-icon="save"', false);

        $this->actingAs($user)->get('/home')
            ->assertSee('data-icon="search"', false);
    }

    public function test_home_search_shortcut_shows_a_single_search_icon(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();

        preg_match('#<a href="'.preg_quote(route('diagrams.index'), '#').'".*?</a>#s', $html, $shortcut);

        $this->assertNotEmpty($shortcut);
        $this->assertSame(1, substr_count($shortcut[0], 'data-icon="search"'));
    }

    public function test_diagram_search_icon_is_sized_like_other_inline_icons(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('diagrams.index'))
            ->assertOk()
            ->assertSeeInOrder(['data-icon="search"', 'h-4 w-4 pointer-events-none'], false);
    }

    public function test_account_change_password_title_has_no_icon(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('data-icon="lock-closed"', false);
    }

    public function test_login_button_displays_an_icon_inside_the_form_only(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-icon="login"'));
        $this->assertGreaterThan(strpos($html, '<body'), strpos($html, 'data-icon="login"'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
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
}

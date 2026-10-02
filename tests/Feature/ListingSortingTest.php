<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingSortingTest extends TestCase
{
    use RefreshDatabase;

    private function assertOrder(string $content, string $first, string $second): void
    {
        $this->assertLessThan(strpos($content, $second), strpos($content, $first));
    }

    public function test_diagram_headers_link_to_the_opposite_direction_and_mark_the_sorted_column(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/diagrams?sort=title&direction=asc&q=foo');

        $response->assertOk()
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('data-sort-column="title"', false)
            ->assertSee('data-sort-column="updated_at"', false);
        $content = $response->getContent();
        $this->assertStringContainsString('sort=title&amp;direction=desc&amp;q=foo', $content);
        $this->assertStringContainsString('sort=updated_at&amp;direction=desc', $content);
    }

    public function test_diagram_headers_toggle_descending_to_ascending(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/diagrams?sort=title&direction=desc');

        $response->assertSee('aria-sort="descending"', false)
            ->assertSee('sort=title&amp;direction=asc', false);
    }

    public function test_diagram_header_links_restart_pagination(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->count(25)->create();

        $response = $this->actingAs($user)->get('/diagrams?page=2');

        $this->assertSame(1, preg_match('/<a href="([^"]+)" data-sort-column="title"/', $response->getContent(), $matches));
        $this->assertStringNotContainsString('page=', $matches[1]);
    }

    public function test_diagram_pagination_links_keep_the_sort(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->count(25)->create();

        $this->actingAs($user)->get('/diagrams?sort=updated_at&direction=asc')
            ->assertSee('sort=updated_at&amp;direction=asc&amp;page=2', false);
    }

    public function test_filter_bars_have_no_sort_control(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['/diagrams', '/workspaces', '/admin/users', '/notifications/history'] as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertDontSee('name="sort"', false)
                ->assertDontSee('name="direction"', false);
        }
    }

    public function test_unknown_sort_columns_are_ignored(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['/diagrams', '/workspaces', '/admin/users'] as $url) {
            $this->actingAs($admin)->get($url.'?sort=password&direction=sideways')->assertOk();
        }
    }

    public function test_workspaces_sort_by_name_and_members_from_the_headers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Workspace::factory()->create(['owner_id' => $admin->id, 'name' => 'Alpha team']);
        Workspace::factory()->create(['owner_id' => $admin->id, 'name' => 'Zulu team']);

        $asc = $this->actingAs($admin)->get('/workspaces?sort=name&direction=asc');
        $asc->assertSee('aria-sort="ascending"', false);
        $this->assertOrder($asc->getContent(), 'Alpha team', 'Zulu team');

        $desc = $this->actingAs($admin)->get('/workspaces?sort=name&direction=desc');
        $this->assertOrder($desc->getContent(), 'Zulu team', 'Alpha team');

        $this->actingAs($admin)->get('/workspaces?sort=members_count')->assertOk()
            ->assertSee('data-sort-column="members_count"', false);
    }

    public function test_users_sort_from_the_headers(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Middle Admin']);
        User::factory()->create(['name' => 'Aaron First']);
        User::factory()->create(['name' => 'Zed Last']);

        $desc = $this->actingAs($admin)->get('/admin/users?sort=name&direction=desc');
        $desc->assertSee('aria-sort="descending"', false);
        $this->assertOrder($desc->getContent(), 'Zed Last', 'Aaron First');

        $this->actingAs($admin)->get('/admin/users?sort=name&direction=desc&admin=0')
            ->assertSee('sort=name&amp;direction=asc&amp;admin=0', false);
    }

    public function test_user_pagination_keeps_the_sort(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(25)->create();

        $this->actingAs($admin)->get('/admin/users?sort=email&direction=desc')
            ->assertSee('sort=email&amp;direction=desc&amp;page=2', false);
    }
}

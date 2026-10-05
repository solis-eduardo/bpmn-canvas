<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAdded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_without_a_term_shows_no_results_section(): void
    {
        $this->actingAs(User::factory()->create())->get('/home')
            ->assertOk()
            ->assertViewHas('results', null)
            ->assertDontSee('data-search-group', false);
    }

    public function test_results_show_a_clear_link_back_to_the_initial_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home?q=zebra')
            ->assertOk()
            ->assertSee('data-search-clear', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('Clear');

        $this->actingAs($user)->get('/home')
            ->assertOk()
            ->assertDontSee('data-search-clear', false);
    }

    public function test_results_are_grouped_by_type_and_link_to_their_items(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Zebra process']);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Zebra team']);
        $this->addMember($workspace, $user);
        $user->notify(new WorkspaceMemberAdded($workspace));

        $response = $this->actingAs($user)->get('/home?q=zebra');

        $response->assertOk()
            ->assertSee('data-search-group="diagrams"', false)
            ->assertSee('data-search-group="workspaces"', false)
            ->assertSee('data-search-group="notifications"', false)
            ->assertSee(route('diagrams.show', $diagram->slug), false)
            ->assertSee('Zebra team')
            ->assertSee('You were added to Zebra team.');
        $this->assertSame(1, $response->viewData('results')['notifications']['total']);
    }

    public function test_hidden_workspace_diagrams_are_absent_and_discoverable_ones_match_by_title_only(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        Diagram::factory()->for($owner, 'author')->create(['title' => 'Quasar hidden', 'workspace_id' => $workspace->id]);
        $discoverable = Diagram::factory()->for($owner, 'author')->create(['title' => 'Quasar public']);

        $response = $this->actingAs($user)->get('/home?q=quasar');

        $response->assertOk()->assertDontSee('Quasar hidden')->assertSee('Quasar public');
        $response->assertDontSee(route('diagrams.show', $discoverable->slug), false);
    }

    public function test_workspaces_are_limited_to_memberships_except_for_administrators(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Nova mine']);
        $this->addMember($mine, $user);
        Workspace::factory()->create(['owner_id' => $other->id, 'name' => 'Nova theirs']);

        $this->actingAs($user)->get('/home?q=nova')
            ->assertSee('Nova mine')->assertDontSee('Nova theirs');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/home?q=nova')
            ->assertSee('Nova mine')->assertSee('Nova theirs');
    }

    public function test_notifications_are_only_the_users_own(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $other->id, 'name' => 'Secret lair']);
        $other->notify(new WorkspaceMemberAdded($workspace));

        $this->actingAs($user)->get('/home?q=lair')
            ->assertOk()->assertDontSee('Secret lair');
    }

    public function test_each_type_is_limited_with_a_see_all_link_to_the_filtered_listing(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 7; $i++) {
            Diagram::factory()->for($user, 'author')->create(['title' => "Bulk diagram {$i}"]);
            $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'name' => "Bulk workspace {$i}"]);
            $this->addMember($workspace, $user);
            $user->notify(new WorkspaceMemberAdded($workspace));
        }

        $response = $this->actingAs($user)->get('/home?q=bulk');

        $results = $response->viewData('results');
        foreach (['diagrams', 'workspaces', 'notifications'] as $type) {
            $this->assertCount(5, $results[$type]['items']);
            $this->assertSame(7, $results[$type]['total']);
        }
        $response->assertSee(route('diagrams.index', ['q' => 'bulk']), false)
            ->assertSee(route('workspaces.index', ['name' => 'bulk']), false)
            ->assertSee(route('notifications.history', ['q' => 'bulk']), false);
    }

    public function test_notification_history_filters_by_message_text(): void
    {
        $user = User::factory()->create();
        foreach (['Alpha', 'Beta'] as $name) {
            $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'name' => $name]);
            $user->notify(new WorkspaceMemberAdded($workspace));
        }

        $this->actingAs($user)->get('/notifications/history?q=alpha')
            ->assertOk()->assertSee('added to Alpha')->assertDontSee('added to Beta');
    }
}

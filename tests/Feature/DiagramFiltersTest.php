<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_filter_shows_only_diagrams_owned_by_the_current_user(): void
    {
        $user = User::factory()->create();
        $owned = Diagram::factory()->for($user, 'author')->create(['title' => 'Owned by me']);
        $other = Diagram::factory()->create(['title' => 'Owned by someone else']);

        $response = $this->actingAs($user)->get('/diagrams?owner=1');

        $response->assertOk();
        $response->assertSee($owned->title);
        $this->assertFalse($response->viewData('diagrams')->getCollection()->contains('id', $other->id));
    }

    public function test_collaborator_filter_shows_only_diagrams_the_user_is_an_approved_collaborator_on(): void
    {
        $user = User::factory()->create();

        $approved = Diagram::factory()->create(['title' => 'Approved collaboration']);
        DiagramCollaborator::create([
            'diagram_id' => $approved->id,
            'user_id' => $user->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $requested = Diagram::factory()->create(['title' => 'Pending collaboration']);
        DiagramCollaborator::create([
            'diagram_id' => $requested->id,
            'user_id' => $user->id,
            'status' => DiagramCollaborator::STATUS_REQUESTED,
        ]);

        $unrelated = Diagram::factory()->create(['title' => 'Unrelated diagram']);

        $response = $this->actingAs($user)->get('/diagrams?collaborator=1');

        $response->assertOk();
        $response->assertSee($approved->title);
        $listedIds = $response->viewData('diagrams')->getCollection()->pluck('id');
        $this->assertFalse($listedIds->contains($requested->id));
        $this->assertFalse($listedIds->contains($unrelated->id));
    }

    public function test_locked_filter_includes_locks_regardless_of_inactivity(): void
    {
        $user = User::factory()->create();
        $locker = User::factory()->create();

        $locked = Diagram::factory()->for($user, 'author')->create(['title' => 'Actively locked']);
        $locked->forceFill([
            'locked_by' => $locker->id,
            'locked_at' => now(),
            'last_heartbeat_at' => now(),
        ])->save();

        $expiredLock = Diagram::factory()->for($user, 'author')->create(['title' => 'Stale lock']);
        $expiredLock->forceFill([
            'locked_by' => $locker->id,
            'locked_at' => now()->subMinutes(5),
            'last_heartbeat_at' => now()->subMinutes(5),
        ])->save();

        $unlocked = Diagram::factory()->for($user, 'author')->create(['title' => 'Not locked']);

        $response = $this->actingAs($user)->get('/diagrams?locked=1');

        $response->assertOk();
        $response->assertSee($locked->title);
        $response->assertSee($expiredLock->title);
        $response->assertDontSee($unlocked->title);
    }

    public function test_recently_modified_filter_shows_only_diagrams_updated_within_the_last_week(): void
    {
        $user = User::factory()->create();

        $recent = Diagram::factory()->for($user, 'author')->create(['title' => 'Recently touched']);

        $stale = Diagram::factory()->for($user, 'author')->create(['title' => 'Untouched for a while']);
        $stale->forceFill(['updated_at' => now()->subDays(30)])->save();

        $response = $this->actingAs($user)->get('/diagrams?recent=1');

        $response->assertOk();
        $response->assertSee($recent->title);
        $response->assertDontSee($stale->title);
    }

    public function test_owner_and_collaborator_filters_combined_show_the_union_not_the_intersection(): void
    {
        $user = User::factory()->create();

        $owned = Diagram::factory()->for($user, 'author')->create(['title' => 'Owned by me']);

        $collaborated = Diagram::factory()->create(['title' => 'Collaborating on this']);
        DiagramCollaborator::create([
            'diagram_id' => $collaborated->id,
            'user_id' => $user->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $unrelated = Diagram::factory()->create(['title' => 'Neither owned nor collaborated']);

        $response = $this->actingAs($user)->get('/diagrams?owner=1&collaborator=1');

        $response->assertOk();
        $response->assertSee($owned->title);
        $response->assertSee($collaborated->title);
        $this->assertFalse($response->viewData('diagrams')->getCollection()->contains('id', $unrelated->id));
    }

    public function test_filters_combine_with_search(): void
    {
        $user = User::factory()->create();

        $match = Diagram::factory()->for($user, 'author')->create(['title' => 'Owned Loan Process']);
        $ownedButNoMatch = Diagram::factory()->for($user, 'author')->create(['title' => 'Owned Unrelated']);
        $matchButNotOwned = Diagram::factory()->create(['title' => 'Loan Process By Another']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'Loan', 'owner' => 1]));

        $response->assertOk();
        $response->assertSee($match->title);
        $response->assertDontSee($ownedButNoMatch->title);
        $this->assertFalse($response->viewData('diagrams')->getCollection()->contains('id', $matchButNotOwned->id));
    }

    public function test_filters_never_surface_a_hidden_diagram_the_user_cannot_see(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Hidden Owned Diagram',
            'workspace_id' => $workspace->id,
        ]);

        $stranger = User::factory()->create();

        $response = $this->actingAs($owner)->get('/diagrams?owner=1');
        $response->assertOk();
        $response->assertSee($hidden->title);

        $response = $this->actingAs($stranger)->get('/diagrams?recent=1');
        $response->assertOk();
        $response->assertDontSee($hidden->title);
    }

    public function test_sort_by_last_modified_ascending_orders_oldest_first(): void
    {
        $user = User::factory()->create();

        $older = Diagram::factory()->for($user, 'author')->create(['title' => 'Older Diagram']);
        $older->forceFill(['updated_at' => now()->subDays(5)])->save();

        $newer = Diagram::factory()->for($user, 'author')->create(['title' => 'Newer Diagram']);
        $newer->forceFill(['updated_at' => now()])->save();

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['sort' => 'updated_at', 'direction' => 'asc']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, $newer->title),
            strpos($content, $older->title)
        );
    }

    public function test_list_is_paginated_server_side(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->count(25)->create();

        $firstPage = $this->actingAs($user)->get('/diagrams');
        $firstPage->assertOk();
        $firstPage->assertSee('page=2');

        $secondPage = $this->actingAs($user)->get('/diagrams?page=2');
        $secondPage->assertOk();
    }

    public function test_pagination_links_preserve_the_search_and_filter_query_string(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->count(25)->create(['title' => 'Paged Process']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'Paged', 'owner' => 1]));

        $response->assertOk();
        $response->assertSee('q=Paged', false);
        $response->assertSee('owner=1', false);
    }
}

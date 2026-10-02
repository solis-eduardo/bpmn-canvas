<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiagramVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function hiddenDiagram(User $owner, Workspace $workspace): Diagram
    {
        return Diagram::factory()->for($owner, 'author')->create(['workspace_id' => $workspace->id]);
    }

    public function test_the_list_omits_a_hidden_diagram_for_a_user_without_access(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams');

        $response->assertOk();
        $response->assertDontSee($hidden->title);
    }

    public function test_unified_results_include_openable_and_discoverable_but_not_workspace_hidden_diagrams(): void
    {
        $owner = User::factory()->create();
        $openable = Diagram::factory()->for($owner, 'author')->create(['title' => 'My openable diagram']);
        $inaccessible = Diagram::factory()->for(User::factory()->create(), 'author')->create([
            'title' => 'Discoverable but inaccessible',
        ]);

        $response = $this->actingAs($owner)->get('/diagrams');

        $response->assertOk();
        $listedIds = $response->viewData('diagrams')->getCollection()->pluck('id');
        $this->assertTrue($listedIds->contains($openable->id));
        $this->assertTrue($listedIds->contains($inaccessible->id));
    }

    public function test_unified_search_only_exposes_title_and_diagram_owner_for_discoverable_diagrams(): void
    {
        $owner = User::factory()->create(['name' => 'Diagram Owner Name']);
        $author = User::factory()->create(['name' => 'Original Author Name']);
        $match = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create([
            'title' => 'Searchable discovery diagram',
            'slug' => 'private-content-slug',
        ]);
        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $workspaceOwner->id]);
        $this->addMember($workspace, $workspaceOwner);
        $hidden = Diagram::factory()->for($workspaceOwner, 'author')->create([
            'title' => 'Workspace secret matching query',
            'workspace_id' => $workspace->id,
        ]);
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams?q=discovery');

        $response->assertOk();
        $response->assertSee('id="diagram-search-results"', false);
        $response->assertSee('scope="col"', false);
        $response->assertSee($match->title);
        $response->assertSee($owner->name);
        $response->assertSee(__('messages.request_access'));
        $response->assertDontSee('id="diagram-discovery-table"', false);
        $response->assertSee(route('diagrams.collaborators.store', [
            'slug' => $match->slug,
            'q' => 'discovery',
        ]), false);
        $response->assertDontSee($author->name);
        $response->assertDontSee($hidden->title);
        $response->assertDontSee('href="'.route('diagrams.show', $match->slug).'"', false);
    }

    public function test_legacy_discovery_search_redirects_to_unified_search_and_preserves_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/diagrams?'.http_build_query([
                'discover' => 'loan',
                'tag' => 'finance',
                'owner' => '1',
                'workspace' => 'none',
            ]))
            ->assertRedirect(route('diagrams.index', [
                'q' => 'loan',
                'tag' => 'finance',
                'owner' => '1',
                'workspace' => 'none',
            ]));
    }

    public function test_the_list_still_shows_a_hidden_diagram_to_a_collaborator_who_is_not_a_member(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $collaborator = User::factory()->create();
        $hidden->collaborators()->create([
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($collaborator)->get('/diagrams');

        $response->assertOk();
        $response->assertSee($hidden->title);
    }

    public function test_the_list_shows_a_hidden_diagram_to_a_workspace_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $response = $this->actingAs($member)->get('/diagrams');

        $response->assertOk();
        $response->assertSee($hidden->title);
    }

    public function test_a_diagram_outside_any_workspace_stays_visible_to_every_user(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams');

        $response->assertOk();
        $this->assertTrue($response->viewData('diagrams')->getCollection()->contains('id', $diagram->id));
    }

    public function test_administrator_sees_a_hidden_diagram_in_the_list(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/diagrams');

        $response->assertOk();
        $response->assertSee($hidden->title);
    }

    public static function directDiagramRoutesProvider(): array
    {
        return [
            'editor' => [fn (Diagram $d) => ['GET', "/diagrams/{$d->slug}"]],
            'content' => [fn (Diagram $d) => ['GET', "/diagrams/{$d->slug}/content"]],
            'versions' => [fn (Diagram $d) => ['GET', "/diagrams/{$d->slug}/versions"]],
            'fork' => [fn (Diagram $d) => ['POST', "/diagrams/{$d->slug}/fork"]],
            'request access' => [fn (Diagram $d) => ['POST', "/diagrams/{$d->slug}/collaborators"]],
        ];
    }

    #[DataProvider('directDiagramRoutesProvider')]
    public function test_direct_url_access_to_a_hidden_diagram_answers_404_never_403(callable $routeFor): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $stranger = User::factory()->create();

        [$method, $uri] = $routeFor($hidden);

        $payload = $uri === "/diagrams/{$hidden->slug}/collaborators" ? ['user_id' => $stranger->id] : [];

        $response = $this->actingAs($stranger)->json($method, $uri, $payload);

        $response->assertNotFound();
    }

    public function test_administrator_can_open_a_hidden_diagram_they_are_not_a_member_of(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get("/diagrams/{$hidden->slug}")->assertOk();
        $this->actingAs($admin)->getJson("/diagrams/{$hidden->slug}/content")->assertOk();
    }

    public function test_an_administrator_who_can_merely_see_a_diagram_does_not_steal_its_editing_lock(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}");

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get("/diagrams/{$diagram->slug}")->assertOk();

        $this->assertSame($owner->id, $diagram->fresh()->locked_by);
    }

    public function test_an_administrator_who_cannot_edit_cannot_request_the_editing_lock(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}");

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->postJson("/diagrams/{$diagram->slug}/lock")->assertForbidden();

        $this->assertSame($owner->id, $diagram->fresh()->locked_by);
    }

    public function test_a_share_of_a_hidden_diagram_still_works_in_the_public_viewer(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = $this->hiddenDiagram($owner, $workspace);

        $share = Share::factory()->for($hidden, 'diagram')->create([
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
    }
}

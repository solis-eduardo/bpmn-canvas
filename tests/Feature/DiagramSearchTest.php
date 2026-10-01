<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_a_diagram_by_a_partial_title_case_insensitively(): void
    {
        $user = User::factory()->create();
        $match = Diagram::factory()->for($user, 'author')->create(['title' => 'Loan Approval Process']);
        $other = Diagram::factory()->for($user, 'author')->create(['title' => 'Unrelated']);

        $response = $this->actingAs($user)->get('/diagrams?q=loan');

        $response->assertOk();
        $response->assertSee($match->title);
        $response->assertDontSee($other->title);
    }

    public function test_search_matches_a_diagram_by_a_partial_slug(): void
    {
        $user = User::factory()->create();
        $match = Diagram::factory()->for($user, 'author')->create(['title' => 'Something', 'slug' => 'special-slug-123']);
        $other = Diagram::factory()->for($user, 'author')->create(['title' => 'Another']);

        $response = $this->actingAs($user)->get('/diagrams?q=special-slug');

        $response->assertOk();
        $response->assertSee($match->title);
        $response->assertDontSee($other->title);
    }

    public function test_an_empty_search_term_lists_every_visible_diagram(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagrams?q=');

        $response->assertOk();
        $response->assertSee($diagram->title);
    }

    public function test_percent_and_underscore_in_the_search_term_are_treated_literally_not_as_wildcards(): void
    {
        $user = User::factory()->create();
        $literalMatch = Diagram::factory()->for($user, 'author')->create(['title' => '50% Report']);
        $shouldNotMatch = Diagram::factory()->for($user, 'author')->create(['title' => '50X Report']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => '50%']));

        $response->assertOk();
        $response->assertSee($literalMatch->title);
        $response->assertDontSee($shouldNotMatch->title);
    }

    public function test_a_literal_backslash_in_the_search_term_still_matches_literally(): void
    {
        $user = User::factory()->create();
        $match = Diagram::factory()->for($user, 'author')->create(['title' => 'A\\B Diagram']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => 'A\\B']));

        $response->assertOk();
        $response->assertSee($match->title);
    }

    public function test_search_with_no_matches_shows_a_no_results_message_not_the_empty_list_message(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->create(['title' => 'Existing Diagram']);

        $response = $this->actingAs($user)->get('/diagrams?q=nothing-matches-this');

        $response->assertOk();
        $response->assertSee(__('messages.no_diagrams_match_search'));
        $response->assertDontSee(__('messages.no_diagrams_yet'));
    }

    public function test_search_never_surfaces_a_hidden_diagram_the_user_cannot_see(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Secret Onboarding Flow',
            'workspace_id' => $workspace->id,
        ]);

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams?q=Secret');

        $response->assertOk();
        $response->assertDontSee($hidden->title);
    }

    public function test_search_still_surfaces_a_hidden_diagram_to_a_workspace_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Team Onboarding Flow',
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($member)->get('/diagrams?q=Onboarding');

        $response->assertOk();
        $response->assertSee($hidden->title);
    }
}

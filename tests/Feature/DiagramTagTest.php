<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tags are global (name, unique) and created implicitly on first use
 * (CONTEXT.md, "Tag"; issue #40). Anyone who can edit a Diagram — Diagram
 * Owner, approved Collaborator, or Member of its Workspace — can apply or
 * remove its Tags; everyone else is refused.
 */
class DiagramTagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_the_diagram_owner_can_apply_a_tag(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $response = $this->actingAs($owner)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Onboarding']);

        $response->assertRedirect();
        $this->assertTrue($diagram->tags()->where('name', 'Onboarding')->exists());
    }

    public function test_an_approved_collaborator_can_apply_and_remove_tags(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($collaborator)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Finance'])
            ->assertRedirect();
        $tag = Tag::where('name', 'Finance')->firstOrFail();
        $this->assertTrue($diagram->tags()->where('tags.id', $tag->id)->exists());

        $this->actingAs($collaborator)->delete("/diagrams/{$diagram->slug}/tags/{$tag->id}")
            ->assertRedirect();
        $this->assertFalse($diagram->tags()->where('tags.id', $tag->id)->exists());
    }

    public function test_a_workspace_member_can_apply_tags(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $workspace->addMember($member);
        $diagram = Diagram::factory()->for($owner, 'author')->create(['workspace_id' => $workspace->id]);

        $this->actingAs($member)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Team'])
            ->assertRedirect();

        $this->assertTrue($diagram->tags()->where('name', 'Team')->exists());
    }

    public function test_a_user_who_cannot_edit_the_diagram_is_refused(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($stranger)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertFalse($diagram->tags()->where('name', 'Nope')->exists());
    }

    public function test_removing_a_tag_is_refused_to_a_user_who_cannot_edit_the_diagram(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $tag = Tag::create(['name' => 'Kept']);
        $diagram->tags()->attach($tag);

        $this->actingAs($stranger)->delete("/diagrams/{$diagram->slug}/tags/{$tag->id}")
            ->assertForbidden();

        $this->assertTrue($diagram->tags()->where('tags.id', $tag->id)->exists());
    }

    public function test_applying_a_tag_to_a_hidden_diagram_answers_404_not_403_for_an_outsider(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $diagram = Diagram::factory()->for($owner, 'author')->create(['workspace_id' => $workspace->id]);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Nope'])
            ->assertNotFound();
    }

    public function test_a_tag_is_created_implicitly_on_first_use_and_reused_by_name_across_diagrams(): void
    {
        $user = User::factory()->create();
        $first = Diagram::factory()->for($user, 'author')->create();
        $second = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->post("/diagrams/{$first->slug}/tags", ['name' => 'Shared'])->assertRedirect();
        $this->actingAs($user)->post("/diagrams/{$second->slug}/tags", ['name' => 'Shared'])->assertRedirect();

        $this->assertSame(1, Tag::where('name', 'Shared')->count());
        $this->assertTrue($first->tags()->where('name', 'Shared')->exists());
        $this->assertTrue($second->tags()->where('name', 'Shared')->exists());
    }

    public function test_removing_a_tag_from_one_diagram_does_not_delete_it_or_untag_another_diagram(): void
    {
        $user = User::factory()->create();
        $first = Diagram::factory()->for($user, 'author')->create();
        $second = Diagram::factory()->for($user, 'author')->create();
        $tag = Tag::create(['name' => 'Shared']);
        $first->tags()->attach($tag);
        $second->tags()->attach($tag);

        $this->actingAs($user)->delete("/diagrams/{$first->slug}/tags/{$tag->id}")->assertRedirect();

        $this->assertFalse($first->tags()->where('tags.id', $tag->id)->exists());
        $this->assertTrue($second->tags()->where('tags.id', $tag->id)->exists());
        $this->assertTrue(Tag::whereKey($tag->id)->exists());
    }

    public function test_applying_a_tag_requires_a_name(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->post("/diagrams/{$diagram->slug}/tags", ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_the_diagram_list_shows_each_diagrams_tags(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();
        $diagram->tags()->attach(Tag::create(['name' => 'Payroll']));

        $response = $this->actingAs($user)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('Payroll');
    }

    public function test_filtering_the_list_by_tag_shows_only_matching_diagrams(): void
    {
        $user = User::factory()->create();
        $tagged = Diagram::factory()->for($user, 'author')->create(['title' => 'Tagged Diagram']);
        $tagged->tags()->attach(Tag::create(['name' => 'Payroll']));
        $untagged = Diagram::factory()->for($user, 'author')->create(['title' => 'Untagged Diagram']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['tag' => 'Payroll']));

        $response->assertOk();
        $response->assertSee($tagged->title);
        $response->assertDontSee($untagged->title);
    }

    public function test_the_tag_filter_never_surfaces_a_hidden_diagram_the_user_cannot_see(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Hidden Tagged Diagram',
            'workspace_id' => $workspace->id,
        ]);
        $hidden->tags()->attach(Tag::create(['name' => 'Payroll']));

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams?'.http_build_query(['tag' => 'Payroll']));

        $response->assertOk();
        $response->assertDontSee($hidden->title);
    }
}

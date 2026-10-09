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
        $this->assertTrue($diagram->tags()->whereJsonContainsLocale('name', 'en', 'Onboarding')->exists());
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
        $tag = Tag::findFromString('Finance');
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
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $member);
        $diagram = Diagram::factory()->for($owner, 'author')->create(['workspace_id' => $workspace->id]);

        $this->actingAs($member)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Team'])
            ->assertRedirect();

        $this->assertTrue($diagram->tags()->whereJsonContainsLocale('name', 'en', 'Team')->exists());
    }

    public function test_a_user_who_cannot_edit_the_diagram_is_refused(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($stranger)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertFalse($diagram->tags()->whereJsonContainsLocale('name', 'en', 'Nope')->exists());
    }

    public function test_removing_a_tag_is_refused_to_a_user_who_cannot_edit_the_diagram(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $tag = Tag::findOrCreate('Kept');
        $diagram->attachTag($tag);

        $this->actingAs($stranger)->delete("/diagrams/{$diagram->slug}/tags/{$tag->id}")
            ->assertForbidden();

        $this->assertTrue($diagram->tags()->where('tags.id', $tag->id)->exists());
    }

    public function test_applying_a_tag_to_a_hidden_diagram_answers_404_not_403_for_an_outsider(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
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

        $this->assertSame(1, Tag::count());
        $this->assertTrue($first->tags()->whereJsonContainsLocale('name', 'en', 'Shared')->exists());
        $this->assertTrue($second->tags()->whereJsonContainsLocale('name', 'en', 'Shared')->exists());
    }

    public function test_removing_a_tag_from_one_diagram_does_not_delete_it_or_untag_another_diagram(): void
    {
        $user = User::factory()->create();
        $first = Diagram::factory()->for($user, 'author')->create();
        $second = Diagram::factory()->for($user, 'author')->create();
        $tag = Tag::findOrCreate('Shared');
        $first->attachTag($tag);
        $second->attachTag($tag);

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
        $diagram->attachTag(Tag::findOrCreate('Payroll'));

        $response = $this->actingAs($user)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('Payroll');
    }

    public function test_filtering_the_list_by_tag_shows_only_matching_diagrams(): void
    {
        $user = User::factory()->create();
        $tagged = Diagram::factory()->for($user, 'author')->create(['title' => 'Tagged Diagram']);
        $tagged->attachTag(Tag::findOrCreate('Payroll'));
        $untagged = Diagram::factory()->for($user, 'author')->create(['title' => 'Untagged Diagram']);

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['tags' => ['Payroll']]));

        $response->assertOk();
        $response->assertSee($tagged->title);
        $response->assertDontSee($untagged->title);
    }

    public function test_multiple_tags_narrow_the_list_with_and(): void
    {
        $user = User::factory()->create();
        $both = Diagram::factory()->for($user, 'author')->create(['title' => 'Has both']);
        $both->attachTags(['Payroll', 'Urgent']);
        $one = Diagram::factory()->for($user, 'author')->create(['title' => 'Has one']);
        $one->attachTag('Payroll');

        $response = $this->actingAs($user)->get('/diagrams?'.http_build_query(['tags' => ['Payroll', 'Urgent']]));

        $response->assertSee('Has both');
        $response->assertDontSee('Has one');
    }

    public function test_an_unknown_tag_matches_nothing(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->create(['title' => 'Plain diagram']);

        $this->actingAs($user)->get('/diagrams?'.http_build_query(['tags' => ['Nope']]))
            ->assertDontSee('Plain diagram');
    }

    public function test_the_tag_filter_never_surfaces_a_hidden_diagram_the_user_cannot_see(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Hidden Tagged Diagram',
            'workspace_id' => $workspace->id,
        ]);
        $hidden->attachTag(Tag::findOrCreate('Payroll'));

        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get('/diagrams?'.http_build_query(['tags' => ['Payroll']]));

        $response->assertOk();
        $response->assertDontSee($hidden->title);
    }

    public function test_the_same_tag_typed_under_another_ui_locale_is_the_same_tag(): void
    {
        $user = User::factory()->create();
        $first = Diagram::factory()->for($user, 'author')->create();
        $second = Diagram::factory()->for($user, 'author')->create();

        $this->actingAs($user)->post("/diagrams/{$first->slug}/tags", ['name' => 'Shared'])->assertRedirect();
        app()->setLocale('pt_BR');
        $this->actingAs($user)->post("/diagrams/{$second->slug}/tags", ['name' => 'Shared'])->assertRedirect();

        $this->assertSame(1, Tag::count());
        $this->actingAs($user)->get('/diagrams?'.http_build_query(['tags' => ['Shared']]))
            ->assertSee($first->title)->assertSee($second->title);
    }

    public function test_the_edit_screen_shows_the_tags_as_selected_chips_of_one_multi_select(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $tag = Tag::findOrCreate('Onboarding');
        $diagram->attachTag($tag);
        Tag::findOrCreate('Finance');

        $response = $this->actingAs($owner)->get(route('diagrams.edit', $diagram->slug));

        $response->assertOk();
        $response->assertSee('<option value="Onboarding" selected>Onboarding</option>', false);
        $response->assertSee('<option value="Finance">Finance</option>', false);
        $response->assertSee('name="tags[]"', false);
        $response->assertSee('data-tag-select', false);
        $response->assertSee('multiple', false);
        $response->assertSee(route('diagrams.settings.update', $diagram->slug), false);
        $response->assertDontSee(route('diagrams.tags.destroy', [$diagram->slug, $tag->id]), false);
        $response->assertSee('href="'.route('diagrams.show', $diagram->slug).'"', false);
    }

    public function test_the_edit_screen_is_refused_to_a_user_who_cannot_edit_the_diagram(): void
    {
        $diagram = Diagram::factory()->for(User::factory()->create(), 'author')->create();

        $this->actingAs(User::factory()->create())->get(route('diagrams.edit', $diagram->slug))
            ->assertForbidden();
    }

    public function test_applying_and_removing_a_tag_returns_to_the_edit_screen(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($owner)->post("/diagrams/{$diagram->slug}/tags", ['name' => 'Back'])
            ->assertRedirect(route('diagrams.edit', $diagram->slug));

        $this->actingAs($owner)->delete("/diagrams/{$diagram->slug}/tags/".Tag::findFromString('Back')->id)
            ->assertRedirect(route('diagrams.edit', $diagram->slug));
    }

    public function test_the_listing_no_longer_registers_tags_but_keeps_the_tag_filter(): void
    {
        $owner = User::factory()->create();
        Diagram::factory()->for($owner, 'author')->create();
        Tag::findOrCreate('Finance');

        $response = $this->actingAs($owner)->get('/diagrams');

        $response->assertOk();
        $response->assertDontSee(__('messages.add_tag_placeholder'), false);
        $response->assertSee(__('messages.filter_tag'), false);
    }

    public function test_the_editor_edits_tags_in_the_info_panel_for_editors(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($owner)->get(route('diagrams.show', $diagram->slug))
            ->assertSee('id="info-tags-form"', false)
            ->assertSee('data-tags-url="'.route('diagrams.tags.sync', $diagram->slug).'"', false)
            ->assertDontSee('id="data-panel"', false);
    }

    public function test_the_listing_filter_offers_existing_tags_in_the_tom_select_picker(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $diagram->attachTag(Tag::findOrCreate('Onboarding'));
        Tag::findOrCreate('Finance');

        $response = $this->actingAs($owner)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('data-tag-select', false);
        $response->assertSee('<option value="Finance">Finance</option>', false);
        $response->assertSee('<option value="Onboarding">Onboarding</option>', false);
    }

    public function test_syncing_applies_new_tags_creating_them_and_detaches_the_ones_removed(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $diagram->attachTags(['Keep', 'Drop']);
        Tag::findOrCreate('Existing');

        $this->actingAs($owner)->put("/diagrams/{$diagram->slug}/tags", ['tags' => ['Keep', 'Existing', ' Brand new ']])
            ->assertRedirect(route('diagrams.show', $diagram->slug));

        $names = $diagram->fresh()->tags->pluck('name')->sort()->values()->all();
        $this->assertSame(['Brand new', 'Existing', 'Keep'], $names);
        $this->assertTrue(Tag::whereJsonContainsLocale('name', 'en', 'Drop')->exists());
    }

    public function test_syncing_with_no_tags_clears_them_all(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $diagram->attachTags(['One', 'Two']);

        $this->actingAs($owner)->put("/diagrams/{$diagram->slug}/tags")->assertRedirect();

        $this->assertCount(0, $diagram->fresh()->tags);
    }

    public function test_syncing_tags_is_refused_to_a_user_who_cannot_edit_the_diagram(): void
    {
        $diagram = Diagram::factory()->for(User::factory()->create(), 'author')->create();
        $diagram->attachTag('Kept');

        $this->actingAs(User::factory()->create())->put("/diagrams/{$diagram->slug}/tags", ['tags' => []])
            ->assertForbidden();

        $this->assertCount(1, $diagram->fresh()->tags);
    }

    public function test_an_invalid_tag_shows_its_error_under_the_picker(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $response = $this->actingAs($owner)
            ->from(route('diagrams.edit', $diagram->slug))
            ->followingRedirects()
            ->put("/diagrams/{$diagram->slug}/tags", ['tags' => [str_repeat('x', 256)], '_form' => "diagram-tags-{$diagram->id}"]);

        $response->assertSee('aria-invalid="true"', false);
        $response->assertSee('role="alert"', false);
    }
}

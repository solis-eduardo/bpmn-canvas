<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Renaming a Diagram and transferring its Diagram Owner role from the edit
 * form and from the Editor's info panel (issue #195). The panel talks to the
 * JSON endpoints; the form posts to its own endpoint and gets redirects.
 */
class DiagramSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function ownedDiagram(User $owner, array $attributes = []): Diagram
    {
        return Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create($attributes);
    }

    private function collaborate(Diagram $diagram, User $user): void
    {
        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
    }

    private function lockedByOther(Diagram $diagram, User $holder): void
    {
        $this->collaborate($diagram, $holder);
        $diagram->forceFill(['locked_by' => $holder->id, 'locked_at' => now()])->save();
    }

    // --- edit form -------------------------------------------------------

    public function test_the_owner_sees_title_owner_and_tags_on_the_edit_form(): void
    {
        $owner = User::factory()->create();
        User::factory()->create(['name' => 'Cass Candidate']);
        $diagram = $this->ownedDiagram($owner, ['title' => 'Order to Cash']);

        $this->actingAs($owner)->get(route('diagrams.edit', $diagram->slug))
            ->assertOk()
            ->assertSee('action="'.route('diagrams.settings.update', $diagram->slug).'"', false)
            ->assertSee('name="title"', false)
            ->assertSee('value="Order to Cash"', false)
            ->assertSee('name="user_id"', false)
            ->assertSee('Cass Candidate')
            ->assertSee('name="give_up_access"', false)
            ->assertSee('name="tags[]"', false);
    }

    public function test_a_collaborator_sees_title_and_tags_but_not_the_owner_control(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);
        $this->collaborate($diagram, $collaborator);

        $this->actingAs($collaborator)->get(route('diagrams.edit', $diagram->slug))
            ->assertOk()
            ->assertSee('name="title"', false)
            ->assertSee('name="tags[]"', false)
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee('name="give_up_access"', false);
    }

    public function test_an_administrator_who_cannot_edit_sees_only_the_owner_control(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->ownedDiagram(User::factory()->create());

        $this->actingAs($admin)->get(route('diagrams.edit', $diagram->slug))
            ->assertOk()
            ->assertSee('name="user_id"', false)
            ->assertDontSee('name="title"', false)
            ->assertDontSee('name="tags[]"', false);
    }

    public function test_the_edit_form_is_refused_to_a_user_who_can_neither_edit_nor_transfer(): void
    {
        $diagram = $this->ownedDiagram(User::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('diagrams.edit', $diagram->slug))
            ->assertForbidden();
    }

    public function test_the_listing_links_to_the_edit_form_for_the_diagram_owner(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->get('/diagrams')
            ->assertSee('href="'.route('diagrams.edit', $diagram->slug).'"', false);
    }

    // --- rename through the form ----------------------------------------

    public function test_the_form_renames_the_diagram_and_the_old_url_keeps_redirecting(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Old Name']);
        $oldSlug = $diagram->slug;

        $this->actingAs($owner)->put(route('diagrams.settings.update', $oldSlug), [
            'title' => 'Brand New Name',
            'tags' => [],
        ])->assertRedirect(route('diagrams.edit', 'brand-new-name'))
            ->assertSessionHas('status');

        $this->assertSame('Brand New Name', $diagram->fresh()->title);
        $this->actingAs($owner)->get('/diagrams/'.$oldSlug)->assertRedirect('/diagrams/brand-new-name');
    }

    public function test_save_and_exit_returns_to_the_listing(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), [
            'title' => 'Another',
            'submit_action' => 'exit',
        ])->assertRedirect(route('diagrams.index'));
    }

    public function test_an_unchanged_title_does_not_reserve_a_new_old_slug(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Stable']);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Stable'])
            ->assertRedirect(route('diagrams.edit', $diagram->slug));

        $this->assertDatabaseMissing('diagram_old_slugs', ['diagram_id' => $diagram->id]);
    }

    public function test_the_form_rejects_an_empty_or_too_long_title(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Keep Me']);
        $url = route('diagrams.settings.update', $diagram->slug);

        $this->actingAs($owner)->from(route('diagrams.edit', $diagram->slug))->put($url, ['title' => ''])
            ->assertRedirect(route('diagrams.edit', $diagram->slug))
            ->assertSessionHasErrors('title');

        $this->actingAs($owner)->put($url, ['title' => str_repeat('a', 256)])
            ->assertSessionHasErrors('title');

        $this->assertSame('Keep Me', $diagram->fresh()->title);
    }

    public function test_the_form_refuses_a_rename_while_someone_else_holds_the_lock(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Locked']);
        $this->lockedByOther($diagram, User::factory()->create());

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Nope'])
            ->assertStatus(423);

        $this->assertSame('Locked', $diagram->fresh()->title);
    }

    public function test_the_form_refuses_a_rename_from_someone_who_cannot_edit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->ownedDiagram(User::factory()->create(), ['title' => 'Untouched']);

        $this->actingAs($admin)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Untouched', $diagram->fresh()->title);
    }

    // --- tags through the form ------------------------------------------

    public function test_the_form_syncs_tags_and_an_empty_selection_clears_them(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);
        $diagram->attachTag(Tag::findOrCreate('Old'));
        $url = route('diagrams.settings.update', $diagram->slug);

        $this->actingAs($owner)->put($url, ['title' => $diagram->title, 'tags' => ['Finance', 'HR']])->assertRedirect();
        $this->assertEqualsCanonicalizing(['Finance', 'HR'], $diagram->fresh()->tags->pluck('name')->all());

        $this->actingAs($owner)->put($url, ['title' => $diagram->title])->assertRedirect();
        $this->assertSame([], $diagram->fresh()->tags->pluck('name')->all());
    }

    public function test_a_user_who_cannot_edit_cannot_change_tags_through_the_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->ownedDiagram(User::factory()->create());

        $this->actingAs($admin)->put(route('diagrams.settings.update', $diagram->slug), ['tags' => ['Sneaky']])
            ->assertForbidden();
    }

    // --- transfer through the form --------------------------------------

    public function test_the_form_transfers_ownership_and_notifies_the_new_owner(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), [
            'title' => $diagram->title,
            'user_id' => $newOwner->id,
        ])->assertRedirect(route('diagrams.edit', $diagram->slug));

        $this->assertSame($newOwner->id, $diagram->fresh()->owner_id);
        $this->assertDatabaseHas('collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $owner->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
        $this->assertCount(1, $newOwner->fresh()->notifications);
    }

    public function test_giving_up_access_through_the_form_sends_the_user_to_the_listing(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), [
            'title' => $diagram->title,
            'user_id' => $newOwner->id,
            'give_up_access' => '1',
        ])->assertRedirect(route('diagrams.index'));

        $this->assertDatabaseMissing('collaborators', ['diagram_id' => $diagram->id, 'user_id' => $owner->id]);
    }

    public function test_an_administrator_can_transfer_through_the_form_without_editing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $newOwner = User::factory()->create();
        $diagram = $this->ownedDiagram(User::factory()->create());

        $this->actingAs($admin)->put(route('diagrams.settings.update', $diagram->slug), ['user_id' => $newOwner->id])
            ->assertRedirect(route('diagrams.edit', $diagram->slug));

        $this->assertSame($newOwner->id, $diagram->fresh()->owner_id);
    }

    public function test_a_collaborator_cannot_transfer_through_the_form(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);
        $this->collaborate($diagram, $collaborator);

        $this->actingAs($collaborator)->put(route('diagrams.settings.update', $diagram->slug), [
            'title' => $diagram->title,
            'user_id' => $collaborator->id,
        ])->assertForbidden();

        $this->assertSame($owner->id, $diagram->fresh()->owner_id);
    }

    public function test_the_form_rejects_an_unknown_user(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), [
            'title' => $diagram->title,
            'user_id' => 999999,
        ])->assertSessionHasErrors('user_id');

        $this->assertSame($owner->id, $diagram->fresh()->owner_id);
    }

    public function test_nothing_is_applied_when_any_part_of_the_submission_is_refused(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Before']);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), [
            'title' => 'After',
            'user_id' => 999999,
        ])->assertSessionHasErrors('user_id');

        $this->assertSame('Before', $diagram->fresh()->title);
    }

    public function test_a_failure_in_the_tags_step_rolls_back_the_rename_and_the_transfer(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Before']);
        $oldSlug = $diagram->slug;

        Tag::creating(function (): void {
            throw new \RuntimeException('forced tag failure');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->put(route('diagrams.settings.update', $oldSlug), [
                'title' => 'After',
                'user_id' => $newOwner->id,
                'tags' => ['Forced'],
            ]);
            $this->fail('The forced tag failure should surface.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced tag failure', $e->getMessage());
        }

        $fresh = $diagram->fresh();
        $this->assertSame('Before', $fresh->title);
        $this->assertSame($oldSlug, $fresh->slug);
        $this->assertSame($owner->id, $fresh->owner_id);
        $this->assertDatabaseMissing('diagram_old_slugs', ['old_slug' => $oldSlug]);
        Storage::disk('diagrams')->assertExists($oldSlug.'/diagram.bpmn');
        Storage::disk('diagrams')->assertMissing('after/diagram.bpmn');
        $this->assertCount(0, $newOwner->fresh()->notifications);
    }

    public function test_a_directory_move_that_fails_rolls_back_the_rename_and_the_transfer(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Before']);
        $oldSlug = $diagram->slug;
        // A leftover directory already sits at the target, so the move fails.
        Storage::disk('diagrams')->put('after/leftover.txt', 'stale');

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->put(route('diagrams.settings.update', $oldSlug), [
                'title' => 'After',
                'user_id' => $newOwner->id,
            ]);
            $this->fail('The failed directory move should surface.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Could not move the Diagram directory', $e->getMessage());
        }

        $fresh = $diagram->fresh();
        $this->assertSame('Before', $fresh->title);
        $this->assertSame($oldSlug, $fresh->slug);
        $this->assertSame($owner->id, $fresh->owner_id);
        $this->assertDatabaseMissing('diagram_old_slugs', ['old_slug' => $oldSlug]);
        Storage::disk('diagrams')->assertExists($oldSlug.'/diagram.bpmn');
        $this->assertCount(0, $newOwner->fresh()->notifications);
    }

    // --- JSON endpoints (Editor panel) ----------------------------------

    public function test_the_json_rename_requires_edit_rights(): void
    {
        $diagram = $this->ownedDiagram(User::factory()->create(), ['title' => 'Mine']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->putJson('/diagrams/'.$diagram->slug, ['title' => 'Stolen'])
            ->assertForbidden();

        $this->assertSame('Mine', $diagram->fresh()->title);
    }

    public function test_the_json_rename_is_refused_while_someone_else_holds_the_lock(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Locked']);
        $this->lockedByOther($diagram, User::factory()->create());

        $this->actingAs($owner)->putJson('/diagrams/'.$diagram->slug, ['title' => 'Nope'])
            ->assertStatus(423);
    }

    public function test_the_json_rename_reports_validation_errors(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->putJson('/diagrams/'.$diagram->slug, ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_the_json_transfer_reports_an_unknown_user(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", ['user_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_the_json_tags_sync_answers_json_for_the_editor_panel(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/tags", ['tags' => ['Finance']])
            ->assertOk()
            ->assertExactJson(['tags' => ['Finance']]);
    }

    public function test_the_sub_resources_still_answer_to_a_slug_the_editor_page_renamed_away_from(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->ownedDiagram($owner, ['title' => 'Before']);
        $oldSlug = $diagram->slug;

        $this->actingAs($owner)->putJson('/diagrams/'.$oldSlug, ['title' => 'After'])->assertOk();

        $this->actingAs($owner)->putJson("/diagrams/{$oldSlug}/tags", ['tags' => ['Kept']])->assertOk();
        $this->actingAs($owner)->getJson("/diagrams/{$oldSlug}/collaborators")->assertOk();
        $this->actingAs($owner)->getJson("/diagrams/{$oldSlug}/shares")->assertOk();
    }

    // --- Editor info panel ----------------------------------------------

    public function test_the_editor_info_panel_offers_rename_transfer_and_tags_to_the_owner(): void
    {
        $owner = User::factory()->create();
        User::factory()->create(['name' => 'Cass Candidate']);
        $diagram = $this->ownedDiagram($owner);

        $this->actingAs($owner)->get(route('diagrams.show', $diagram->slug))
            ->assertOk()
            ->assertSee('id="info-title-edit"', false)
            ->assertSee('id="info-owner-transfer"', false)
            ->assertSee('id="owner-transfer-dialog"', false)
            ->assertSee('Cass Candidate')
            ->assertSee('id="info-tags-form"', false)
            ->assertSee('data-owner-url="'.route('diagrams.owner.transfer', $diagram->slug).'"', false)
            ->assertSee('href="'.route('diagrams.edit', $diagram->slug).'"', false);
    }

    public function test_the_editor_info_panel_hides_the_transfer_control_from_a_collaborator(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = $this->ownedDiagram($owner);
        $this->collaborate($diagram, $collaborator);

        $this->actingAs($collaborator)->get(route('diagrams.show', $diagram->slug))
            ->assertOk()
            ->assertSee('id="info-title-edit"', false)
            ->assertSee('id="info-tags-form"', false)
            ->assertDontSee('id="info-owner-transfer"', false)
            ->assertDontSee('id="owner-transfer-dialog"', false);
    }
}

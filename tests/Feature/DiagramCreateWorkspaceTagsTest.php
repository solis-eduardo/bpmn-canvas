<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Diagram creation form sets the Workspace and Tags up front (issue #169).
 */
class DiagramCreateWorkspaceTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_the_form_lists_only_workspaces_the_user_is_a_member_of(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Mine WS']);
        $theirs = Workspace::factory()->create(['owner_id' => $other->id, 'name' => 'Theirs WS']);
        $this->addMember($mine, $user);
        $this->addMember($theirs, $other);

        $this->actingAs($user)->get('/diagrams/create')
            ->assertOk()
            ->assertSee('Mine WS')
            ->assertDontSee('Theirs WS')
            ->assertSee('name="tags[]"', false);
    }

    public function test_the_diagram_is_created_with_the_chosen_workspace_and_tags(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $this->addMember($workspace, $user);
        Tag::findOrCreate('Existing');

        $this->actingAs($user)->post('/diagrams', [
            'title' => 'With Extras',
            'workspace_id' => $workspace->id,
            'tags' => ['Existing', 'Brand New'],
        ])->assertRedirect();

        $diagram = Diagram::where('slug', 'with-extras')->firstOrFail();
        $this->assertSame($workspace->id, $diagram->workspace_id);
        $this->assertEqualsCanonicalizing(['Existing', 'Brand New'], $diagram->tags->pluck('name')->all());
        $this->assertSame(2, Tag::count());
    }

    public function test_workspace_and_tags_are_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/diagrams', ['title' => 'Plain', 'workspace_id' => ''])->assertRedirect();

        $diagram = Diagram::where('slug', 'plain')->firstOrFail();
        $this->assertNull($diagram->workspace_id);
        $this->assertCount(0, $diagram->tags);
    }

    public function test_a_workspace_the_user_is_not_a_member_of_is_a_field_error(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $theirs = Workspace::factory()->create(['owner_id' => $other->id]);
        $this->addMember($theirs, $other);

        $this->actingAs($user)->from('/diagrams/create')->post('/diagrams', [
            'title' => 'Sneaky',
            'workspace_id' => $theirs->id,
        ])->assertRedirect('/diagrams/create')->assertSessionHasErrors('workspace_id');

        $this->assertDatabaseMissing('diagrams', ['title' => 'Sneaky']);
    }

    public function test_an_invalid_tag_is_a_field_error_shown_under_the_tags_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()->from('/diagrams/create')->post('/diagrams', [
            'title' => 'Bad Tag',
            'tags' => [str_repeat('x', 256)],
        ])->assertSee('id="tags-field-error"', false);

        $this->assertDatabaseMissing('diagrams', ['title' => 'Bad Tag']);
    }

    public function test_the_form_is_translated_in_both_locales(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['locale' => 'pt_BR'])->get('/diagrams/create')
            ->assertSee('Sem ambiente')
            ->assertSee('Adicionar tag…');
        $this->actingAs($user)->withSession(['locale' => 'en'])->get('/diagrams/create')
            ->assertSee('No workspace');
    }
}

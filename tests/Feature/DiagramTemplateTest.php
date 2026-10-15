<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\DiagramRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Templates: Diagrams new Diagrams can start from (CONTEXT.md, "Template";
 * issue #255). A Template outside any Workspace is global; one inside a
 * Workspace is offered only to whoever can edit it.
 */
class DiagramTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE_XML = '<?xml version="1.0" encoding="UTF-8"?><bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="D"><bpmn:process id="P"><bpmn:task id="Approve" name="Approve request" /></bpmn:process></bpmn:definitions>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function template(User $owner, array $attributes = []): Diagram
    {
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create($attributes + ['is_template' => true]);
        Storage::disk('diagrams')->put($diagram->disk_path, self::TEMPLATE_XML);

        return $diagram;
    }

    private function workspaceWith(User $owner, User ...$members): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        foreach ([$owner, ...$members] as $member) {
            $workspace->members()->create(['user_id' => $member->id]);
        }

        return $workspace;
    }

    public function test_the_create_form_offers_global_templates_to_everyone(): void
    {
        $owner = User::factory()->create();
        $this->template($owner, ['title' => 'Approval flow']);
        Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['title' => 'Plain diagram']);

        $this->actingAs(User::factory()->create())->get(route('diagrams.create'))
            ->assertOk()
            ->assertSee('name="template_id"', false)
            ->assertSee('Approval flow')
            ->assertDontSee('Plain diagram');
    }

    public function test_a_workspace_template_is_offered_only_to_whoever_can_edit_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->workspaceWith($owner, $member);
        $this->template($owner, ['title' => 'Team scaffold', 'workspace_id' => $workspace->id]);

        $this->actingAs($member)->get(route('diagrams.create'))->assertSee('Team scaffold');
        $this->actingAs(User::factory()->create())->get(route('diagrams.create'))->assertDontSee('Team scaffold');
    }

    public function test_creating_from_a_template_copies_its_content_without_origin_link_or_copy_title(): void
    {
        $owner = User::factory()->create();
        $template = $this->template($owner, ['title' => 'Approval flow']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('diagrams.store'), [
            'title' => 'My process',
            'template_id' => $template->id,
        ])->assertRedirect();

        $diagram = Diagram::where('title', 'My process')->firstOrFail();
        $this->assertSame(self::TEMPLATE_XML, app(DiagramRepository::class)->contentOf($diagram));
        $this->assertNull($diagram->forked_from_id);
        $this->assertFalse((bool) $diagram->is_template);
        $this->assertSame($user->id, $diagram->owner_id);
        $this->assertSame($user->id, $diagram->author_id);
    }

    public function test_without_a_template_the_diagram_starts_from_the_empty_seed(): void
    {
        $this->actingAs(User::factory()->create())->post(route('diagrams.store'), ['title' => 'Blank'])->assertRedirect();

        $this->assertStringContainsString('StartEvent_1', app(DiagramRepository::class)->contentOf(Diagram::where('title', 'Blank')->firstOrFail()));
    }

    public function test_a_template_the_user_cannot_use_is_refused(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $hidden = $this->template($owner, ['workspace_id' => $workspace->id]);
        $notATemplate = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        foreach ([$hidden->id, $notATemplate->id, 999999] as $id) {
            $this->actingAs(User::factory()->create())->post(route('diagrams.store'), ['title' => 'X', 'template_id' => $id])
                ->assertSessionHasErrors('template_id');
        }
        $this->assertDatabaseMissing('diagrams', ['title' => 'X']);
    }

    public function test_the_diagram_owner_can_mark_and_unmark_a_template_from_the_settings_form(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['title' => 'Flow']);

        $this->actingAs($owner)->get(route('diagrams.edit', $diagram->slug))->assertSee('name="is_template"', false);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Flow', 'is_template' => '1'])->assertRedirect();
        $this->assertTrue($diagram->refresh()->is_template);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Flow', 'is_template' => '0'])->assertRedirect();
        $this->assertFalse($diagram->refresh()->is_template);
    }

    public function test_a_form_without_the_field_leaves_the_template_flag_alone(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->template($owner, ['title' => 'Flow']);

        $this->actingAs($owner)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Flow'])->assertRedirect();

        $this->assertTrue($diagram->refresh()->is_template);
    }

    public function test_only_the_diagram_owner_can_change_the_template_flag(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['title' => 'Flow']);
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $collaborator->id, 'status' => Collaborator::STATUS_APPROVED]);

        $this->actingAs($collaborator)->get(route('diagrams.edit', $diagram->slug))->assertDontSee('name="is_template"', false);
        $this->actingAs($collaborator)->put(route('diagrams.settings.update', $diagram->slug), ['title' => 'Flow', 'is_template' => '1'])->assertForbidden();
        $this->assertFalse($diagram->refresh()->is_template);
    }

    public function test_a_fork_of_a_template_is_not_itself_a_template(): void
    {
        $owner = User::factory()->create();
        $template = $this->template($owner);

        $this->actingAs($owner)->post(route('diagrams.fork', $template->slug))->assertRedirect();

        $this->assertFalse((bool) Diagram::where('forked_from_id', $template->id)->firstOrFail()->is_template);
    }
}

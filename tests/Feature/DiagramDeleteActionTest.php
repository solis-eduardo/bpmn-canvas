<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The "Delete diagram" action on the Diagram edit form (issue #264): only the
 * Diagram Owner sees it, it asks for confirmation naming the Diagram, and the
 * server enforces the same policy as the JSON endpoint.
 */
class DiagramDeleteActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function collaboratorOn(Diagram $diagram): User
    {
        $collaborator = User::factory()->create();
        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        return $collaborator;
    }

    public function test_the_edit_form_offers_the_delete_action_to_the_diagram_owner_with_a_named_confirmation(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create(['title' => 'Order <flow>']);

        $this->actingAs($owner)->get(route('diagrams.edit', $diagram->slug))
            ->assertOk()
            ->assertSee(route('diagrams.destroy', $diagram->slug), false)
            ->assertSee(__('messages.delete_diagram'))
            ->assertSee(__('messages.delete_diagram_confirm', ['title' => 'Order <flow>']));
    }

    public function test_the_edit_form_hides_the_delete_action_from_users_who_cannot_delete(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $collaborator = $this->collaboratorOn($diagram);

        $this->actingAs($collaborator)->get(route('diagrams.edit', $diagram->slug))
            ->assertOk()
            ->assertDontSee('name="_method" value="DELETE"', false)
            ->assertDontSee(__('messages.delete_diagram'));
    }

    public function test_deleting_from_the_form_redirects_to_the_list_with_a_success_message(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create(['title' => 'Old process']);

        $this->actingAs($owner)->delete(route('diagrams.destroy', $diagram->slug))
            ->assertRedirect(route('diagrams.index'))
            ->assertSessionHas('status', __('messages.diagram_deleted', ['title' => 'Old process']));

        $this->assertDatabaseMissing('diagrams', ['id' => $diagram->id]);
    }

    public function test_the_json_endpoint_keeps_answering_json(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($owner)->deleteJson(route('diagrams.destroy', $diagram->slug))
            ->assertOk()
            ->assertJson(['deleted' => true]);
    }

    public function test_the_server_refuses_deletion_by_anyone_but_the_diagram_owner(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();

        $this->actingAs($this->collaboratorOn($diagram))->delete(route('diagrams.destroy', $diagram->slug))->assertForbidden();
        $this->actingAs(User::factory()->create())->delete(route('diagrams.destroy', $diagram->slug))->assertForbidden();

        $this->assertDatabaseHas('diagrams', ['id' => $diagram->id]);
    }
}

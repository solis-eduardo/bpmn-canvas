<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagram_owner_can_put_the_diagram_into_a_workspace_they_are_a_member_of(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/workspace", [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertOk();
        $this->assertSame($workspace->id, $diagram->fresh()->workspace_id);
    }

    public function test_diagram_owner_cannot_put_the_diagram_into_a_workspace_they_are_not_a_member_of(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $otherOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $otherOwner->id]);
        $workspace->addMember($otherOwner);

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/workspace", [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertStatus(422);
        $this->assertNull($diagram->fresh()->workspace_id);
    }

    public function test_a_user_who_is_not_the_diagram_owner_cannot_put_it_into_a_workspace(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        $workspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $workspace->addMember($stranger);

        $response = $this->actingAs($stranger)->putJson("/diagrams/{$diagram->slug}/workspace", [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertForbidden();
        $this->assertNull($diagram->fresh()->workspace_id);
    }

    public function test_diagram_owner_can_take_the_diagram_out_of_its_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->addMember($owner);
        $diagram = Diagram::factory()->for($owner, 'author')->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/workspace");

        $response->assertOk();
        $this->assertNull($diagram->fresh()->workspace_id);
    }

    public function test_workspace_owner_can_take_a_diagram_out_of_their_workspace_even_if_not_diagram_owner(): void
    {
        $diagramOwner = User::factory()->create();
        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $workspaceOwner->id]);
        $workspace->addMember($workspaceOwner);
        $workspace->addMember($diagramOwner);
        $diagram = Diagram::factory()->for($diagramOwner, 'author')->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($workspaceOwner)->deleteJson("/diagrams/{$diagram->slug}/workspace");

        $response->assertOk();
        $this->assertNull($diagram->fresh()->workspace_id);
    }

    public function test_a_plain_member_cannot_take_a_diagram_out_of_the_workspace(): void
    {
        $diagramOwner = User::factory()->create();
        $workspaceOwner = User::factory()->create();
        $plainMember = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $workspaceOwner->id]);
        $workspace->addMember($workspaceOwner);
        $workspace->addMember($diagramOwner);
        $workspace->addMember($plainMember);
        $diagram = Diagram::factory()->for($diagramOwner, 'author')->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($plainMember)->deleteJson("/diagrams/{$diagram->slug}/workspace");

        $response->assertForbidden();
        $this->assertSame($workspace->id, $diagram->fresh()->workspace_id);
    }

    public function test_a_workspace_member_can_open_the_editor_and_save_a_workspace_diagram(): void
    {
        $diagramOwner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $diagramOwner->id]);
        $workspace->addMember($diagramOwner);
        $workspace->addMember($member);
        $diagram = Diagram::factory()->for($diagramOwner, 'author')->create(['workspace_id' => $workspace->id]);

        $this->actingAs($member)->getJson("/diagrams/{$diagram->slug}/content")->assertOk();

        $response = $this->actingAs($member)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<xml>member edit</xml>',
        ]);

        $response->assertOk();
    }

    public function test_a_stranger_cannot_open_the_editor_of_a_workspace_diagram_they_are_not_a_member_of(): void
    {
        $diagramOwner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $diagramOwner->id]);
        $workspace->addMember($diagramOwner);
        $diagram = Diagram::factory()->for($diagramOwner, 'author')->create(['workspace_id' => $workspace->id]);

        // 404, not 403: a Workspace Diagram a stranger has no access to is
        // hidden — its existence doesn't leak (issue #28).
        $this->actingAs($stranger)->getJson("/diagrams/{$diagram->slug}/content")->assertNotFound();
    }

    public function test_a_user_removed_from_the_workspace_loses_edit_access_to_its_diagrams(): void
    {
        $diagramOwner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $diagramOwner->id]);
        $workspace->addMember($diagramOwner);
        $workspace->addMember($member);
        $diagram = Diagram::factory()->for($diagramOwner, 'author')->create(['workspace_id' => $workspace->id]);

        $this->actingAs($member)->getJson("/diagrams/{$diagram->slug}/content")->assertOk();

        $workspace->removeMember($member);

        // 404, not 403 (issue #28) — same reasoning as above.
        $this->actingAs($member)->getJson("/diagrams/{$diagram->slug}/content")->assertNotFound();
    }

    public function test_a_user_removed_from_the_workspace_keeps_edit_access_if_they_are_a_direct_collaborator(): void
    {
        $diagramOwner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $diagramOwner->id]);
        $workspace->addMember($diagramOwner);
        $workspace->addMember($member);
        $diagram = Diagram::factory()->for($diagramOwner, 'author')->create(['workspace_id' => $workspace->id]);
        $diagram->collaborators()->create([
            'user_id' => $member->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $workspace->removeMember($member);

        $this->actingAs($member)->getJson("/diagrams/{$diagram->slug}/content")->assertOk();
    }

    public function test_a_user_can_fork_a_diagram_into_one_of_their_workspaces(): void
    {
        $forker = User::factory()->create();
        $diagram = Diagram::factory()->for($forker, 'author')->create();
        $workspace = Workspace::factory()->create(['owner_id' => $forker->id]);
        $workspace->addMember($forker);

        $this->actingAs($forker)
            ->post('/diagrams/'.$diagram->slug.'/fork', ['workspace_id' => $workspace->id])
            ->assertRedirect();

        $forked = Diagram::where('forked_from_id', $diagram->id)->firstOrFail();
        $this->assertSame($workspace->id, $forked->workspace_id);
    }

    public function test_a_user_can_fork_a_diagram_without_assigning_it_to_a_workspace(): void
    {
        $forker = User::factory()->create();
        $diagram = Diagram::factory()->for($forker, 'author')->create();

        $this->actingAs($forker)
            ->post('/diagrams/'.$diagram->slug.'/fork', ['workspace_id' => null])
            ->assertRedirect();

        $forked = Diagram::where('forked_from_id', $diagram->id)->firstOrFail();
        $this->assertNull($forked->workspace_id);
    }

    public function test_a_user_cannot_fork_a_diagram_into_a_workspace_they_are_not_a_member_of(): void
    {
        $forker = User::factory()->create();
        $otherOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($forker, 'author')->create();
        $workspace = Workspace::factory()->create(['owner_id' => $otherOwner->id]);
        $workspace->addMember($otherOwner);

        $this->actingAs($forker)
            ->post('/diagrams/'.$diagram->slug.'/fork', ['workspace_id' => $workspace->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('diagrams', ['forked_from_id' => $diagram->id]);
    }

    public function test_diagram_list_can_filter_to_a_workspace_and_hide_any_hidden_diagrams(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Alpha Workspace']);
        $workspace->addMember($owner);
        $workspace->addMember($member);

        $otherWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id, 'name' => 'Secret Workspace']);
        $otherWorkspace->addMember($stranger);

        $visibleInWorkspace = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Visible workspace diagram',
            'workspace_id' => $workspace->id,
        ]);

        $outsideWorkspaceDiagram = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Outside workspace diagram',
            'workspace_id' => null,
        ]);

        $hiddenDiagram = Diagram::factory()->for($stranger, 'author')->create([
            'title' => 'Hidden workspace diagram',
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response = $this->actingAs($owner)->get("/diagrams?workspace={$workspace->id}");

        $response->assertOk()
            ->assertSee($visibleInWorkspace->title)
            ->assertSee($workspace->name)
            ->assertDontSee($outsideWorkspaceDiagram->title)
            ->assertDontSee($hiddenDiagram->title);
    }

    public function test_diagram_list_can_filter_to_diagrams_outside_any_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Workspace One']);
        $workspace->addMember($owner);

        $outsideDiagram = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Standalone diagram',
            'workspace_id' => null,
        ]);

        $workspaceDiagram = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Workspace diagram',
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($owner)
            ->get('/diagrams?workspace=none')
            ->assertOk()
            ->assertSee($outsideDiagram->title)
            ->assertDontSee($workspaceDiagram->title);
    }
}

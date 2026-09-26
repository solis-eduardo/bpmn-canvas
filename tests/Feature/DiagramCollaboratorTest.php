<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Managing Collaborators (invite/approve/remove) is Diagram Owner or
 * Administrator (CONTEXT.md, "Collaborator"; ADR-0006). These tests focus
 * on Diagrams where the Diagram Owner and the Author differ, to prove
 * management follows the Owner, not the Author (issue #13).
 */
class DiagramCollaboratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_only_the_diagram_owner_or_an_admin_can_list_collaborators_when_owner_and_author_differ(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($author)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertForbidden();

        $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertOk();

        $this->actingAs($admin)->getJson("/diagrams/{$diagram->slug}/collaborators")
            ->assertOk();
    }

    public function test_only_the_diagram_owner_or_an_admin_can_invite_a_collaborator_directly(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', DiagramCollaborator::STATUS_APPROVED);
    }

    public function test_the_author_alone_cannot_invite_a_collaborator_directly_once_ownership_diverges(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        // The Author is neither the Diagram Owner nor the target, so this
        // is treated as a self-request attempt on someone else's behalf
        // and rejected.
        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'user_id' => $target->id,
        ]);

        $response->assertForbidden();
    }

    public function test_only_the_diagram_owner_or_an_admin_approves_a_pending_request(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        DiagramCollaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $requester->id,
            'status' => DiagramCollaborator::STATUS_REQUESTED,
        ]);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}")
            ->assertForbidden();

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/collaborators/{$requester->id}");
        $response->assertOk();
        $response->assertJsonPath('status', DiagramCollaborator::STATUS_APPROVED);
    }

    public function test_only_the_diagram_owner_or_an_admin_removes_a_collaborator(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        DiagramCollaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $this->actingAs($author)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('diagram_collaborators', ['user_id' => $collaborator->id]);

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$collaborator->id}")
            ->assertOk();
        $this->assertDatabaseMissing('diagram_collaborators', ['user_id' => $collaborator->id]);
    }

    public function test_inviting_by_email_returns_identical_response_for_existing_and_non_existing_email(): void
    {
        $owner = User::factory()->create();
        $existingUser = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $responseWithExistingEmail = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => $existingUser->email,
        ]);

        $responseWithNonExistingEmail = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", [
            'email' => 'nonexistent@example.com',
        ]);

        // Both responses should have identical status and structure
        $this->assertSame($responseWithExistingEmail->status(), $responseWithNonExistingEmail->status());
        $this->assertSame($responseWithExistingEmail->status(), 201);

        // Both should have the same JSON structure (generic success response)
        $this->assertSame($responseWithExistingEmail->json(), $responseWithNonExistingEmail->json());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramCollaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestsEmailEnumerationPrevention;

/**
 * Transferring the Diagram Owner role (CONTEXT.md, "Diagram Owner";
 * ADR-0006; issue #14): the current Diagram Owner or an Administrator hands
 * the Diagram to another User with no acceptance step. The previous Diagram
 * Owner stays on as a Collaborator unless the "give up my access" checkbox
 * is ticked.
 */
class DiagramOwnershipTransferTest extends TestCase
{
    use RefreshDatabase;
    use TestsEmailEnumerationPrevention;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_the_diagram_owner_can_transfer_ownership_and_the_previous_owner_becomes_a_collaborator_by_default(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('owner_id', $newOwner->id);

        $diagram->refresh();
        $this->assertSame($newOwner->id, $diagram->owner_id);
        $this->assertSame($owner->id, $diagram->author_id, 'the Author never changes');

        $this->assertDatabaseHas('diagram_collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $owner->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        // The recipient has full access immediately, as Diagram Owner.
        $this->actingAs($newOwner)->get("/diagrams/{$diagram->slug}")->assertOk();
        // The previous Owner kept access too, now as a Collaborator.
        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}")->assertOk();
    }

    public function test_the_diagram_owner_can_transfer_ownership_and_give_up_their_own_access(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
            'give_up_access' => true,
        ]);

        $response->assertOk();

        $diagram->refresh();
        $this->assertSame($newOwner->id, $diagram->owner_id);

        $this->assertDatabaseMissing('diagram_collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}")->assertForbidden();
    }

    public function test_an_administrator_can_transfer_ownership_of_someone_elses_diagram_and_the_previous_owner_becomes_a_collaborator(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($admin)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertOk();

        $diagram->refresh();
        $this->assertSame($newOwner->id, $diagram->owner_id);

        $this->assertDatabaseHas('diagram_collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $owner->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);
    }

    public function test_an_administrator_can_transfer_ownership_and_the_previous_owner_gives_up_access(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($admin)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
            'give_up_access' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('diagram_collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_a_non_owner_non_admin_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($outsider)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertForbidden();

        $diagram->refresh();
        $this->assertSame($owner->id, $diagram->owner_id);
    }

    public function test_the_author_alone_cannot_transfer_ownership_once_ownership_has_diverged(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertForbidden();

        $diagram->refresh();
        $this->assertSame($owner->id, $diagram->owner_id);
    }

    public function test_a_collaborator_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        DiagramCollaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($collaborator)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertForbidden();
    }

    public function test_transferring_to_a_user_who_was_already_an_approved_collaborator_removes_the_now_redundant_collaborator_row(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        DiagramCollaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $newOwner->id,
            'status' => DiagramCollaborator::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", [
            'user_id' => $newOwner->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('diagram_collaborators', [
            'diagram_id' => $diagram->id,
            'user_id' => $newOwner->id,
        ]);
    }

    public function test_transferring_by_email_returns_identical_response_for_existing_and_non_existing_email(): void
    {
        $owner = User::factory()->create();
        $existingUser = User::factory()->create();
        $diagram1 = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $diagram2 = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $responseWithExistingEmail = $this->actingAs($owner)->putJson("/diagrams/{$diagram1->slug}/owner", [
            'email' => $existingUser->email,
        ]);

        $responseWithNonExistingEmail = $this->actingAs($owner)->putJson("/diagrams/{$diagram2->slug}/owner", [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertEmailEnumerationPrevented($responseWithExistingEmail, $responseWithNonExistingEmail, 200);
    }
}

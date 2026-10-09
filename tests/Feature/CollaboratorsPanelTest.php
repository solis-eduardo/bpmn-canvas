<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Editor's header reserves a "collaborators-entry" slot (issue #20)
 * that #11 turns into a real button opening the Collaborators & Shares
 * panel — but only for whoever can actually manage Collaborators
 * (DiagramPolicy::manageCollaborators: Diagram Owner or Administrator).
 * Everyone else keeps seeing the inert placeholder, same as before #11.
 */
class CollaboratorsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_the_diagram_owner_sees_the_collaborators_entry_button_and_panel(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->get("/diagrams/{$diagram->slug}");

        $response->assertOk();
        $response->assertSee('id="btn-collaborators-entry"', false);
        $response->assertSee('id="collaborators-panel"', false);
        $response->assertSee('assets/collaborators.js', false);
        $response->assertSee('id="shares-panel"', false);
    }

    public function test_an_approved_collaborator_who_is_not_the_owner_sees_the_shares_panel_but_not_the_collaborators_one(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($collaborator)->get("/diagrams/{$diagram->slug}");

        $response->assertOk();
        $response->assertDontSee('id="btn-collaborators-entry"', false);
        $response->assertDontSee('id="collaborators-panel"', false);
        $response->assertSee('id="btn-shares-entry"', false);
        $response->assertSee('id="shares-panel"', false);
        $response->assertSee('assets/collaborators.js', false);
    }
}

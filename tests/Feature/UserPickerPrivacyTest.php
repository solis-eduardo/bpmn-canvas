<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * User pickers list other Users by name only: their emails would let any
 * signed-in User map emails to accounts, undoing the email-enumeration guard
 * (issue #233).
 */
class UserPickerPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_new_diagram_form_lists_users_by_name_without_emails(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Picker Target', 'email' => 'target-picker@example.test']);

        $this->actingAs($user)->get('/diagrams/create')
            ->assertOk()
            ->assertSee('Picker Target')
            ->assertDontSee('target-picker@example.test');
    }

    public function test_workspace_form_lists_users_by_name_without_emails(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Member Target', 'email' => 'target-member@example.test']);

        $this->actingAs($user)->get('/workspaces/create')
            ->assertOk()
            ->assertSee('Member Target')
            ->assertDontSee('target-member@example.test');
    }

    public function test_diagram_owner_transfer_candidates_carry_no_emails(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        User::factory()->create(['name' => 'Transfer Target', 'email' => 'target-transfer@example.test']);

        $this->actingAs($owner)->get('/diagrams/'.$diagram->slug)
            ->assertOk()
            ->assertSee('Transfer Target')
            ->assertDontSee('target-transfer@example.test');
    }

    public function test_workspace_filters_list_users_by_name_without_emails(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Filter Target', 'email' => 'target-filter@example.test']);
        Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->get('/workspaces')
            ->assertOk()
            ->assertSee('Filter Target')
            ->assertDontSee('target-filter@example.test');
    }
}

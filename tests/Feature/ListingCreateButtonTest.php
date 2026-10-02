<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCreateButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagrams_listing_links_to_the_create_form_next_to_the_title(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('diagrams.index'))
            ->assertOk()
            ->assertSee('href="'.route('diagrams.create').'"', false)
            ->assertSee(__('messages.create_diagram'))
            ->assertDontSee('name="title"', false);
    }

    public function test_workspaces_listing_links_to_the_create_form_next_to_the_title(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('href="'.route('workspaces.create').'"', false)
            ->assertSee(__('messages.create_workspace'))
            ->assertDontSee('action="'.route('workspaces.store').'"', false);
    }

    public function test_diagram_create_form_is_blank_and_posts_to_store(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->for($user, 'owner')->create(['title' => 'Existing diagram']);

        $this->actingAs($user)
            ->get(route('diagrams.create'))
            ->assertOk()
            ->assertSee('action="'.route('diagrams.store').'"', false)
            ->assertSee('name="title"', false)
            ->assertDontSee('value="Existing diagram"', false);
    }

    public function test_workspace_create_form_is_blank_and_posts_to_store(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Existing workspace']);

        $this->actingAs($user)
            ->get(route('workspaces.create'))
            ->assertOk()
            ->assertSee('action="'.route('workspaces.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertDontSee('value="Existing workspace"', false);
    }

    public function test_create_forms_require_authentication(): void
    {
        $this->get(route('diagrams.create'))->assertRedirect('/login');
        $this->get(route('workspaces.create'))->assertRedirect('/login');
    }
}

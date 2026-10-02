<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ButtonComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_button_element_by_default(): void
    {
        $html = Blade::render('<x-button>Save</x-button>');

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('bg-blue-600', $html);
    }

    public function test_it_renders_an_anchor_when_given_an_href(): void
    {
        $html = Blade::render('<x-button href="/somewhere" variant="secondary">Back</x-button>');

        $this->assertStringContainsString('<a href="/somewhere"', $html);
        $this->assertStringNotContainsString('<button', $html);
    }

    public function test_destructive_variant_is_red(): void
    {
        $html = Blade::render('<x-button type="submit" variant="destructive">Delete</x-button>');

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('bg-red-600', $html);
    }

    public function test_workspace_delete_action_uses_the_destructive_button(): void
    {
        $owner = User::factory()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->get('/workspaces');

        $response->assertOk();
        $response->assertSee('bg-red-600', false);
        $response->assertDontSee('text-red-600 hover:text-red-700', false);
    }

    public function test_admin_user_back_links_render_as_buttons(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('border border-gray-300 bg-white', false)
            ->assertDontSee('text-sm font-medium text-blue-600 hover:text-blue-700', false);
    }
}

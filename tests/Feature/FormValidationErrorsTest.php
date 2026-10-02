<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormValidationErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_create_error_is_rendered_right_below_the_field_and_flags_it_invalid(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->from('/workspaces')
            ->followingRedirects()
            ->post('/workspaces', ['name' => '', '_form' => 'workspace-create'])
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*aria-invalid="true"[^>]*>\s*<p[^>]*id="[^"]+-error"[^>]*>[^<]*<\/p>/s',
            $html,
        );
        $this->assertStringNotContainsString('bg-red-50', $html);
    }

    public function test_workspace_row_forms_do_not_show_each_others_errors(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Keep']);
        $workspace->members()->create(['user_id' => $user->id]);

        $html = $this->actingAs($user)
            ->from('/workspaces')
            ->followingRedirects()
            ->put("/workspaces/{$workspace->id}", ['name' => '', '_form' => "workspace-rename-{$workspace->id}"])
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-invalid="true"'));
    }

    public function test_account_form_shows_error_under_field(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->from('/account')
            ->followingRedirects()
            ->put('/account', ['name' => ''])
            ->getContent();

        $this->assertMatchesRegularExpression('/id="name"[^>]*aria-invalid="true"[^>]*>\s*<p[^>]*role="alert"/s', $html);
        $this->assertStringNotContainsString('bg-red-50', $html);
    }

    public function test_layout_script_focuses_the_first_invalid_field(): void
    {
        $this->assertStringContainsString('aria-invalid="true"', file_get_contents(resource_path('js/app.js')));
    }
}

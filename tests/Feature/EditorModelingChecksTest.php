<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorModelingChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_has_a_modeling_checks_toggle_with_an_issue_count_and_the_bundle_to_load(): void
    {
        Storage::fake('diagrams');
        $author = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $response = $this->actingAs($author)->get('/diagrams/'.$diagram->slug);

        $response->assertOk();
        $response->assertViewIs('diagrams.show');
        $response->assertSee('id="btn-lint"', false);
        $response->assertSee('id="lint-count"', false);
        $response->assertSee('data-lint-url="', false);
        $response->assertSee('editor-lint', false);
    }

    public function test_every_locale_translates_the_modeling_checks_strings(): void
    {
        foreach (['en', 'pt_BR', 'es'] as $locale) {
            foreach (['lint_count', 'lint_show', 'lint_hide'] as $key) {
                $this->assertNotSame("messages.$key", trans("messages.$key", ['count' => 2], $locale), "$locale/$key");
            }
        }

        $this->assertSame('Modeling checks: 2 found', trans('messages.lint_count', ['count' => 2], 'en'));
    }
}

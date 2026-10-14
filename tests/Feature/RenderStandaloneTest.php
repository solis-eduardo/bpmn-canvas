<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RenderStandaloneTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/standalone-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function page(string $locale, string $name): string
    {
        return File::get("{$this->path}/{$locale}/{$name}.html");
    }

    public function test_renders_every_page_for_every_language(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        foreach (['en', 'es', 'pt_BR'] as $locale) {
            foreach (['home', 'library', 'library-row', 'create', 'edit', 'editor', 'workspaces', 'users', 'account'] as $name) {
                $this->assertFileExists("{$this->path}/{$locale}/{$name}.html");
            }
        }
    }

    public function test_the_pages_are_the_server_views_in_the_pages_language(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $this->assertStringContainsString(__('messages.create_diagram', [], 'pt_BR'), $this->page('pt_BR', 'library'));
        $this->assertStringContainsString(__('messages.create_diagram', [], 'en'), $this->page('en', 'library'));
        $this->assertStringContainsString('id="diagram-search-results"', $this->page('en', 'library'));
        $this->assertStringContainsString('id="btn-save"', $this->page('en', 'editor'));
        $this->assertStringContainsString('id="version-panel"', $this->page('en', 'editor'));
    }

    public function test_the_listing_and_the_editor_carry_the_tokens_main_js_replaces(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $this->assertStringNotContainsString('__slug__', $this->page('en', 'library'));
        $this->assertMatchesRegularExpression('~<tbody[^>]*>\s*<tr>.*__title__.*__slug__.*</tr>\s*</tbody>~s', $this->page('en', 'library-row'));
        $this->assertStringContainsString('__title__', $this->page('en', 'editor'));
        $this->assertStringContainsString('/diagrams/__slug__', $this->page('en', 'editor'));
    }

    public function test_links_point_at_the_standalone_origin(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $this->assertStringContainsString('href="http://standalone.invalid/diagrams"', $this->page('en', 'library'));
    }

    public function test_it_leaves_the_real_installation_untouched(): void
    {
        $before = ['connection' => config('database.default'), 'store' => config('bpmn.version_store'), 'root' => url('/')];
        $user = User::factory()->create();

        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame($before, ['connection' => config('database.default'), 'store' => config('bpmn.version_store'), 'root' => url('/')]);
        $this->assertSame(0, Diagram::count());
        $this->assertSame([$user->id], User::pluck('id')->all());
    }
}

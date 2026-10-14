<?php

namespace Tests\Feature;

use App\Support\Ui;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RenderStandaloneTest extends TestCase
{
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

    public function test_renders_one_page_per_language_from_the_shared_views(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        foreach (['en', 'es', 'pt_BR'] as $locale) {
            foreach (['library', 'editor', 'placeholder'] as $page) {
                $this->assertFileExists("{$this->path}/{$locale}/{$page}.html");
            }
        }

        $this->assertStringContainsString(__('messages.create_diagram', [], 'pt_BR'), File::get("{$this->path}/pt_BR/library.html"));

        $editor = File::get("{$this->path}/pt_BR/editor.html");

        $this->assertStringContainsString('id="btn-save"', $editor);
        $this->assertStringContainsString(__('messages.versions', [], 'pt_BR'), $editor);
    }

    public function test_the_listing_is_the_server_listing_with_a_row_template(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $html = File::get("{$this->path}/en/library.html");

        $this->assertStringContainsString('id="diagram-search-results"', $html);
        $this->assertStringContainsString('data-sort-column="title"', $html);
        $this->assertStringContainsString('<template id="diagram-row">', $html);
        $this->assertStringContainsString('href="#/diagram/__slug__"', $html);
        $this->assertStringContainsString('<time data-timestamp', $html);
    }

    public function test_the_standalone_pages_need_no_server(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $html = File::get("{$this->path}/en/library.html");

        $this->assertStringNotContainsString('/build/assets', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('/logout', $html);
        $this->assertStringContainsString('href="#/workspaces"', $html);
    }

    public function test_the_server_pages_are_not_affected_by_a_standalone_render(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $this->assertFalse(Ui::standalone());
        $this->assertSame(url('/diagrams'), Ui::route('diagrams.index'));
        $this->assertSame(app()->getLocale(), config('app.locale'));
    }
}

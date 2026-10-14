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

        $this->path = sys_get_temp_dir().'/standalone-'.uniqid().'.html';
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_renders_the_page_from_the_shared_layout_with_translation_keys(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $html = File::get($this->path);

        $this->assertStringContainsString('id="library"', $html);
        $this->assertStringContainsString('id="btn-save"', $html);
        $this->assertStringContainsString('data-i18n="messages.versions"', $html);
        $this->assertStringContainsString('data-i18n-title="messages.zoom_in"', $html);
    }

    public function test_the_standalone_page_needs_no_server(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $html = File::get($this->path);

        $this->assertStringNotContainsString('/build/assets', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('/logout', $html);
    }

    public function test_the_server_pages_are_not_affected_by_a_standalone_render(): void
    {
        $this->artisan('standalone:render', ['--path' => $this->path])->assertSuccessful();

        $this->assertFalse(Ui::standalone());
        $this->assertSame(__('messages.versions'), (string) Ui::text('messages.versions'));
    }
}

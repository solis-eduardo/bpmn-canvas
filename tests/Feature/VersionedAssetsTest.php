<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every public/assets/ URL the views render carries a content hash, and the
 * import map gives the modules' relative imports the same versions (issue #257).
 */
class VersionedAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function versioned(string $file): string
    {
        return $file.'?v='.substr(md5_file(public_path("assets/{$file}")), 0, 12);
    }

    private function editorPage(): TestResponse
    {
        Storage::fake('diagrams');
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());

        return $this->actingAs($owner)->get("/diagrams/{$diagram->slug}");
    }

    public function test_the_editor_renders_versioned_script_urls(): void
    {
        $response = $this->editorPage();

        foreach (['editor.js', 'diagram-settings.js', 'collaborators.js', 'notifications.js'] as $file) {
            $response->assertSee('/assets/'.$this->versioned($file).'"', false);
        }
        $response->assertDontSee('src="'.url('assets/editor.js').'"', false);
    }

    public function test_the_editor_import_map_versions_the_modules_imported_by_other_modules(): void
    {
        $response = $this->editorPage();

        foreach (['common.js', 'editor-core.js', 'http-store.js', 'diff.js', 'versions.js'] as $file) {
            $response->assertSee('"'.url("assets/{$file}").'":"'.url('assets/'.$this->versioned($file)).'"', false);
        }
        $response->assertSee('"bpmn-js-differ":', false);
    }

    public function test_the_editor_lang_file_url_is_versioned(): void
    {
        $locale = app()->getLocale();
        $langFile = public_path("assets/lang/{$locale}.json");
        // Generated and gitignored: absent on a fresh checkout such as CI.
        if (! is_file($langFile)) {
            $this->artisan('lang:export')->assertSuccessful();
        }
        $this->assertFileExists($langFile);

        $this->editorPage()->assertSee("assets/lang/{$locale}.json?v=".substr(md5_file($langFile), 0, 12), false);
    }

    public function test_the_share_page_renders_versioned_script_urls_and_the_import_map(): void
    {
        Storage::fake('diagrams');
        $author = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        $share = Share::factory()->for($diagram)->for($author, 'author')->create();

        $response = $this->get("/s/{$share->token}");

        $response->assertOk();
        $response->assertSee('/assets/'.$this->versioned('share-viewer.js').'"', false);
        $response->assertSee('"'.url('assets/common.js').'":"'.url('assets/'.$this->versioned('common.js')).'"', false);
    }

    public function test_the_web_server_sends_an_explicit_cache_control_for_assets(): void
    {
        $conf = file_get_contents(base_path('docker/web/default.conf'));

        $this->assertMatchesRegularExpression(
            '#location \^~ /assets/ \{\s+add_header Cache-Control "public, max-age=31536000, immutable";#',
            $conf,
        );
    }
}

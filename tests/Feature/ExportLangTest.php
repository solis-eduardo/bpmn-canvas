<?php

namespace Tests\Feature;

use App\Console\Commands\ExportLang;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportLangTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/lang-export-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function exported(string $locale): array
    {
        return json_decode(File::get("{$this->path}/{$locale}.json"), true);
    }

    public function test_writes_one_json_file_per_locale_from_lang(): void
    {
        $this->artisan('lang:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame(trans('messages.saved', [], 'en'), $this->exported('en')['messages.saved']);
        $this->assertSame(trans('messages.saved', [], 'pt_BR'), $this->exported('pt_BR')['messages.saved']);
        $this->assertFileExists("{$this->path}/es.json");
    }

    public function test_keeps_placeholders_for_the_core_to_replace(): void
    {
        $this->artisan('lang:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertStringContainsString(':name', $this->exported('en')['messages.version_by_author']);
    }

    public function test_a_key_ending_in_a_dot_exports_the_whole_group(): void
    {
        $this->artisan('lang:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame(
            trans('messages.bpmn_element_types.UserTask', [], 'pt_BR'),
            $this->exported('pt_BR')['messages.bpmn_element_types.UserTask'],
        );
    }

    public function test_every_key_the_core_uses_has_a_translation_in_every_locale(): void
    {
        $this->artisan('lang:export', ['--path' => $this->path])->assertSuccessful()->doesntExpectOutputToContain('No translation');

        foreach (['en', 'es', 'pt_BR'] as $locale) {
            foreach (ExportLang::sources() as $source) {
                preg_match_all(ExportLang::KEY_PATTERN, File::get($source), $matches);

                foreach (array_filter(array_merge($matches[1], $matches[2])) as $key) {
                    $this->assertTrue(
                        collect($this->exported($locale))->keys()->contains(fn ($exported) => str_starts_with($exported, $key)),
                        "'{$key}' missing from {$locale}.json",
                    );
                }
            }
        }
    }
}

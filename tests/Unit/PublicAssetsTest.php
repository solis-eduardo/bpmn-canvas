<?php

namespace Tests\Unit;

use App\Support\PublicAssets;
use PHPUnit\Framework\TestCase;

class PublicAssetsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/public-assets-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/lang', 0777, true);
        file_put_contents($this->dir.'/a.js', 'export const a = 1;');
        file_put_contents($this->dir.'/b.js', 'export const b = 2;');
        file_put_contents($this->dir.'/app.css', 'body{}');
        file_put_contents($this->dir.'/lang/en.json', '{}');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/lang/*'));
        array_map('unlink', glob($this->dir.'/*.*'));
        rmdir($this->dir.'/lang');
        rmdir($this->dir);
    }

    private function assets(): PublicAssets
    {
        return new PublicAssets($this->dir, 'https://app.test/assets');
    }

    public function test_the_url_carries_a_hash_of_the_file_content(): void
    {
        $url = $this->assets()->url('a.js');

        $this->assertMatchesRegularExpression('#^https://app\.test/assets/a\.js\?v=[0-9a-f]{12}$#', $url);
    }

    public function test_the_version_changes_when_the_content_changes_and_only_then(): void
    {
        $before = $this->assets()->url('a.js');
        $this->assertSame($before, $this->assets()->url('a.js'));

        file_put_contents($this->dir.'/a.js', 'export const a = 99;');

        $this->assertNotSame($before, $this->assets()->url('a.js'));
        $this->assertSame($this->assets()->url('b.js'), $this->assets()->url('b.js'));
    }

    public function test_nested_files_are_versioned(): void
    {
        $this->assertStringContainsString('/assets/lang/en.json?v=', $this->assets()->url('lang/en.json'));
    }

    public function test_a_missing_file_gets_an_unversioned_url(): void
    {
        $this->assertSame('https://app.test/assets/nope.js', $this->assets()->url('nope.js'));
    }

    public function test_the_import_map_rewrites_every_module_to_its_versioned_url(): void
    {
        $assets = $this->assets();
        $map = $assets->importMap();

        $this->assertSame($assets->url('a.js'), $map['https://app.test/assets/a.js']);
        $this->assertSame($assets->url('b.js'), $map['https://app.test/assets/b.js']);
        $this->assertArrayNotHasKey('https://app.test/assets/app.css', $map);
        $this->assertArrayNotHasKey('https://app.test/assets/lang/en.json', $map);
    }
}

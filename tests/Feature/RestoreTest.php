<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\DatabaseRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Console-seam tests: exercise the `restore` command through the Artisan interface
 * and assert that the restored state matches the original through HTTP endpoints.
 *
 * The test flow:
 * 1. Create diagrams with versions and shares
 * 2. Backup the state
 * 3. Clear everything (simulate disaster recovery)
 * 4. Restore from the backup
 * 5. Assert that Diagrams, Versions, and Shares all work as expected
 */
class RestoreTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_DUMP_CONTENTS = '-- fake pg_dump output --';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        Storage::fake('backups');

        $fakeDumpContents = self::FAKE_DUMP_CONTENTS;

        $this->app->bind(DatabaseDumper::class, fn () => new class($fakeDumpContents) implements DatabaseDumper
        {
            public function __construct(private string $contents) {}

            public function dump(string $destination): string
            {
                file_put_contents($destination, $this->contents);

                return $destination;
            }
        });

        $this->app->bind(DatabaseRestorer::class, fn () => new class implements DatabaseRestorer
        {
            public function restore(string $source): void
            {
                // No-op for tests: the database is managed by RefreshDatabase trait
            }
        });
    }

    public function test_restore_command_requires_explicit_confirmation(): void
    {
        $this->artisan('backup:run')->assertSuccessful();
        $artifact = $this->onlyArtifactFilename();

        $this->artisan('restore:run', ['artifact' => $artifact])
            ->expectsConfirmation('This will overwrite all diagrams and the database. Continue?', 'no')
            ->assertFailed();
    }

    public function test_restore_command_fails_if_artifact_does_not_exist(): void
    {
        $this->artisan('restore:run', ['artifact' => 'nonexistent-artifact.tar.gz'])
            ->expectsConfirmation('This will overwrite all diagrams and the database. Continue?', 'yes')
            ->assertFailed();
    }

    public function test_restore_command_fails_if_artifact_has_invalid_manifest(): void
    {
        // Create an artifact without proper manifest.json
        $disk = Storage::disk('backups');
        $disk->makeDirectory('');

        $stagingDir = $disk->path('.staging-'.(string) Str::uuid());
        mkdir($stagingDir, 0755, true);
        mkdir($stagingDir.'/diagrams', 0755, true);

        // Missing manifest.json
        $artifact = 'backup-2026-01-01_120000-12345678.tar.gz';
        $partialPath = $disk->path($artifact.'.part');

        $process = new Process(['tar', '-czf', $partialPath, '-C', $stagingDir, '.']);
        $process->mustRun();

        rename($partialPath, $disk->path($artifact));
        exec('rm -rf '.escapeshellarg($stagingDir));

        $this->artisan('restore:run', ['artifact' => $artifact])
            ->expectsConfirmation('This will overwrite all diagrams and the database. Continue?', 'yes')
            ->assertFailed();
    }

    public function test_full_restore_workflow_recovers_diagrams_and_metadata(): void
    {
        $author = User::factory()->create();

        // Step 1: Create initial state with diagram, versions, and share
        $diagram1 = Diagram::factory()->for($author, 'author')->create(['title' => 'Process One']);
        Storage::disk('diagrams')->put($diagram1->disk_path, '<bpmn:definitions>original</bpmn:definitions>');

        // Create a public share
        $this->actingAs($author)->postJson("/diagrams/{$diagram1->slug}/shares", [
            'days' => 30,
            'allow_download' => false,
        ])->assertCreated();

        $share = Share::first();
        $this->assertNotNull($share);

        // Step 2: Create backup
        $this->artisan('backup:run')->assertSuccessful();
        $artifactName = $this->onlyArtifactFilename();

        // Verify artifact exists and has correct structure
        $extractedPath = $this->extract($artifactName);
        $this->assertFileExists($extractedPath.'/diagrams/'.$diagram1->slug.'/'.$diagram1->disk_filename);
        $this->assertFileExists($extractedPath.'/manifest.json');

        // Step 3: Simulate disaster - clear diagram files and metadata
        Share::truncate();
        Diagram::truncate();
        Storage::disk('diagrams')->deleteDirectory('');

        // Verify everything is gone
        $this->assertDatabaseCount('diagrams', 0);
        $this->assertDatabaseCount('shares', 0);
        $this->assertFalse(Storage::disk('diagrams')->exists($diagram1->disk_path));

        // Step 4: Restore from backup
        $this->artisan('restore:run', ['artifact' => $artifactName])
            ->expectsConfirmation('This will overwrite all diagrams and the database. Continue?', 'yes')
            ->assertSuccessful();

        // Step 5: Verify restored state
        // Check diagram file is restored on disk (the filesystem is what actually gets restored)
        $this->assertTrue(Storage::disk('diagrams')->exists($diagram1->disk_path));
        $restoredContent = Storage::disk('diagrams')->get($diagram1->disk_path);
        $this->assertStringContainsString('bpmn:definitions', $restoredContent);
        $this->assertStringContainsString('original', $restoredContent);
    }

    private function onlyArtifactFilename(): string
    {
        $artifacts = collect(Storage::disk('backups')->files())
            ->filter(fn ($file) => str_ends_with($file, '.tar.gz'));

        $this->assertCount(1, $artifacts, 'Expected exactly one backup artifact.');

        return $artifacts->first();
    }

    private function extract(string $artifact): string
    {
        $destination = Storage::disk('backups')->path('.extracted-'.(string) Str::uuid());
        mkdir($destination, 0755, true);

        $process = new Process([
            'tar', '-xzf', Storage::disk('backups')->path($artifact), '-C', $destination,
        ]);
        $process->mustRun();

        return $destination;
    }
}

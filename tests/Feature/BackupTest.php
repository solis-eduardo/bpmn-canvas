<?php

namespace Tests\Feature;

use App\Services\Backup\DatabaseDumper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Console-seam tests: exercise `backup:run` through the Artisan command
 * (the public interface an operator/scheduler actually uses) and assert on
 * the real artifact it leaves on the `backups` disk. The only boundary
 * swapped out is the database dump itself (DatabaseDumper) — there's no
 * Postgres server in the test environment (phpunit.xml forces sqlite) — so
 * the fake below is the seam, not the command or BackupRepository.
 */
class BackupTest extends TestCase
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
    }

    public function test_running_the_command_produces_one_artifact_with_the_diagrams_and_a_database_dump(): void
    {
        Storage::disk('diagrams')->put('purchase-process/purchase-process.bpmn', '<bpmn:definitions/>');
        Storage::disk('diagrams')->put('purchase-process/.git/HEAD', 'ref: refs/heads/master');

        $this->artisan('backup:run')->assertSuccessful();

        $artifacts = collect(Storage::disk('backups')->files())
            ->filter(fn ($file) => str_ends_with($file, '.tar.gz'));

        $this->assertCount(1, $artifacts, 'Expected exactly one backup artifact.');

        $contents = $this->extract($artifacts->first());

        $this->assertSame(
            '<bpmn:definitions/>',
            file_get_contents($contents.'/diagrams/purchase-process/purchase-process.bpmn'),
        );
        $this->assertFileExists(
            $contents.'/diagrams/purchase-process/.git/HEAD',
            'The Version repository (.git) must survive the backup, not just the .bpmn file.',
        );
        $this->assertSame(
            self::FAKE_DUMP_CONTENTS,
            file_get_contents($contents.'/database.dump'),
        );

        $manifest = json_decode(file_get_contents($contents.'/manifest.json'), true);
        $this->assertSame(1, $manifest['format']);
    }

    public function test_retention_prunes_artifacts_older_than_the_configured_window_but_keeps_recent_ones(): void
    {
        config(['backup.retention_days' => 14]);

        Carbon::setTestNow('2026-01-01 00:00:00');
        $this->artisan('backup:run')->assertSuccessful();
        $oldArtifact = $this->onlyArtifactFilename();

        // Not yet expired: still inside the 14-day retention window.
        Carbon::setTestNow('2026-01-10 00:00:00');
        $this->artisan('backup:run')->assertSuccessful();
        Storage::disk('backups')->assertExists($oldArtifact);

        // Time travelled (not slept) past retention for the first artifact.
        Carbon::setTestNow('2026-01-20 00:00:00');
        $this->artisan('backup:run')->assertSuccessful();

        Storage::disk('backups')->assertMissing($oldArtifact);

        $remaining = collect(Storage::disk('backups')->files())
            ->filter(fn ($file) => str_ends_with($file, '.tar.gz'));

        // The 2026-01-10 and 2026-01-20 artifacts are both still within 14
        // days of "now" (2026-01-20), only the 2026-01-01 one is expired.
        $this->assertCount(2, $remaining);
    }

    private function onlyArtifactFilename(): string
    {
        $artifacts = collect(Storage::disk('backups')->files())
            ->filter(fn ($file) => str_ends_with($file, '.tar.gz'));

        $this->assertCount(1, $artifacts);

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

<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Console-seam tests for the optional off-site copy of `backup:run`
 * (ADR-0026). The off-site disk is a fake filesystem; the database dump is
 * the same fake seam BackupTest uses.
 */
class BackupOffsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        Storage::fake('backups');
        Storage::fake('backups_offsite');

        $this->app->bind(DatabaseDumper::class, fn () => new class implements DatabaseDumper
        {
            public function dump(string $destination): string
            {
                file_put_contents($destination, 'dump');

                return $destination;
            }
        });
    }

    public function test_nothing_is_uploaded_while_the_offsite_copy_is_disabled(): void
    {
        config(['backup.offsite.enabled' => false]);

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertSame([], Storage::disk('backups_offsite')->allFiles());
        $this->assertSame(0, BackupRun::where('kind', BackupRun::KIND_OFFSITE)->count());
    }

    public function test_each_artifact_is_uploaded_to_the_offsite_disk_when_enabled(): void
    {
        config(['backup.offsite.enabled' => true]);

        $this->artisan('backup:run')->assertSuccessful();

        $local = collect(Storage::disk('backups')->files())->first(fn ($f) => str_ends_with($f, '.tar.gz'));
        Storage::disk('backups_offsite')->assertExists($local);
        $this->assertSame(
            Storage::disk('backups')->get($local),
            Storage::disk('backups_offsite')->get($local),
        );
        $this->assertNotNull(BackupRun::lastSuccess(BackupRun::KIND_OFFSITE));
    }

    public function test_offsite_retention_is_independent_of_the_local_one(): void
    {
        config([
            'backup.offsite.enabled' => true,
            'backup.retention_days' => 2,
            'backup.offsite.retention_days' => 30,
        ]);

        Carbon::setTestNow('2026-01-01 00:00:00');
        $this->artisan('backup:run')->assertSuccessful();
        $first = collect(Storage::disk('backups')->files())->first(fn ($f) => str_ends_with($f, '.tar.gz'));

        Carbon::setTestNow('2026-01-10 00:00:00');
        $this->artisan('backup:run')->assertSuccessful();

        Storage::disk('backups')->assertMissing($first);
        Storage::disk('backups_offsite')->assertExists($first);

        Carbon::setTestNow('2026-03-01 00:00:00');
        $this->artisan('backup:run')->assertSuccessful();

        Storage::disk('backups_offsite')->assertMissing($first);
    }

    public function test_a_failed_upload_fails_the_command_records_it_and_keeps_the_local_artifact(): void
    {
        config([
            'backup.offsite.enabled' => true,
            'backup.offsite.disk' => 'unreachable',
            'filesystems.disks.unreachable' => ['driver' => 'local', 'root' => '/proc/nonexistent', 'throw' => true],
        ]);

        $this->artisan('backup:run')->assertFailed();

        $this->assertCount(1, collect(Storage::disk('backups')->files())->filter(fn ($f) => str_ends_with($f, '.tar.gz')));
        $this->assertNull(BackupRun::lastSuccess(BackupRun::KIND_OFFSITE));
        $this->assertSame(1, BackupRun::where('kind', BackupRun::KIND_OFFSITE)->where('succeeded', false)->count());
        $this->assertNotNull(BackupRun::lastSuccess(BackupRun::KIND_BACKUP));
    }
}

<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\BackupVerificationFailed;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\RestoreVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Console-seam tests for `backup:verify` (ADR-0026): restore the latest
 * artifact into a throwaway place and check it. The Postgres side is the
 * RestoreVerifier seam (no server in the test environment); `git fsck` runs
 * for real against real repositories.
 */
class BackupVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int>|RuntimeException|null null = the live database, as if restored faithfully */
    private array|RuntimeException|null $restoredCounts = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        Storage::fake('backups');

        $this->app->bind(DatabaseDumper::class, fn () => new class implements DatabaseDumper
        {
            public function dump(string $destination): string
            {
                file_put_contents($destination, 'dump');

                return $destination;
            }
        });

        $test = $this;
        $this->app->bind(RestoreVerifier::class, fn () => new class($test) implements RestoreVerifier
        {
            public function __construct(private BackupVerificationTest $test) {}

            public function rowCounts(string $dump): array
            {
                $counts = $this->test->restoredCounts();

                if ($counts instanceof RuntimeException) {
                    throw $counts;
                }

                return $counts;
            }
        });
    }

    /** @return array<string, int>|RuntimeException */
    public function restoredCounts(): array|RuntimeException
    {
        return $this->restoredCounts ?? $this->liveCounts();
    }

    public function test_a_healthy_artifact_passes_and_the_time_is_recorded(): void
    {
        User::factory()->create();
        $this->makeRepository('purchase-process');
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:verify')->assertSuccessful();

        $this->assertNotNull(BackupRun::lastSuccess(BackupRun::KIND_VERIFICATION));
        $this->assertSame([], glob(Storage::disk('backups')->path('.verify-*')));
    }

    public function test_a_table_that_had_rows_but_is_empty_after_restore_fails_and_alerts_administrators(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $plain = User::factory()->create();
        $this->restoredCounts = ['users' => 0] + $this->liveCounts();
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:verify')->assertFailed();

        $this->assertNull(BackupRun::lastSuccess(BackupRun::KIND_VERIFICATION));
        $this->assertSame(1, BackupRun::where('kind', BackupRun::KIND_VERIFICATION)->where('succeeded', false)->count());
        Notification::assertSentTo($admin, BackupVerificationFailed::class);
        Notification::assertNotSentTo($plain, BackupVerificationFailed::class);
    }

    public function test_a_missing_table_fails(): void
    {
        User::factory()->create();
        $this->restoredCounts = array_diff_key($this->liveCounts(), ['users' => 1]);
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:verify')->assertFailed();
    }

    public function test_a_restore_error_fails(): void
    {
        $this->restoredCounts = new RuntimeException('pg_restore exploded');
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:verify')->assertFailed();

        $this->assertStringContainsString('pg_restore exploded', BackupRun::latestFailure(BackupRun::KIND_VERIFICATION)->message);
    }

    public function test_a_corrupt_diagram_repository_fails(): void
    {
        $this->makeRepository('broken-one', corrupt: true);
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:verify')->assertFailed();

        $this->assertStringContainsString('broken-one', BackupRun::latestFailure(BackupRun::KIND_VERIFICATION)->message);
    }

    public function test_it_verifies_the_latest_artifact_only(): void
    {
        $this->makeRepository('broken-one', corrupt: true);
        $this->artisan('backup:run')->assertSuccessful();

        // A newer artifact taken after the repository was repaired.
        $this->travel(1)->minute();
        Storage::disk('diagrams')->deleteDirectory('broken-one');
        $this->makeRepository('good-one');
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:verify')->assertSuccessful();
    }

    public function test_no_artifact_at_all_is_a_failure(): void
    {
        $this->artisan('backup:verify')->assertFailed();

        $this->assertSame(1, BackupRun::where('kind', BackupRun::KIND_VERIFICATION)->where('succeeded', false)->count());
    }

    /** @return array<string, int> */
    private function liveCounts(): array
    {
        $counts = [];
        foreach (Schema::getTables() as $table) {
            $counts[$table['name']] = DB::table($table['name'])->count();
        }

        return $counts;
    }

    private function makeRepository(string $slug, bool $corrupt = false): void
    {
        $dir = Storage::disk('diagrams')->path($slug);
        mkdir($dir, 0755, true);
        file_put_contents("{$dir}/{$slug}.bpmn", '<bpmn:definitions/>');

        foreach ([
            ['git', 'init', '-q'],
            ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'add', '.'],
            ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 'v1'],
        ] as $command) {
            (new Process($command, $dir))->mustRun();
        }

        if ($corrupt) {
            foreach (glob("{$dir}/.git/objects/??/*") as $object) {
                chmod($object, 0644);
                file_put_contents($object, 'garbage');
            }
        }
    }
}

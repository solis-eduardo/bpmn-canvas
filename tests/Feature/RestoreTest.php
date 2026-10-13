<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Repositories\VersionRepository;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\DatabaseRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Console-seam test for the restore side of ticket 05: `backup:run` produces
 * a real artifact, application state is then mutated, `backup:restore`
 * brings it back, and the original state is asserted through the same HTTP
 * endpoints a User/operator would use — not by inspecting the database or
 * disk directly.
 *
 * As in BackupTest, the only boundary swapped out is the database
 * dump/restore itself (no Postgres server in the test environment, see
 * phpunit.xml forcing sqlite): the fake below round-trips a fixed set of
 * application tables through the artifact, standing in for `pg_dump`
 * /`pg_restore`. The command, RestoreRepository, the Diagrams-disk copy, and
 * every HTTP endpoint asserted on are all real.
 */
class RestoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    public const TABLES_IN_DEPENDENCY_ORDER = [
        'users',
        'diagrams',
        'collaborators',
        'diagram_version_names',
        'shares',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        Storage::fake('backups');

        $fake = new class implements DatabaseDumper, DatabaseRestorer
        {
            public function dump(string $destination): string
            {
                $snapshot = [];

                foreach (RestoreTest::TABLES_IN_DEPENDENCY_ORDER as $table) {
                    $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                }

                file_put_contents($destination, json_encode($snapshot));

                return $destination;
            }

            public function restore(string $source): void
            {
                $snapshot = json_decode(file_get_contents($source), true);

                foreach (array_reverse(RestoreTest::TABLES_IN_DEPENDENCY_ORDER) as $table) {
                    DB::table($table)->delete();
                }

                foreach (RestoreTest::TABLES_IN_DEPENDENCY_ORDER as $table) {
                    foreach ($snapshot[$table] ?? [] as $row) {
                        DB::table($table)->insert($row);
                    }
                }
            }
        };

        $this->app->bind(DatabaseDumper::class, fn () => $fake);
        $this->app->bind(DatabaseRestorer::class, fn () => $fake);
    }

    public function test_restore_brings_back_diagrams_versions_collaborators_and_shares_through_the_http_endpoints(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        app(VersionRepository::class)->save($diagram, $owner, '<bpmn:definitions>original</bpmn:definitions>');

        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $collaborator->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $share = Share::factory()->for($diagram)->for($owner, 'author')->create([
            'allow_download' => true,
        ]);

        $originalVersions = app(VersionRepository::class)->list($diagram);
        $this->assertCount(2, $originalVersions);
        $oldestVersionId = $originalVersions[1]['id'];

        $downloadUrl = $share->downloadUrl();

        // --- backup ---
        $this->artisan('backup:run')->assertSuccessful();

        $artifact = collect(Storage::disk('backups')->files())
            ->first(fn ($file) => str_ends_with($file, '.tar.gz'));
        $this->assertNotNull($artifact, 'Expected a backup artifact to have been produced.');

        // --- mutate everything the restore is supposed to bring back ---
        app(VersionRepository::class)->save($diagram, $owner, '<bpmn:definitions>mutated after backup</bpmn:definitions>');

        Collaborator::where('diagram_id', $diagram->id)
            ->where('user_id', $collaborator->id)
            ->delete();

        $share->update(['revoked_at' => now()]);

        $this->assertNotSame(
            '<bpmn:definitions>original</bpmn:definitions>',
            Storage::disk('diagrams')->get($diagram->disk_path),
        );

        // --- restore ---
        $this->artisan('backup:restore', ['artifact' => $artifact, '--force' => true])
            ->assertSuccessful();

        // --- assert original state is back, through the HTTP endpoints ---
        $this->actingAs($owner);

        $this->getJson(route('diagrams.content', $diagram->slug))
            ->assertOk()
            ->assertJson(['content' => '<bpmn:definitions>original</bpmn:definitions>']);

        $versionsResponse = $this->getJson(route('diagrams.versions', $diagram->slug))->assertOk();
        $restoredVersionCount = count($versionsResponse->json('versions'));
        $this->assertSame(2, $restoredVersionCount, 'Version history must match what it was at backup time.');

        $this->getJson(route('diagrams.collaborators.index', $diagram->slug))
            ->assertOk()
            ->assertJsonFragment(['user_id' => $collaborator->id, 'status' => Collaborator::STATUS_APPROVED]);

        $this->get($downloadUrl)
            ->assertOk()
            ->assertSee('<bpmn:definitions>original</bpmn:definitions>', false);

        // Restoring an old Version through the normal HTTP endpoint still
        // works against the restored Version repository (its .git history
        // survived the backup/restore round trip intact).
        $this->postJson(route('diagrams.versions.restore', [$diagram->slug, $oldestVersionId]))
            ->assertOk()
            ->assertJson(['restored' => true]);
    }

    public function test_restore_command_requires_explicit_confirmation(): void
    {
        Storage::disk('backups')->put('backup-2026-01-01_000000-abcd1234.tar.gz', 'not a real archive');

        $this->artisan('backup:restore', ['artifact' => 'backup-2026-01-01_000000-abcd1234.tar.gz'])
            ->expectsConfirmation(
                'This will OVERWRITE the current database and Diagrams directory with the contents of '
                    .'"backup-2026-01-01_000000-abcd1234.tar.gz". This cannot be undone. Continue?',
                'no',
            )
            ->assertFailed();
    }

    public function test_restore_command_fails_if_artifact_does_not_exist(): void
    {
        $this->artisan('backup:restore', ['artifact' => 'does-not-exist.tar.gz', '--force' => true])
            ->assertFailed();
    }

    public function test_restore_command_fails_if_artifact_has_invalid_manifest(): void
    {
        // A real .tar.gz, so extraction succeeds and validateManifest() is
        // actually exercised — it's missing manifest.json entirely.
        $sourceDir = sys_get_temp_dir().'/restore-test-'.uniqid();
        mkdir($sourceDir.'/diagrams', 0755, true);
        file_put_contents($sourceDir.'/database.dump', 'fake dump');
        $archivePath = $sourceDir.'.tar.gz';
        exec(sprintf('tar -czf %s -C %s .', escapeshellarg($archivePath), escapeshellarg($sourceDir)));

        Storage::disk('backups')->put(
            'backup-2026-01-01_000000-abcd1234.tar.gz',
            file_get_contents($archivePath),
        );

        $this->artisan('backup:restore', [
            'artifact' => 'backup-2026-01-01_000000-abcd1234.tar.gz',
            '--force' => true,
        ])->assertFailed();
    }
}

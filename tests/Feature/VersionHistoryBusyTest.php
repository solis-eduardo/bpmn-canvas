<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A writer that cannot get a Diagram's Version history lock in time is told
 * to retry (503), instead of failing with a 500 or aborting `locks:expire`
 * (issue #242). Every test holds the cache lock itself to force the timeout.
 */
class VersionHistoryBusyTest extends TestCase
{
    use RefreshDatabase;

    private Lock $held;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        config(['bpmn.version_lock_wait_seconds' => 0]);
    }

    protected function tearDown(): void
    {
        if (isset($this->held)) {
            $this->held->release();
        }

        parent::tearDown();
    }

    private function holdHistory(int $diagramId): void
    {
        $this->held = Cache::lock('diagram-versions:'.$diagramId, 3600);
        $this->assertTrue($this->held->get());
    }

    private function heldDiagram(User $holder): Diagram
    {
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        return $diagram;
    }

    private function content(string $label): string
    {
        return preg_replace('/ name="[^"]*"/', ' name="'.$label.'"', Diagram::emptyBpmn(), 1);
    }

    private function assertBusy($response): void
    {
        $response->assertStatus(503)
            ->assertHeader('Retry-After', '5')
            ->assertJsonPath('message', __('messages.error_history_busy'));
    }

    private function versionCount(Diagram $diagram): int
    {
        return count(app(VersionRepository::class)->list($diagram));
    }

    public function test_saving_content_answers_503_and_writes_nothing(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $before = Storage::disk('diagrams')->get($diagram->disk_path);
        $this->holdHistory($diagram->id);

        $this->assertBusy($this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => $this->content('Busy'),
        ]));

        $this->assertSame($before, Storage::disk('diagrams')->get($diagram->disk_path));
    }

    public function test_a_save_naming_its_base_version_answers_503_and_writes_nothing(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $base = $this->actingAs($holder)->getJson('/diagrams/'.$diagram->slug.'/content')->json('version');
        $before = Storage::disk('diagrams')->get($diagram->disk_path);
        $this->holdHistory($diagram->id);

        $this->assertBusy($this->actingAs($holder)->putJson('/diagrams/'.$diagram->slug.'/content', [
            'content' => $this->content('Busy'),
            'base_version' => $base,
        ]));

        $this->assertSame($before, Storage::disk('diagrams')->get($diagram->disk_path));
    }

    public function test_the_message_is_translated_to_the_users_locale(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->holdHistory($diagram->id);

        $this->actingAs($holder)->withSession(['locale' => 'pt_BR'])->putJson('/diagrams/'.$diagram->slug.'/content', ['content' => $this->content('Busy')])
            ->assertStatus(503)
            ->assertJsonPath('message', trans('messages.error_history_busy', locale: 'pt_BR'));
    }

    public function test_a_browser_request_gets_a_plain_503_with_retry_after(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->holdHistory($diagram->id);

        $this->actingAs($holder)->put('/diagrams/'.$diagram->slug.'/content', ['content' => $this->content('Busy')])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5')
            ->assertSee(__('messages.error_history_busy'));
    }

    public function test_restoring_answers_503_not_404(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $first = app(VersionRepository::class)->save($diagram, $holder, $this->content('One'));
        app(VersionRepository::class)->save($diagram, $holder, $this->content('Two'));
        $this->holdHistory($diagram->id);

        $this->assertBusy($this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$first.'/restore'));
    }

    public function test_releasing_with_a_draft_answers_503_and_keeps_the_lock_and_draft(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->content('Draft')])->assertOk();
        $versions = $this->versionCount($diagram);
        $this->holdHistory($diagram->id);

        $this->assertBusy($this->actingAs($holder)->deleteJson('/diagrams/'.$diagram->slug.'/lock', ['save_draft' => true]));

        $diagram->refresh();
        $this->assertSame($holder->id, $diagram->locked_by);
        $this->assertNotEmpty($diagram->lock_draft);
        $this->assertSame($versions, $this->versionCount($diagram));
    }

    public function test_deactivating_a_user_rolls_back_whole_when_a_release_times_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->content('Draft')])->assertOk();
        $this->holdHistory($diagram->id);

        $this->actingAs($admin)->postJson('/admin/users/'.$holder->id.'/deactivate')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5');

        $this->assertNull($holder->fresh()->deactivated_at);
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_creating_a_diagram_answers_503(): void
    {
        $user = User::factory()->create();
        $this->holdHistory((int) Diagram::max('id') + 1);

        $this->actingAs($user)->postJson('/diagrams', ['title' => 'Brand new'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5');

        $this->assertSame(0, Diagram::count());
        $this->assertSame([], Storage::disk('diagrams')->allFiles());
        $this->assertSame([], Storage::disk('diagrams')->allDirectories());
    }

    public function test_forking_answers_503(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create(['owner_id' => $owner->id]);
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        $this->holdHistory((int) Diagram::max('id') + 1);

        $this->actingAs($owner)->postJson('/diagrams/'.$diagram->slug.'/fork')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5');

        $this->assertSame(1, Diagram::count());
        $this->assertSame([$diagram->slug], Storage::disk('diagrams')->directories());
    }

    public function test_forking_local_edits_answers_503_and_leaves_no_orphan_diagram(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create(['owner_id' => $owner->id]);
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        $this->holdHistory((int) Diagram::max('id') + 1);

        $this->actingAs($owner)->postJson('/diagrams/'.$diagram->slug.'/fork', ['content' => $this->content('Local edits')])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5');

        $this->assertSame(1, Diagram::count());
        $this->assertSame([$diagram->slug], Storage::disk('diagrams')->directories());
    }

    public function test_the_lock_wait_setting_is_at_least_one_second(): void
    {
        foreach (['0', '-5', 'abc'] as $value) {
            putenv('VERSION_LOCK_WAIT_SECONDS='.$value);
            $this->assertSame(1, (require base_path('config/bpmn.php'))['version_lock_wait_seconds'], $value);
        }

        putenv('VERSION_LOCK_WAIT_SECONDS=12');
        $this->assertSame(12, (require base_path('config/bpmn.php'))['version_lock_wait_seconds']);
        putenv('VERSION_LOCK_WAIT_SECONDS');
    }

    public function test_a_busy_restore_leaves_the_free_lock_with_the_requester(): void
    {
        $holder = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create(['owner_id' => $holder->id]);
        Storage::disk('diagrams')->put($diagram->disk_path, Diagram::emptyBpmn());
        $first = app(VersionRepository::class)->save($diagram, $holder, $this->content('One'));
        app(VersionRepository::class)->save($diagram, $holder, $this->content('Two'));
        $this->holdHistory($diagram->id);

        $this->assertBusy($this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/versions/'.$first.'/restore'));

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_lock_expiry_skips_a_busy_diagram_and_still_releases_the_others(): void
    {
        $busyHolder = User::factory()->create();
        $busy = $this->heldDiagram($busyHolder);
        $this->actingAs($busyHolder)->patchJson('/diagrams/'.$busy->slug.'/lock', ['draft' => $this->content('Busy draft')])->assertOk();

        $freeHolder = User::factory()->create();
        $free = $this->heldDiagram($freeHolder);
        $this->actingAs($freeHolder)->patchJson('/diagrams/'.$free->slug.'/lock', ['draft' => $this->content('Free draft')])->assertOk();

        $this->holdHistory($busy->id);
        Log::spy();

        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful()->expectsOutputToContain('Released 1 expired');

        $this->assertSame($busyHolder->id, $busy->fresh()->locked_by);
        $this->assertNotEmpty($busy->fresh()->lock_draft);
        $this->assertNull($free->fresh()->locked_by);
        $this->assertSame(2, $this->versionCount($free));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_lock_expiry_retries_the_skipped_diagram_on_the_next_run(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->actingAs($holder)->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->content('Draft')])->assertOk();
        $this->holdHistory($diagram->id);

        $this->travel(5)->minutes();
        $this->artisan('locks:expire')->assertSuccessful();
        $this->assertSame($holder->id, $diagram->fresh()->locked_by);

        $this->held->release();
        $this->artisan('locks:expire')->assertSuccessful();

        $this->assertNull($diagram->fresh()->locked_by);
        $this->assertSame(2, $this->versionCount($diagram));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Releasing a Lock by hand can keep the holder's unsaved draft as a
 * provisional Version, the same way expiry does (issue #196).
 */
class LockReleaseDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function heldDiagram(User $holder): Diagram
    {
        $diagram = Diagram::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        return $diagram;
    }

    private function draft(string $label): string
    {
        return preg_replace('/ name="[^"]*"/', ' name="'.$label.'"', Diagram::emptyBpmn(), 1);
    }

    private function sendDraft(User $holder, Diagram $diagram, string $label): void
    {
        $this->actingAs($holder)
            ->patchJson('/diagrams/'.$diagram->slug.'/lock', ['draft' => $this->draft($label)])
            ->assertOk();
    }

    private function versionCount(Diagram $diagram): int
    {
        return count(app(VersionRepository::class)->list($diagram));
    }

    public function test_the_lock_state_reports_whether_a_draft_exists(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);

        $this->actingAs($holder)->getJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertOk()->assertJson(['has_draft' => false]);

        $this->sendDraft($holder, $diagram, 'Unsaved');

        $this->actingAs($holder)->getJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertOk()->assertJson(['has_draft' => true]);
    }

    public function test_an_administrator_force_releasing_can_see_that_the_holder_has_a_draft(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Holder work');

        $this->actingAs($admin)->getJson('/diagrams/'.$diagram->slug.'/lock')
            ->assertOk()->assertJson(['has_draft' => true]);

        $this->actingAs($admin)->get('/diagrams/'.$diagram->slug)
            ->assertOk()
            ->assertSee('data-release-draft-message="'.e(__('messages.release_lock_draft_admin')).'"', false)
            ->assertSee(__('messages.release_save_and_release'));
    }

    public function test_an_administrator_can_save_the_holders_draft_when_force_releasing(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Holder work');
        $before = $this->versionCount($diagram);

        $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock', ['save_draft' => true])
            ->assertOk()->assertJson(['unlocked' => true]);

        $diagram = $diagram->fresh();
        $this->assertNull($diagram->locked_by);
        $this->assertNull($diagram->lock_draft);

        $list = app(VersionRepository::class)->list($diagram);
        $this->assertCount($before + 1, $list);
        $this->assertSame(__('messages.provisional_version_released'), $list[0]['name']);
        $this->assertNotSame(__('messages.provisional_version'), $list[0]['name']);
        $this->assertSame($holder->name, $list[0]['author']);
        $this->assertStringContainsString('Holder work', Storage::disk('diagrams')->get($diagram->disk_path));
    }

    public function test_releasing_without_save_draft_discards_the_draft(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Holder work');
        $before = $this->versionCount($diagram);

        $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();

        $diagram = $diagram->fresh();
        $this->assertNull($diagram->locked_by);
        $this->assertNull($diagram->lock_draft);
        $this->assertSame($before, $this->versionCount($diagram));
    }

    public function test_the_holder_can_save_their_current_content_when_releasing(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Old draft');

        $this->actingAs($holder)->deleteJson('/diagrams/'.$diagram->slug.'/lock', [
            'save_draft' => true,
            'draft' => $this->draft('Newest work'),
        ])->assertOk();

        $diagram = $diagram->fresh();
        $this->assertNull($diagram->locked_by);
        $content = Storage::disk('diagrams')->get($diagram->disk_path);
        $this->assertStringContainsString('Newest work', $content);
        $this->assertStringNotContainsString('Old draft', $content);
        $this->assertSame($holder->name, app(VersionRepository::class)->list($diagram)[0]['author']);
    }

    public function test_an_administrator_cannot_inject_content_through_the_request(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Holder work');

        $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock', [
            'save_draft' => true,
            'draft' => $this->draft('Admin injected'),
        ])->assertOk();

        $content = Storage::disk('diagrams')->get($diagram->fresh()->disk_path);
        $this->assertStringContainsString('Holder work', $content);
        $this->assertStringNotContainsString('Admin injected', $content);
    }

    public function test_saving_with_a_blank_draft_just_releases(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);
        $before = $this->versionCount($diagram);

        $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock', ['save_draft' => true])->assertOk();

        $this->assertNull($diagram->fresh()->locked_by);
        $this->assertSame($before, $this->versionCount($diagram));
    }

    public function test_a_holder_who_lost_edit_rights_gets_no_version_but_the_lock_is_released(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $holder->id, 'status' => Collaborator::STATUS_APPROVED]);
        $this->actingAs($holder)->postJson('/diagrams/'.$diagram->slug.'/lock')->assertOk();
        $this->sendDraft($holder, $diagram, 'Lapsed work');
        Collaborator::where('diagram_id', $diagram->id)->delete();
        $before = $this->versionCount($diagram);

        $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock', ['save_draft' => true])->assertOk();

        $diagram = $diagram->fresh();
        $this->assertNull($diagram->locked_by);
        $this->assertSame($before, $this->versionCount($diagram));
    }

    public function test_the_lock_stays_held_when_saving_the_version_fails(): void
    {
        $holder = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Holder work');
        $this->mock(VersionRepository::class)->shouldReceive('save')->andThrow(new \RuntimeException('disk full'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->deleteJson('/diagrams/'.$diagram->slug.'/lock', ['save_draft' => true]);
            $this->fail('Expected the failed save to surface.');
        } catch (\RuntimeException) {
            // expected
        }

        $diagram = $diagram->fresh();
        $this->assertSame($holder->id, $diagram->locked_by);
        $this->assertNotNull($diagram->lock_draft);
    }

    public function test_someone_who_may_not_release_cannot_save_the_draft_either(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = $this->heldDiagram($holder);
        $this->sendDraft($holder, $diagram, 'Holder work');
        $before = $this->versionCount($diagram);

        $this->actingAs($other)->deleteJson('/diagrams/'.$diagram->slug.'/lock', ['save_draft' => true])->assertForbidden();

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
        $this->assertSame($before, $this->versionCount($diagram));
    }
}

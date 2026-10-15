<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Member;
use App\Models\Share;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The append-only activity log of sensitive access changes (CONTEXT.md,
 * "Activity Log"; ADR-0025; issue #252).
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_a_diagram_ownership_transfer_is_logged_with_actor_and_before_after(): void
    {
        $owner = User::factory()->create(['name' => 'Olivia Owner']);
        $newOwner = User::factory()->create(['name' => 'Nora Newowner']);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", ['user_id' => $newOwner->id])->assertOk();

        $entry = ActivityLog::where('action', 'diagram.owner_transferred')->sole();
        $this->assertSame($owner->id, $entry->actor_id);
        $this->assertSame('Olivia Owner', $entry->actor_name);
        $this->assertSame($diagram->id, $entry->diagram_id);
        $this->assertSame($diagram->title, $entry->diagram_title);
        $this->assertSame('user', $entry->subject_type);
        $this->assertSame($newOwner->id, $entry->subject_id);
        $this->assertSame('Nora Newowner', $entry->subject_label);
        $this->assertSame(['diagram_owner' => 'Olivia Owner'], $entry->before);
        $this->assertSame(['diagram_owner' => 'Nora Newowner', 'previous_owner_access' => 'collaborator'], $entry->after);
        $this->assertNotNull($entry->created_at);
    }

    public function test_a_give_up_access_transfer_records_that_the_previous_owner_kept_none(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", ['user_id' => $newOwner->id, 'give_up_access' => true])->assertOk();

        $this->assertSame('none', ActivityLog::sole()->after['previous_owner_access']);
    }

    public function test_a_transfer_to_the_current_owner_logs_nothing(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/owner", ['user_id' => $owner->id])->assertOk();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_a_workspace_ownership_transfer_is_logged(): void
    {
        $owner = User::factory()->create(['name' => 'Wanda Owner']);
        $newOwner = User::factory()->create(['name' => 'Walt New']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'origin' => Member::ORIGIN_MANUAL]);

        $this->actingAs($owner)->put("/workspaces/{$workspace->id}/owner", ['user_id' => $newOwner->id]);

        $entry = ActivityLog::where('action', 'workspace.owner_transferred')->sole();
        $this->assertSame($owner->id, $entry->actor_id);
        $this->assertSame($workspace->id, $entry->workspace_id);
        $this->assertNull($entry->diagram_id);
        $this->assertSame('Walt New', $entry->subject_label);
        $this->assertSame(['workspace_owner' => 'Wanda Owner'], $entry->before);
        $this->assertSame(['workspace_owner' => 'Walt New'], $entry->after);
    }

    public function test_collaborator_grant_role_change_and_removal_are_logged(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create(['name' => 'Cleo Collab']);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/collaborators", ['user_id' => $target->id])->assertCreated();
        $grant = ActivityLog::where('action', 'collaborator.granted')->sole();
        $this->assertSame($owner->id, $grant->actor_id);
        $this->assertSame($diagram->id, $grant->diagram_id);
        $this->assertSame($target->id, $grant->subject_id);
        $this->assertNull($grant->before);
        $this->assertSame(['status' => 'approved', 'role' => 'collaborator'], $grant->after);

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/collaborators/{$target->id}", ['role' => 'manager'])->assertOk();
        $change = ActivityLog::where('action', 'collaborator.role_changed')->sole();
        $this->assertSame(['role' => 'collaborator'], $change->before);
        $this->assertSame(['role' => 'manager'], $change->after);

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$target->id}")->assertOk();
        $removal = ActivityLog::where('action', 'collaborator.removed')->sole();
        $this->assertSame(['status' => 'approved', 'role' => 'manager'], $removal->before);
        $this->assertNull($removal->after);
    }

    public function test_approving_and_declining_access_requests_are_logged(): void
    {
        $owner = User::factory()->create();
        $approved = User::factory()->create();
        $declined = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        foreach ([$approved, $declined] as $requester) {
            $this->actingAs($requester)->postJson("/diagrams/{$diagram->slug}/collaborators", ['user_id' => $requester->id]);
        }
        $this->assertSame(0, ActivityLog::count(), 'a self-request is not a sensitive change by itself');

        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/collaborators/{$approved->id}")->assertOk();
        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}/collaborators/{$declined->id}")->assertOk();

        $grant = ActivityLog::where('action', 'collaborator.granted')->sole();
        $this->assertSame(['status' => 'requested'], $grant->before);
        $this->assertSame($approved->id, $grant->subject_id);
        $declinedEntry = ActivityLog::where('action', 'collaborator.request_declined')->sole();
        $this->assertSame($declined->id, $declinedEntry->subject_id);
    }

    public function test_share_creation_and_revocation_are_logged_without_the_token(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();

        $id = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares", ['days' => 3, 'allow_download' => true])
            ->assertCreated()->json('id');

        $created = ActivityLog::where('action', 'share.created')->sole();
        $this->assertSame('share', $created->subject_type);
        $this->assertSame($id, $created->subject_id);
        $this->assertSame($diagram->id, $created->diagram_id);
        $this->assertTrue($created->after['allow_download']);
        $this->assertNull($created->after['pinned_version_id']);
        $this->assertStringNotContainsString(Share::find($id)->token, json_encode($created->toArray()));

        $this->actingAs($owner)->deleteJson("/shares/{$id}")->assertOk();
        $this->assertSame($id, ActivityLog::where('action', 'share.revoked')->sole()->subject_id);
    }

    public function test_an_administrator_force_releasing_a_lock_is_logged_but_a_holder_release_is_not(): void
    {
        $holder = User::factory()->create(['name' => 'Hal Holder']);
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Ada Admin']);
        $diagram = Diagram::factory()->for($holder, 'author')->create();

        $this->actingAs($holder)->postJson("/diagrams/{$diagram->slug}/lock")->assertOk();
        $this->actingAs($holder)->deleteJson("/diagrams/{$diagram->slug}/lock")->assertOk();
        $this->assertSame(0, ActivityLog::count());

        $this->actingAs($holder)->postJson("/diagrams/{$diagram->slug}/lock")->assertOk();
        $this->actingAs($admin)->deleteJson("/diagrams/{$diagram->slug}/lock")->assertOk();

        $entry = ActivityLog::sole();
        $this->assertSame('lock.force_released', $entry->action);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame('Hal Holder', $entry->subject_label);
        $this->assertSame(['holder' => 'Hal Holder'], $entry->before);
    }

    public function test_workspace_membership_changes_are_logged(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mia Member']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Member::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'origin' => Member::ORIGIN_MANUAL]);

        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['user_id' => $member->id]);
        $added = ActivityLog::where('action', 'workspace.member_added')->sole();
        $this->assertSame($workspace->id, $added->workspace_id);
        $this->assertSame($workspace->name, $added->workspace_name);
        $this->assertSame($member->id, $added->subject_id);

        $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");
        $this->assertSame($member->id, ActivityLog::where('action', 'workspace.member_removed')->sole()->subject_id);
    }

    public function test_administrator_role_changes_are_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['name' => 'Tom Target']);

        $this->actingAs($admin)->put("/admin/users/{$target->id}", ['is_admin' => true]);
        $granted = ActivityLog::where('action', 'administrator.granted')->sole();
        $this->assertSame($admin->id, $granted->actor_id);
        $this->assertSame('Tom Target', $granted->subject_label);
        $this->assertSame(['is_admin' => false], $granted->before);
        $this->assertSame(['is_admin' => true], $granted->after);

        $this->actingAs($admin)->put("/admin/users/{$target->id}", ['is_admin' => false]);
        $this->assertSame(1, ActivityLog::where('action', 'administrator.revoked')->count());

        $this->actingAs($admin)->put("/admin/users/{$target->id}", ['name' => 'Renamed']);
        $this->assertSame(2, ActivityLog::count(), 'an update that leaves the role alone logs nothing');
    }

    public function test_entries_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();
        $entry = ActivityLog::sole();

        $this->expectException(\LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_deleting_an_entry_is_refused(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();

        $this->expectException(\LogicException::class);
        ActivityLog::sole()->delete();
    }

    public function test_the_editors_activity_panel_loads_only_that_diagrams_entries_for_those_who_manage_it(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $collaborator = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $other = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['title' => 'Unrelated Diagram']);
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $manager->id, 'status' => 'approved', 'role' => 'manager']);
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $collaborator->id, 'status' => 'approved']);
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();
        $this->actingAs($owner)->postJson("/diagrams/{$other->slug}/shares")->assertCreated();

        foreach ([$owner, $manager, $admin] as $viewer) {
            $this->actingAs($viewer)->getJson("/diagrams/{$diagram->slug}/activity")
                ->assertOk()
                ->assertJsonCount(1, 'entries')
                ->assertJsonPath('entries.0.action', __('messages.activity_action_share_created'))
                ->assertJsonPath('entries.0.actor', $owner->name)
                ->assertJsonPath('next_page', null);
            $this->get("/diagrams/{$diagram->slug}")->assertOk()
                ->assertSee('id="activity-panel"', false)
                ->assertSee("/diagrams/{$diagram->slug}/activity", false);
        }

        $this->actingAs($collaborator)->getJson("/diagrams/{$diagram->slug}/activity")->assertForbidden();
        $this->get("/diagrams/{$diagram->slug}")->assertDontSee('id="activity-panel"', false);
    }

    public function test_the_activity_panel_filters_and_pages_entries(): void
    {
        config(['activity.per_page' => 2]);
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $shareId = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->json('id');
        $this->actingAs($owner)->deleteJson("/shares/{$shareId}");
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();

        $first = $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/activity")
            ->assertJsonCount(2, 'entries')->assertJsonPath('next_page', 2);
        $this->assertNotNull($first->json('entries.0.created_at'));
        $this->getJson("/diagrams/{$diagram->slug}/activity?page=2")->assertJsonCount(1, 'entries')->assertJsonPath('next_page', null);
        $this->getJson("/diagrams/{$diagram->slug}/activity?action=share.revoked")->assertJsonCount(1, 'entries');
        $this->getJson("/diagrams/{$diagram->slug}/activity?actor=nobody")->assertJsonCount(0, 'entries');
    }

    public function test_the_activity_of_a_hidden_diagram_answers_404(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = Diagram::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs(User::factory()->create())->getJson("/diagrams/{$diagram->slug}/activity")->assertNotFound();
    }

    public function test_entries_are_json_encoded_not_markup_so_the_panel_can_insert_them_as_text(): void
    {
        $owner = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();

        $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/activity")
            ->assertOk()->assertJsonPath('entries.0.actor', '<script>alert(1)</script>');
    }

    public function test_the_global_log_escapes_user_controlled_names(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();

        $this->actingAs($admin)->get('/admin/activity')
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_the_global_log_is_for_administrators_and_filters_by_action_actor_diagram_and_dates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $alice = User::factory()->create(['name' => 'Alice Actor']);
        $bob = User::factory()->create(['name' => 'Bob Builder']);
        $first = Diagram::factory()->for($alice, 'author')->for($alice, 'owner')->create(['title' => 'Invoice flow']);
        $second = Diagram::factory()->for($bob, 'author')->for($bob, 'owner')->create(['title' => 'Onboarding']);
        $shareId = $this->actingAs($alice)->postJson("/diagrams/{$first->slug}/shares")->json('id');
        $this->actingAs($alice)->deleteJson("/shares/{$shareId}");
        $this->travel(-10)->days();
        $this->actingAs($bob)->postJson("/diagrams/{$second->slug}/shares")->assertCreated();
        $this->travelBack();

        $this->actingAs($alice)->get('/admin/activity')->assertForbidden();

        $get = fn (string $query) => $this->actingAs($admin)->get('/admin/activity'.$query)->assertOk();
        $get('')->assertSee('Alice Actor')->assertSee('Bob Builder');
        $get('?action=share.revoked')->assertSee(__('messages.activity_action_share_revoked'))->assertDontSee('Bob Builder');
        $get('?actor=bob')->assertSee('Onboarding')->assertDontSee('Invoice flow');
        $get('?diagram=invoice')->assertSee('Alice Actor')->assertDontSee('Bob Builder');
        $get('?from='.now()->subDays(2)->toDateString())->assertSee('Alice Actor')->assertDontSee('Bob Builder');
        $get('?to='.now()->subDays(5)->toDateString())->assertSee('Bob Builder')->assertDontSee('Alice Actor');
        $get('?actor=%25')->assertSee(__('messages.activity_no_match'));
    }

    public function test_the_prune_command_removes_only_entries_older_than_the_configured_retention(): void
    {
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create();
        $this->travel(-40)->days();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();
        $this->travelBack();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares")->assertCreated();

        config(['activity.retention_days' => 0]);
        $this->artisan('activity:prune')->assertSuccessful();
        $this->assertSame(2, ActivityLog::count(), 'zero keeps everything');

        config(['activity.retention_days' => 30]);
        $this->artisan('activity:prune')->assertSuccessful();
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_every_logged_action_has_a_label_in_each_language(): void
    {
        foreach (['en', 'pt_BR', 'es'] as $locale) {
            app()->setLocale($locale);
            foreach (ActivityLog::ACTIONS as $action) {
                $key = 'messages.activity_action_'.str_replace('.', '_', $action);
                $this->assertNotSame($key, __($key), "$key missing in $locale");
            }
        }
    }
}

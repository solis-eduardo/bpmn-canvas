<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DiagramCommentPosted;
use App\Notifications\QueuedMailChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comment Threads (CONTEXT.md, "Comment Thread"; ADR-0024): written by anyone
 * who can open the Diagram, without the Lock; resolvable; orphaned when their
 * element is gone; and they notify the Diagram Owner and the participants.
 */
class CommentThreadTest extends TestCase
{
    use RefreshDatabase;

    private const BPMN = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" id="Defs_1">
  <bpmn:process id="Process_1">
    <bpmn:task id="Task_1" name="Approve" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="Task_1_di" bpmnElement="Task_1" />
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>
BPMN;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function diagram(?User $owner = null): Diagram
    {
        $diagram = Diagram::factory()->for($owner ?? User::factory()->create(), 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, self::BPMN);

        return $diagram;
    }

    private function collaborator(Diagram $diagram, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $user->id, 'status' => Collaborator::STATUS_APPROVED]);

        return $user;
    }

    private function threadsUrl(Diagram $diagram, string $suffix = ''): string
    {
        return "/diagrams/{$diagram->slug}/comment-threads{$suffix}";
    }

    public function test_a_thread_can_be_opened_on_an_element_and_listed(): void
    {
        $owner = User::factory()->create(['name' => 'Olga']);
        $diagram = $this->diagram($owner);

        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'Is this right?', 'element_id' => 'Task_1'])
            ->assertCreated();

        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))
            ->assertOk()
            ->assertJsonCount(1, 'threads')
            ->assertJsonPath('threads.0.element_id', 'Task_1')
            ->assertJsonPath('threads.0.orphaned', false)
            ->assertJsonPath('threads.0.resolved', false)
            ->assertJsonPath('threads.0.comments.0.body', 'Is this right?')
            ->assertJsonPath('threads.0.comments.0.author', 'Olga');
    }

    public function test_a_thread_can_be_about_the_whole_diagram(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);

        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'General remark'])->assertCreated();

        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))
            ->assertJsonPath('threads.0.element_id', null)
            ->assertJsonPath('threads.0.orphaned', false);
    }

    public function test_comments_do_not_need_the_lock(): void
    {
        $holder = User::factory()->create();
        $diagram = $this->diagram($holder);
        $reviewer = $this->collaborator($diagram);
        $this->actingAs($holder)->postJson("/diagrams/{$diagram->slug}/lock")->assertOk();

        $this->actingAs($reviewer)->postJson($this->threadsUrl($diagram), ['body' => 'Looks off', 'element_id' => 'Task_1'])
            ->assertCreated();

        $this->assertSame($holder->id, $diagram->fresh()->locked_by);
    }

    public function test_an_administrator_who_can_only_open_the_diagram_can_comment(): void
    {
        $diagram = $this->diagram();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson($this->threadsUrl($diagram), ['body' => 'Seen'])->assertCreated();
    }

    public function test_a_diagram_hidden_in_a_workspace_answers_404(): void
    {
        $workspace = Workspace::factory()->create();
        $diagram = $this->diagram();
        $diagram->update(['workspace_id' => $workspace->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson($this->threadsUrl($diagram))->assertNotFound();
        $this->actingAs($outsider)->postJson($this->threadsUrl($diagram), ['body' => 'x'])->assertNotFound();
    }

    public function test_the_body_is_required_and_limited(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);

        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => ''])->assertUnprocessable();
        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => str_repeat('a', 2001)])->assertUnprocessable();
    }

    public function test_a_thread_whose_element_was_deleted_is_listed_as_orphaned(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'About a gone task', 'element_id' => 'Task_9'])->assertCreated();
        // A diagram-interchange shape id is not a model element.
        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'Shape', 'element_id' => 'Task_1_di'])->assertCreated();

        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))
            ->assertJsonPath('threads.0.orphaned', true)
            ->assertJsonPath('threads.0.comments.0.body', 'About a gone task')
            ->assertJsonPath('threads.1.orphaned', true);
    }

    public function test_replying_adds_a_comment_to_the_thread(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $reviewer = $this->collaborator($diagram);
        $id = $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'First'])->json('id');

        $this->actingAs($reviewer)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'Second'])->assertCreated();

        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))
            ->assertJsonCount(2, 'threads.0.comments')
            ->assertJsonPath('threads.0.comments.1.body', 'Second');
    }

    public function test_a_thread_can_be_resolved_and_a_reply_reopens_it(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $reviewer = $this->collaborator($diagram);
        $reviewer->update(['name' => 'Rita']);
        $id = $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'First'])->json('id');

        $this->actingAs($reviewer)->putJson($this->threadsUrl($diagram, "/{$id}/resolution"), ['resolved' => true])->assertOk();
        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))
            ->assertJsonPath('threads.0.resolved', true)
            ->assertJsonPath('threads.0.resolved_by', 'Rita');

        $this->actingAs($owner)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'Not yet'])->assertCreated();
        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))->assertJsonPath('threads.0.resolved', false);

        $this->actingAs($owner)->putJson($this->threadsUrl($diagram, "/{$id}/resolution"), ['resolved' => true])->assertOk();
        $this->actingAs($owner)->putJson($this->threadsUrl($diagram, "/{$id}/resolution"), ['resolved' => false])->assertOk();
        $this->actingAs($owner)->getJson($this->threadsUrl($diagram))->assertJsonPath('threads.0.resolved', false);
    }

    public function test_a_thread_of_another_diagram_is_not_reachable_through_this_one(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $other = $this->diagram($owner);
        $id = $this->actingAs($owner)->postJson($this->threadsUrl($other), ['body' => 'Elsewhere'])->json('id');

        $this->actingAs($owner)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'x'])->assertNotFound();
        $this->actingAs($owner)->putJson($this->threadsUrl($diagram, "/{$id}/resolution"), ['resolved' => true])->assertNotFound();
    }

    public function test_a_new_thread_notifies_the_diagram_owner_but_not_its_author(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $reviewer = $this->collaborator($diagram);

        $this->actingAs($reviewer)->postJson($this->threadsUrl($diagram), ['body' => 'Hi'])->assertCreated();
        Notification::assertSentTo($owner, DiagramCommentPosted::class);
        Notification::assertNotSentTo($reviewer, DiagramCommentPosted::class);

        Notification::fake();
        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'Mine'])->assertCreated();
        Notification::assertNothingSent();
    }

    public function test_a_reply_notifies_the_owner_and_the_other_participants_once_each(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $first = $this->collaborator($diagram);
        $second = $this->collaborator($diagram);
        $bystander = $this->collaborator($diagram);
        $id = $this->actingAs($first)->postJson($this->threadsUrl($diagram), ['body' => 'Q'])->json('id');

        Notification::fake();
        $this->actingAs($second)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'A'])->assertCreated();

        Notification::assertSentToTimes($owner, DiagramCommentPosted::class, 1);
        Notification::assertSentToTimes($first, DiagramCommentPosted::class, 1);
        Notification::assertNotSentTo($second, DiagramCommentPosted::class);
        Notification::assertNotSentTo($bystander, DiagramCommentPosted::class);
    }

    public function test_the_notification_follows_the_email_preference_and_carries_no_comment_text(): void
    {
        $owner = User::factory()->create(['email_notifications_enabled' => true]);
        $quiet = User::factory()->create(['email_notifications_enabled' => false]);
        $diagram = $this->diagram($owner);
        $this->collaborator($diagram, $quiet);
        $id = $this->actingAs($quiet)->postJson($this->threadsUrl($diagram), ['body' => 'Q'])->json('id');

        Notification::fake();
        $reviewer = $this->collaborator($diagram);
        $this->actingAs($reviewer)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'Secret text'])->assertCreated();

        Notification::assertSentTo($owner, DiagramCommentPosted::class, function ($notification, $channels) use ($owner) {
            $this->assertContains(QueuedMailChannel::class, $channels);
            $this->assertStringNotContainsString('Secret text', json_encode($notification->toArray($owner)));

            return true;
        });
        Notification::assertSentTo($quiet, DiagramCommentPosted::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_deactivated_users_and_users_who_lost_access_are_not_notified(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $gone = $this->collaborator($diagram);
        $deactivated = $this->collaborator($diagram);
        $id = $this->actingAs($gone)->postJson($this->threadsUrl($diagram), ['body' => 'a'])->json('id');
        $this->actingAs($deactivated)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'b'])->assertCreated();
        Collaborator::where('user_id', $gone->id)->delete();
        $deactivated->forceFill(['deactivated_at' => now()])->save();
        $author = $this->collaborator($diagram);

        Notification::fake();
        $this->actingAs($author)->postJson($this->threadsUrl($diagram, "/{$id}/comments"), ['body' => 'c'])->assertCreated();

        Notification::assertSentTo($owner, DiagramCommentPosted::class);
        Notification::assertNotSentTo($gone, DiagramCommentPosted::class);
        Notification::assertNotSentTo($deactivated, DiagramCommentPosted::class);
    }

    public function test_the_notification_reads_back_and_opens_the_comments_panel(): void
    {
        $owner = User::factory()->create(['name' => 'Olga']);
        $diagram = $this->diagram($owner);
        $reviewer = $this->collaborator($diagram, User::factory()->create(['name' => 'Rita']));
        $id = $this->actingAs($reviewer)->postJson($this->threadsUrl($diagram), ['body' => 'Hi'])->json('id');

        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($owner)->getJson('/notifications')->assertSee('Rita commented on '.$diagram->title);
        $this->actingAs($owner)->get("/notifications/{$notification->id}/read")
            ->assertRedirect(route('diagrams.show', $diagram->slug)."?open-panel=comments&thread={$id}");
    }

    public function test_deleting_a_diagram_deletes_its_comments(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $this->actingAs($owner)->postJson($this->threadsUrl($diagram), ['body' => 'Bye'])->assertCreated();

        $this->actingAs($owner)->deleteJson("/diagrams/{$diagram->slug}")->assertOk();

        $this->assertDatabaseCount('comment_threads', 0);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_the_editor_and_the_viewer_expose_the_comments_panel(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagram($owner);
        $url = route('diagrams.comments.index', $diagram->slug);

        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}")
            ->assertOk()
            ->assertSee('id="comments-panel"', false)
            ->assertSee('data-comments-url="'.$url.'"', false);

        $holder = $this->collaborator($diagram);
        $this->actingAs($holder)->postJson("/diagrams/{$diagram->slug}/lock")->assertOk();
        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}")
            ->assertOk()
            ->assertSee('id="comments-panel"', false)
            ->assertSee('data-comments-url="'.$url.'"', false);
    }
}

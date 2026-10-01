<?php

namespace Tests\Unit\Services;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Notifications\DiagramOwnershipTransferred;
use App\Services\DiagramOwnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DiagramOwnerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_previous_owner_becomes_a_collaborator_by_default(): void
    {
        Notification::fake();
        $diagram = Diagram::factory()->create();
        $previous = $diagram->owner;
        $newOwner = User::factory()->create();

        app(DiagramOwnerService::class)->transfer($diagram, $newOwner);

        $this->assertSame($newOwner->id, $diagram->fresh()->owner_id);
        $this->assertSame(Collaborator::STATUS_APPROVED, Collaborator::where('user_id', $previous->id)->value('status'));
        Notification::assertSentTo($newOwner, DiagramOwnershipTransferred::class);
    }

    public function test_giving_up_access_leaves_the_previous_owner_with_none(): void
    {
        Notification::fake();
        $diagram = Diagram::factory()->create();
        $previous = $diagram->owner;

        app(DiagramOwnerService::class)->transfer($diagram, User::factory()->create(), giveUpAccess: true);

        $this->assertSame(0, Collaborator::where('user_id', $previous->id)->count());
    }

    public function test_the_author_is_never_touched(): void
    {
        Notification::fake();
        $diagram = Diagram::factory()->create();
        $author = $diagram->author_id;

        app(DiagramOwnerService::class)->transfer($diagram, User::factory()->create());

        $this->assertSame($author, $diagram->fresh()->author_id);
    }

    public function test_the_new_owner_is_not_listed_twice(): void
    {
        Notification::fake();
        $diagram = Diagram::factory()->create();
        $newOwner = User::factory()->create();
        Collaborator::create(['diagram_id' => $diagram->id, 'user_id' => $newOwner->id, 'status' => Collaborator::STATUS_APPROVED]);

        app(DiagramOwnerService::class)->transfer($diagram, $newOwner);

        $this->assertSame(0, Collaborator::where('user_id', $newOwner->id)->count());
    }

    public function test_transferring_to_the_current_owner_changes_nothing_and_notifies_no_one(): void
    {
        Notification::fake();
        $diagram = Diagram::factory()->create();
        $owner = $diagram->owner;

        app(DiagramOwnerService::class)->transfer($diagram, $owner);

        $this->assertSame($owner->id, $diagram->fresh()->owner_id);
        $this->assertSame(0, Collaborator::count());
        Notification::assertNothingSent();
    }
}

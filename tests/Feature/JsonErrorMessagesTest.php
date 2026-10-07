<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Editor shows a JSON error's `message` to the User, so the framework's
 * own English messages are replaced with localized ones — by exception
 * type, never by matching Laravel's prose.
 */
class JsonErrorMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_a_missing_route_is_localized(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/diagrams/does-not-exist/content')
            ->assertNotFound()
            ->assertJsonPath('message', __('messages.error_not_found'));
    }

    /** An unauthorized action: the framework's message, not ours. */
    public function test_an_unauthorized_action_is_localized(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'author')->create([
            'owner_id' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->postJson('/diagrams/'.$diagram->slug.'/fork')
            ->assertForbidden()
            ->assertJsonPath('message', __('messages.error_forbidden'));
    }

    /**
     * A message we wrote deliberately is ours to keep — this is the case
     * the old implementation had to infer by comparing against Laravel's
     * English strings.
     */
    public function test_a_deliberate_message_is_kept(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagram = Diagram::factory()->for($holder, 'author')->create([
            'owner_id' => $holder->id,
        ]);
        Collaborator::create([
            'diagram_id' => $diagram->id,
            'user_id' => $other->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);
        $this->actingAs($holder)->get('/diagrams/'.$diagram->slug);

        $this->actingAs($other)
            ->putJson('/diagrams/'.$diagram->slug.'/content', [
                'content' => Diagram::emptyBpmn(),
            ])
            ->assertStatus(423)
            ->assertJsonPath('message', __('messages.error_diagram_locked_by', [
                'name' => $holder->name,
            ]));
    }

    /** Validation errors arrive localized field by field; leave them be. */
    public function test_validation_errors_are_left_alone(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create([
            'owner_id' => $user->id,
        ]);
        $this->actingAs($user)->get('/diagrams/'.$diagram->slug);

        $this->actingAs($user)
            ->putJson('/diagrams/'.$diagram->slug.'/content', ['content' => 'not xml'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content')
            ->assertJsonPath('message', fn ($message) => $message !== __('messages.error_forbidden'));
    }

    /** A 500 says nothing about the failure to the User. */
    public function test_a_server_error_is_localized_when_not_debugging(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/test-boom', fn () => throw new \RuntimeException('Database credentials rejected'));

        $this->actingAs(User::factory()->create())
            ->getJson('/test-boom')
            ->assertStatus(500)
            ->assertJsonPath('message', __('messages.error_server'));
    }

    /** While debugging, the real message is the point. */
    public function test_a_server_error_keeps_its_message_when_debugging(): void
    {
        config(['app.debug' => true]);
        Route::middleware('web')->get('/test-boom-debug', fn () => throw new \RuntimeException('Database credentials rejected'));

        $this->actingAs(User::factory()->create())
            ->getJson('/test-boom-debug')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Database credentials rejected');
    }

    /** Non-JSON requests keep Laravel's normal error pages. */
    public function test_html_requests_are_untouched(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/diagrams/does-not-exist')
            ->assertNotFound()
            ->assertDontSee(__('messages.error_not_found'));
    }
}

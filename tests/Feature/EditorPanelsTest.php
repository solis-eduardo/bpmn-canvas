<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Editor's side panels: the collaborator picker and the current Version flag.
 */
class EditorPanelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_the_collaborators_panel_invites_from_a_user_list_not_an_email_field(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create(['name' => 'Cass Candidate']);
        $diagram = Diagram::factory()->for($owner, 'author')->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('diagrams.show', $diagram->slug))->assertOk();

        $response->assertSee('id="collab-invite-user_id"', false)
            ->assertSee('Cass Candidate')
            ->assertDontSee('id="collab-invite-email"', false);
    }

    public function test_only_the_newest_version_is_flagged_as_current(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create();

        foreach (['one', 'two'] as $label) {
            $this->actingAs($user)->putJson('/diagrams/'.$diagram->slug.'/content', [
                'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">'.$label.'</bpmn:definitions>',
            ])->assertOk();
        }

        $items = collect($this->actingAs($user)->getJson('/diagrams/'.$diagram->slug.'/versions')->json('versions'))
            ->flatMap(fn ($day) => $day['items']);

        $this->assertGreaterThan(1, $items->count());
        $this->assertSame([true], $items->pluck('is_current')->filter()->unique()->values()->all());
        $this->assertCount(1, $items->where('is_current', true));
        $this->assertTrue($items->first()['is_current']);
    }
}

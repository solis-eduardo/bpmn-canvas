<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagramListingTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagram_listing_is_a_semantic_table_with_all_record_details_and_read_only_tags(): void
    {
        $author = User::factory()->create(['name' => 'Original Diagram Author']);
        $owner = User::factory()->create(['name' => 'Current Diagram Owner']);
        $editor = User::factory()->create(['name' => 'Current Lock Holder']);
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create([
            'title' => 'Quarterly Approval Process',
        ]);
        $diagram->forceFill([
            'locked_by' => $editor->id,
            'locked_at' => now(),
            'last_heartbeat_at' => now(),
        ])->save();
        $diagram->attachTag(Tag::findOrCreate('Finance'));

        $response = $this->actingAs($owner)->get('/diagrams');

        $response->assertOk();
        $response->assertSee('<table', false);
        $response->assertSee('<thead ', false);
        $response->assertSee('<tbody ', false);
        $response->assertSee('scope="col"', false);
        $response->assertSee(__('messages.diagram_column_title'));
        $response->assertSee(__('messages.diagram_column_author'));
        $response->assertSee(__('messages.diagram_column_owner'));
        $response->assertSee(__('messages.diagram_column_modified'));
        $response->assertSee(__('messages.diagram_column_lock'));
        $response->assertSee(__('messages.diagram_column_tags'));
        $response->assertSee(__('messages.diagram_column_actions'));
        $response->assertSee($diagram->title);
        $response->assertSee($author->name);
        $response->assertSee($owner->name);
        $response->assertSee($editor->name);
        $response->assertSee('Finance');
        $response->assertSee(route('diagrams.show', $diagram->slug), false);
        $response->assertDontSee(route('diagrams.tags.destroy', [$diagram->slug, $diagram->tags()->first()->id]), false);
        $response->assertDontSee(route('diagrams.tags.store', $diagram->slug), false);
        $response->assertSee('overflow-x-auto', false);
    }

    public function test_an_empty_diagram_listing_has_a_semantic_empty_table_row(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/diagrams');

        $response->assertOk();
        $response->assertSee('<tbody ', false);
        $response->assertSee('colspan="8"', false);
        $response->assertSee(__('messages.no_diagrams_yet'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function createDiagram(User $author, string $content = 'bpmn-content'): Diagram
    {
        $diagram = Diagram::factory()->for($author, 'author')->create();
        Storage::disk('diagrams')->put($diagram->disk_path, $content);

        return $diagram;
    }

    /**
     * Builds the download URL even when allow_download is false: used to
     * prove the server refuses access, since Share::downloadUrl() returns
     * null in that case.
     */
    private function signedDownloadUrlIgnoringPermission(Share $share): string
    {
        return URL::temporarySignedRoute(
            'shares.download',
            now()->addYears(10),
            ['share' => $share->id, 'format' => 'bpmn'],
        );
    }

    public function test_author_can_create_share_with_default_seven_days(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", []);

        $response->assertCreated();
        $response->assertJsonPath('allow_download', false);

        $share = Share::first();
        $this->assertNotNull($share);
        $this->assertTrue($share->expires_at->isSameDay(now()->addDays(7)));
    }

    public function test_author_can_create_share_with_custom_days_and_download(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", [
            'days' => 30,
            'allow_download' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('allow_download', true);
        $response->assertJsonStructure(['download_url']);

        $share = Share::first();
        $this->assertTrue($share->expires_at->isSameDay(now()->addDays(30)));
        $this->assertTrue($share->allow_download);
    }

    public function test_creation_rejects_less_than_one_day(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", [
            'days' => 0,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_creation_rejects_more_than_sixty_days(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", [
            'days' => 61,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_share_link_is_https_when_request_is_forwarded_as_https(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($author)
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->postJson("/diagrams/{$diagram->slug}/shares", []);

        $response->assertCreated();

        $url = $response->json('url');
        $this->assertStringStartsWith('https://', $url);

        // The signature must still validate for the https URL actually handed
        // to the client (not just for a locally-rebuilt http:// one).
        $visit = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get($url);
        $visit->assertOk();
    }

    public function test_non_author_cannot_create_share(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($other)->postJson("/diagrams/{$diagram->slug}/shares", []);

        $response->assertStatus(403);
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_guest_can_view_diagram_with_valid_share(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>');
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee($diagram->title);
    }

    public function test_public_viewer_shows_download_buttons_when_share_allows_download(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>');
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => true,
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee($diagram->title);
        $response->assertSee('id="btn-download"', false);
        $response->assertSee('data-export="bpmn"', false);
    }

    public function test_public_viewer_hides_download_buttons_when_share_disallows_download(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>');
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => false,
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee($diagram->title);
        $response->assertDontSee('id="btn-download"', false);
        $response->assertDontSee('data-export="bpmn"', false);
    }

    public function test_view_screen_puts_page_actions_in_a_row_below_the_navigation_header(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => true,
        ]);

        $html = $this->get($share->showUrl())->assertOk()->getContent();

        $headerEnd = strpos($html, '</header>');
        $this->assertNotFalse($headerEnd);
        $actionsRow = strpos($html, 'id="view-actions"');
        $this->assertNotFalse($actionsRow);
        $this->assertGreaterThan($headerEnd, $actionsRow);
        $this->assertGreaterThan($actionsRow, strpos($html, 'id="btn-download"'));
        $this->assertGreaterThan($actionsRow, strpos($html, $diagram->title, $actionsRow));
    }

    public function test_guest_sees_clear_message_when_share_expired(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $url = $share->showUrl();

        Carbon::setTestNow(now()->addDays(8));

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('expired', false);

        Carbon::setTestNow();
    }

    public function test_guest_sees_clear_message_when_share_revoked(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'revoked_at' => now(),
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee('revoked', false);
    }

    public function test_download_allowed_serves_the_bpmn_file(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content-to-download</bpmn:definitions>');
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => true,
        ]);

        $response = $this->get($share->downloadUrl());

        $response->assertOk();
        $response->assertSee('content-to-download', false);
    }

    public function test_download_blocked_when_share_disallows_it(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => false,
        ]);

        $response = $this->get($this->signedDownloadUrlIgnoringPermission($share));

        $response->assertStatus(403);
    }

    public function test_download_blocked_when_share_expired_even_if_allowed(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => true,
        ]);

        $url = $share->downloadUrl();

        Carbon::setTestNow(now()->addDays(8));

        $response = $this->get($url);

        $response->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_author_can_list_active_shares_of_diagram(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        Share::factory()->for($diagram)->for($author, 'author')->create(['allow_download' => true]);
        Share::factory()->for($diagram)->for($author, 'author')->create(['allow_download' => false]);
        Share::factory()->for($diagram)->for($author, 'author')->create(['revoked_at' => now()]);

        $response = $this->actingAs($author)->getJson("/diagrams/{$diagram->slug}/shares");

        $response->assertOk();
        $response->assertJsonCount(2, 'shares');
    }

    public function test_non_author_cannot_list_shares(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $diagram = $this->createDiagram($author);
        Share::factory()->for($diagram)->for($author, 'author')->create();

        $response = $this->actingAs($other)->getJson("/diagrams/{$diagram->slug}/shares");

        $response->assertStatus(403);
    }

    public function test_author_can_revoke_a_share(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create();

        $response = $this->actingAs($author)->deleteJson("/shares/{$share->id}");

        $response->assertOk();
        $this->assertNotNull($share->fresh()->revoked_at);

        $visit = $this->get($share->showUrl());
        $visit->assertSee('revoked', false);
    }

    public function test_non_author_cannot_revoke_a_share(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $diagram = $this->createDiagram($author);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create();

        $response = $this->actingAs($other)->deleteJson("/shares/{$share->id}");

        $response->assertStatus(403);
        $this->assertNull($share->fresh()->revoked_at);
    }

    public function test_diagram_can_have_multiple_active_shares_with_different_permissions(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>');

        $withDownload = Share::factory()->for($diagram)->for($author, 'author')->create([
            'allow_download' => true,
            'expires_at' => now()->addDays(10),
        ]);
        $withoutDownload = Share::factory()->for($diagram)->for($author, 'author')->create([
            'allow_download' => false,
            'expires_at' => now()->addDays(20),
        ]);

        $this->get($withDownload->showUrl())->assertOk();
        $this->get($withoutDownload->showUrl())->assertOk();

        $this->get($withDownload->downloadUrl())->assertOk();
        $this->get($this->signedDownloadUrlIgnoringPermission($withoutDownload))->assertStatus(403);

        $list = $this->actingAs($author)->getJson("/diagrams/{$diagram->slug}/shares");
        $list->assertOk();
        $list->assertJsonCount(2, 'shares');
    }

    public function test_an_admin_cannot_manage_shares_of_a_diagram_they_do_not_author(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($author, 'author')->create();

        $this->actingAs($admin)->postJson("/diagrams/{$diagram->slug}/shares")
            ->assertForbidden();

        $this->actingAs($admin)->getJson("/diagrams/{$diagram->slug}/shares")
            ->assertForbidden();

        $share = Share::factory()->for($diagram)->for($author, 'author')->create();

        $this->actingAs($admin)->deleteJson("/shares/{$share->id}")
            ->assertForbidden();
    }

    public function test_only_the_diagram_owner_manages_shares_when_it_differs_from_the_author(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares")
            ->assertForbidden();
        $this->assertDatabaseCount('shares', 0);

        $response = $this->actingAs($owner)->postJson("/diagrams/{$diagram->slug}/shares");
        $response->assertCreated();

        $share = Share::first();

        $this->actingAs($author)->deleteJson("/shares/{$share->id}")
            ->assertForbidden();
        $this->assertNull($share->fresh()->revoked_at);

        $this->actingAs($owner)->deleteJson("/shares/{$share->id}")
            ->assertOk();
        $this->assertNotNull($share->fresh()->revoked_at);
    }

    public function test_an_admin_cannot_manage_shares_even_when_owner_and_author_differ(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $diagram = Diagram::factory()->for($author, 'author')->for($owner, 'owner')->create();

        $this->actingAs($admin)->postJson("/diagrams/{$diagram->slug}/shares")
            ->assertForbidden();
    }

    /**
     * @return array<int, array{id: string, number: int}>
     */
    private function versionsOf(User $user, Diagram $diagram): array
    {
        return collect(
            $this->actingAs($user)->getJson("/diagrams/{$diagram->slug}/versions")->json('versions')
        )->flatMap(fn (array $day) => $day['items'])->values()->all();
    }

    public function test_share_created_without_a_version_follows_the_latest_version_by_default(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">initial</bpmn:definitions>');

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", []);

        $response->assertCreated();
        $response->assertJsonPath('pinned_version', null);
        $this->assertNull(Share::first()->pinned_version_id);
    }

    public function test_share_can_be_created_pinned_to_a_chosen_version(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">version one</bpmn:definitions>');

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">version two</bpmn:definitions>',
        ])->assertOk();

        $versions = $this->versionsOf($author, $diagram);
        $oldestVersion = collect($versions)->last();

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", [
            'pinned_version_id' => $oldestVersion['id'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('pinned_version.id', $oldestVersion['id']);
        $response->assertJsonPath('pinned_version.number', $oldestVersion['number']);

        $share = Share::first();
        $this->assertSame($oldestVersion['id'], $share->pinned_version_id);
        $this->assertTrue($share->isPinned());
    }

    public function test_creating_a_share_pinned_to_an_unknown_version_is_rejected(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", [
            'pinned_version_id' => 'deadbeef',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_pinned_share_keeps_serving_old_content_after_new_saves(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        // Save "old content" as its own Version, and capture the id the
        // Share will be pinned to.
        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">old content</bpmn:definitions>',
        ])->assertOk();
        $pinnedVersion = collect($this->versionsOf($author, $diagram))->first();

        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'pinned_version_id' => $pinnedVersion['id'],
        ]);

        // A new save happens after the Share was pinned.
        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">new content</bpmn:definitions>',
        ])->assertOk();

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee('old content', false);
        $response->assertDontSee('new content', false);
    }

    public function test_latest_share_serves_new_content_after_new_saves(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">old content</bpmn:definitions>',
        ])->assertOk();

        $share = Share::factory()->for($diagram)->for($author, 'author')->create();

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">new content</bpmn:definitions>',
        ])->assertOk();

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee('new content', false);
        $response->assertDontSee('old content', false);
    }

    public function test_pinned_share_download_serves_old_content_after_new_saves(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">old download content</bpmn:definitions>',
        ])->assertOk();
        $pinnedVersion = collect($this->versionsOf($author, $diagram))->first();

        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'allow_download' => true,
            'pinned_version_id' => $pinnedVersion['id'],
        ]);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">new download content</bpmn:definitions>',
        ])->assertOk();

        $response = $this->get($share->downloadUrl());

        $response->assertOk();
        $response->assertSee('old download content', false);
        $response->assertDontSee('new download content', false);
    }

    public function test_shares_list_shows_which_version_a_share_is_pinned_to(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>',
        ])->assertOk();
        $pinnedVersion = collect($this->versionsOf($author, $diagram))->first();

        Share::factory()->for($diagram)->for($author, 'author')->create([
            'pinned_version_id' => $pinnedVersion['id'],
        ]);
        Share::factory()->for($diagram)->for($author, 'author')->create();

        $response = $this->actingAs($author)->getJson("/diagrams/{$diagram->slug}/shares");

        $response->assertOk();
        $shares = collect($response->json('shares'));

        $pinned = $shares->firstWhere('pinned_version.id', $pinnedVersion['id']);
        $this->assertNotNull($pinned);
        $this->assertSame($pinnedVersion['number'], $pinned['pinned_version']['number']);

        $latest = $shares->firstWhere('pinned_version', null);
        $this->assertNotNull($latest);
    }

    public function test_no_share_api_response_exposes_git_vocabulary(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author);

        $this->actingAs($author)->putJson("/diagrams/{$diagram->slug}/content", [
            'content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">content</bpmn:definitions>',
        ])->assertOk();
        $pinnedVersion = collect($this->versionsOf($author, $diagram))->first();

        $response = $this->actingAs($author)->postJson("/diagrams/{$diagram->slug}/shares", [
            'pinned_version_id' => $pinnedVersion['id'],
        ]);

        $response->assertCreated();

        $body = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('commit', $body);
        $this->assertStringNotContainsStringIgnoringCase('branch', $body);
    }

    public function test_share_page_embeds_diagram_xml_escaped_so_it_cannot_break_out_of_the_script_tag(): void
    {
        $author = User::factory()->create();
        // Well-formed XML (and so accepted at save time) that still carries a
        // `</script>` inside an attribute value.
        $payload = '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" name="</script><script>alert(1)</script>"/>';
        $diagram = $this->createDiagram($author, $payload);
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertDontSee('</script><script>alert(1)', false);
        $response->assertDontSee('<script>alert(1)', false);
        $response->assertSee('\u003C\/script\u003E\u003Cscript\u003Ealert(1)', false);
    }

    public function test_share_page_carries_a_content_security_policy_header(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, Diagram::emptyBpmn());
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $csp = $this->get($share->showUrl())->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_the_content_security_policy_nonce_is_unique_per_request_and_used_by_inline_scripts(): void
    {
        $author = User::factory()->create();
        $diagram = $this->createDiagram($author, Diagram::emptyBpmn());

        $first = $this->actingAs($author)->get("/diagrams/{$diagram->slug}");
        $second = $this->actingAs($author)->get("/diagrams/{$diagram->slug}");

        $nonceOf = fn ($response) => preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;

        $this->assertNotNull($nonceOf($first));
        $this->assertNotSame($nonceOf($first), $nonceOf($second));
        $first->assertSee('<script type="importmap" nonce="'.$nonceOf($first).'">', false);
    }
}

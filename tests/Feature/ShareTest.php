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
        $diagram = $this->createDiagram($author, '<bpmn>content</bpmn>');
        $share = Share::factory()->for($diagram)->for($author, 'author')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get($share->showUrl());

        $response->assertOk();
        $response->assertSee($diagram->title);
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
        $diagram = $this->createDiagram($author, '<bpmn>content-to-download</bpmn>');
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
        $diagram = $this->createDiagram($author, '<bpmn>content</bpmn>');

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
}

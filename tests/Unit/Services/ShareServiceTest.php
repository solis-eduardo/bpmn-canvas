<?php

namespace Tests\Unit\Services;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Repositories\VersionRepository;
use App\Services\ShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShareServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShareService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        $this->service = app(ShareService::class);
    }

    public function test_create_defaults_to_seven_days_without_download(): void
    {
        $this->freezeTime();
        $diagram = Diagram::factory()->create();

        $share = $this->service->create($diagram, $diagram->owner);

        $this->assertSame(now()->addDays(7)->timestamp, $share->expires_at->timestamp);
        $this->assertFalse($share->allow_download);
        $this->assertNull($share->pinned_version_id);
        $this->assertSame($diagram->owner_id, $share->created_by);
    }

    public function test_create_honours_days_and_download(): void
    {
        $this->freezeTime();
        $diagram = Diagram::factory()->create();

        $share = $this->service->create($diagram, $diagram->owner, days: 30, allowDownload: true);

        $this->assertSame(now()->addDays(30)->timestamp, $share->expires_at->timestamp);
        $this->assertTrue($share->allow_download);
    }

    public function test_create_pins_an_existing_version(): void
    {
        $diagram = Diagram::factory()->create();
        $versionId = app(VersionRepository::class)->list($diagram)[0]['id'];

        $share = $this->service->create($diagram, $diagram->owner, pinnedVersionId: $versionId);

        $this->assertSame($versionId, $share->pinned_version_id);
    }

    public function test_create_pins_the_full_id_when_given_an_abbreviation(): void
    {
        $diagram = Diagram::factory()->create();
        $versionId = app(VersionRepository::class)->list($diagram)[0]['id'];

        $share = $this->service->create($diagram, $diagram->owner, pinnedVersionId: substr($versionId, 0, 7));

        $this->assertSame($versionId, $share->pinned_version_id);
    }

    public function test_create_rejects_a_version_the_diagram_does_not_have(): void
    {
        $diagram = Diagram::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->create($diagram, $diagram->owner, pinnedVersionId: 'not-a-version');
    }

    public function test_list_active_leaves_out_revoked_and_expired_shares(): void
    {
        $diagram = Diagram::factory()->create();
        $active = $this->service->create($diagram, $diagram->owner);
        $this->service->revoke($this->service->create($diagram, $diagram->owner));
        Share::factory()->for($diagram)->create(['expires_at' => now()->subDay()]);

        $this->assertSame([$active->id], $this->service->listActive($diagram)->pluck('id')->all());
    }

    public function test_revoke_stamps_the_share(): void
    {
        $diagram = Diagram::factory()->create();
        $share = $this->service->create($diagram, $diagram->owner);

        $this->service->revoke($share);

        $this->assertNotNull($share->fresh()->revoked_at);
    }

    public function test_an_unpinned_share_serves_the_latest_content(): void
    {
        $diagram = Diagram::factory()->create();
        $share = $this->service->create($diagram, $diagram->owner);
        Storage::disk('diagrams')->put($diagram->disk_path, 'latest');

        $this->assertSame('latest', $this->service->contentFor($share));
    }

    public function test_a_pinned_share_serves_that_versions_content(): void
    {
        $author = User::factory()->create();
        $diagram = Diagram::factory()->for($author, 'author')->create();
        $versions = app(VersionRepository::class);
        $pinned = $versions->list($diagram)[0]['id'];
        $original = $versions->get($diagram, $pinned);
        $share = $this->service->create($diagram, $author, pinnedVersionId: $pinned);
        Storage::disk('diagrams')->put($diagram->disk_path, 'newer');
        $versions->save($diagram, $author, 'newer');

        $this->assertSame($original, $this->service->contentFor($share));
    }

    public function test_show_url_is_signed_and_points_at_the_share(): void
    {
        $diagram = Diagram::factory()->create();
        $share = $this->service->create($diagram, $diagram->owner);

        $url = $this->service->showUrlFor($share);

        $this->assertStringContainsString('/s/', $url.'/s/');
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString((string) $share->id, $url);
    }

    public function test_download_url_exists_only_when_downloads_are_allowed(): void
    {
        $diagram = Diagram::factory()->create();
        $closed = $this->service->create($diagram, $diagram->owner);
        $open = $this->service->create($diagram, $diagram->owner, allowDownload: true);

        $this->assertNull($this->service->downloadUrlFor($closed));
        $this->assertStringContainsString('signature=', $this->service->downloadUrlFor($open));
    }
}

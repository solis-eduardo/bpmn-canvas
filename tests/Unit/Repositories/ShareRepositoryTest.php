<?php

namespace Tests\Unit\Repositories;

use App\Models\Diagram;
use App\Models\Share;
use App\Repositories\ShareRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_a_share(): void
    {
        $share = Share::factory()->create();
        $repository = new ShareRepository;

        $this->assertTrue($repository->find($share->id)->is($share));
        $this->assertTrue($repository->findOrFail($share->id)->is($share));
        $this->assertNull($repository->find(0));
    }

    public function test_active_for_excludes_revoked_and_expired_shares_of_the_diagram(): void
    {
        $diagram = Diagram::factory()->create();
        $active = Share::factory()->create(['diagram_id' => $diagram->id]);
        Share::factory()->create(['diagram_id' => $diagram->id, 'revoked_at' => now()]);
        Share::factory()->create(['diagram_id' => $diagram->id, 'expires_at' => now()->subMinute()]);
        Share::factory()->create();

        $shares = (new ShareRepository)->activeFor($diagram);

        $this->assertSame([$active->id], $shares->pluck('id')->all());
    }

    public function test_creates_and_revokes_a_share(): void
    {
        $diagram = Diagram::factory()->create();
        $repository = new ShareRepository;

        $share = $repository->create([
            'diagram_id' => $diagram->id,
            'expires_at' => now()->addDay(),
            'created_by' => $diagram->owner_id,
        ]);
        $this->assertTrue($share->isValid());

        $revoked = $repository->revoke($share);

        $this->assertNotNull($revoked->fresh()->revoked_at);
        $this->assertCount(0, $repository->activeFor($diagram));
    }
}

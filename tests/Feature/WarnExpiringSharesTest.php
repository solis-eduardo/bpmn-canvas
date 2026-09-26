<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Notifications\ShareExpiringSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Console-seam tests (issue #21): exercise `shares:warn-expiring` through
 * the Artisan command (the public interface the scheduler actually uses)
 * and assert on the notifications it creates, using `travelTo` instead of
 * `sleep` to move a Share into and past its warning window.
 */
class WarnExpiringSharesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_warns_the_diagram_owner_once_a_share_enters_the_configured_lead_time(): void
    {
        config(['shares.expiry_warning_lead_days' => 2]);

        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();
        $share = Share::factory()->for($diagram)->for($owner, 'author')->create([
            'expires_at' => now()->addDays(3),
        ]);

        // Outside the 2-day lead time yet: no warning.
        $this->artisan('shares:warn-expiring')->assertSuccessful();
        $this->assertCount(0, $owner->fresh()->notifications);
        $this->assertNull($share->fresh()->expiry_warned_at);

        // Travel to inside the lead time (expires in a bit over a day).
        $this->travelTo(now()->addDays(2));
        $this->artisan('shares:warn-expiring')->assertSuccessful();

        $owner->refresh();
        $this->assertCount(1, $owner->notifications);

        $notification = $owner->notifications->first();
        $this->assertSame(ShareExpiringSoon::class, $notification->type);
        $this->assertSame($share->id, $notification->data['share_id']);
        $this->assertSame($diagram->id, $notification->data['diagram_id']);
        $this->assertNotNull($share->fresh()->expiry_warned_at);
    }

    public function test_the_same_share_is_never_warned_about_twice(): void
    {
        config(['shares.expiry_warning_lead_days' => 2]);

        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();
        Share::factory()->for($diagram)->for($owner, 'author')->create([
            'expires_at' => now()->addDays(1),
        ]);

        $this->artisan('shares:warn-expiring')->assertSuccessful();
        $this->assertCount(1, $owner->fresh()->notifications);

        // Run again without the Share's state changing: still only 1.
        $this->artisan('shares:warn-expiring')->assertSuccessful();
        $this->assertCount(1, $owner->fresh()->notifications);

        // Even after travelling further into (and past) the lead time.
        $this->travelTo(now()->addHours(12));
        $this->artisan('shares:warn-expiring')->assertSuccessful();
        $this->assertCount(1, $owner->fresh()->notifications);
    }

    public function test_a_revoked_share_is_never_warned_about(): void
    {
        config(['shares.expiry_warning_lead_days' => 2]);

        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();
        $share = Share::factory()->for($diagram)->for($owner, 'author')->create([
            'expires_at' => now()->addDays(1),
            'revoked_at' => now(),
        ]);

        $this->artisan('shares:warn-expiring')->assertSuccessful();

        $this->assertCount(0, $owner->fresh()->notifications);
        $this->assertNull($share->fresh()->expiry_warned_at);
    }

    public function test_an_already_expired_share_is_never_warned_about(): void
    {
        config(['shares.expiry_warning_lead_days' => 2]);

        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();

        // Create a Share that expires shortly in the future, then travel
        // past its expiry so it's expired (not just inside the lead time)
        // by the time the command runs.
        $share = Share::factory()->for($diagram)->for($owner, 'author')->create([
            'expires_at' => now()->addHour(),
        ]);

        $this->travelTo(now()->addHours(2));

        $this->artisan('shares:warn-expiring')->assertSuccessful();

        $this->assertCount(0, $owner->fresh()->notifications);
        $this->assertNull($share->fresh()->expiry_warned_at);
    }

    public function test_a_share_outside_the_lead_time_is_not_warned_about(): void
    {
        config(['shares.expiry_warning_lead_days' => 2]);

        $owner = User::factory()->create();
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();
        Share::factory()->for($diagram)->for($owner, 'author')->create([
            'expires_at' => now()->addDays(10),
        ]);

        $this->artisan('shares:warn-expiring')->assertSuccessful();

        $this->assertCount(0, $owner->fresh()->notifications);
    }
}

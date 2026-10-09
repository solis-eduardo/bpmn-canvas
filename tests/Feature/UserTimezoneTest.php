<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Notifications\ShareExpiringSoon;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_set_and_clear_their_timezone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account', ['name' => $user->name, 'timezone' => 'America/Sao_Paulo'])
            ->assertSessionHasNoErrors();
        $this->assertSame('America/Sao_Paulo', $user->fresh()->timezone);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('<option value="America/Sao_Paulo" selected>', false);

        $this->actingAs($user)->put('/account', ['name' => $user->name, 'timezone' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->timezone);
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);

        $this->actingAs($user)->put('/account', ['name' => $user->name, 'timezone' => 'Mars/Olympus'])
            ->assertSessionHasErrors('timezone');
        $this->assertSame('Europe/Paris', $user->fresh()->timezone);
    }

    public function test_a_form_without_a_timezone_field_leaves_it_untouched(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);

        $this->actingAs($user)->put('/account', ['name' => 'New Name'])->assertSessionHasNoErrors();

        $this->assertSame('Europe/Paris', $user->fresh()->timezone);
    }

    public function test_the_browser_timezone_is_stored_on_first_access_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home')->assertSee('data-detect-timezone', false);

        $this->actingAs($user)->putJson('/account/timezone', ['timezone' => 'Asia/Tokyo'])->assertNoContent();
        $this->assertSame('Asia/Tokyo', $user->fresh()->timezone);

        $this->actingAs($user)->putJson('/account/timezone', ['timezone' => 'Europe/Paris'])->assertNoContent();
        $this->assertSame('Asia/Tokyo', $user->fresh()->timezone);

        $this->actingAs($user)->get('/home')->assertDontSee('data-detect-timezone', false);
    }

    public function test_detecting_an_invalid_timezone_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/account/timezone', ['timezone' => 'nope'])->assertUnprocessable();
        $this->assertNull($user->fresh()->timezone);
    }

    public function test_the_timestamp_component_renders_in_the_users_timezone(): void
    {
        $at = now()->setDate(2026, 1, 15)->setTime(15, 0);

        $this->actingAs(User::factory()->create(['timezone' => 'America/Sao_Paulo']));
        $html = Blade::render('<x-timestamp :at="$at" />', ['at' => $at]);

        $this->assertStringContainsString('12:00', $html);
        $this->assertStringContainsString('datetime="2026-01-15T15:00:00.000000Z"', $html);
    }

    public function test_the_timestamp_component_falls_back_to_utc(): void
    {
        $at = now()->setDate(2026, 1, 15)->setTime(15, 0);

        $this->actingAs(User::factory()->create());
        $this->assertStringContainsString('15:00', Blade::render('<x-timestamp :at="$at" />', ['at' => $at]));

        auth()->logout();
        $this->assertStringContainsString('15:00', Blade::render('<x-timestamp :at="$at" />', ['at' => $at]));
    }

    public function test_the_share_expiry_email_uses_the_recipients_timezone(): void
    {
        Storage::fake('diagrams');
        Carbon::setLocale('en');

        $owner = User::factory()->create(['timezone' => 'Pacific/Auckland']);
        $diagram = Diagram::factory()->for($owner, 'owner')->for($owner, 'author')->create();
        // 23:00 UTC on 31 Dec is already 1 Jan in Auckland (UTC+13).
        $share = Share::factory()->for($diagram)->for($owner, 'author')
            ->create(['expires_at' => now()->setDate(2026, 12, 31)->setTime(23, 0)]);

        $lines = (new ShareExpiringSoon($share))->toMail($owner)->introLines;
        $this->assertStringContainsString('Jan 1, 2027', $lines[0]);

        $owner->timezone = null;
        $lines = (new ShareExpiringSoon($share))->toMail($owner)->introLines;
        $this->assertStringContainsString('Dec 31, 2026', $lines[0]);
    }
}

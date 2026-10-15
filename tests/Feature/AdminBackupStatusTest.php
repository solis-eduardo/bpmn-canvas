<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminBackupStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_see_the_last_successful_backup_and_verification(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'timezone' => 'UTC']);
        Carbon::setTestNow('2026-03-05 02:15:00');
        BackupRun::record(BackupRun::KIND_BACKUP, true);
        Carbon::setTestNow('2026-03-05 04:15:00');
        BackupRun::record(BackupRun::KIND_VERIFICATION, true);
        BackupRun::record(BackupRun::KIND_VERIFICATION, false, message: 'git fsck failed for purchase-process');

        $this->actingAs($admin)->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('03/05/2026 2:15 AM')
            ->assertSee('03/05/2026 4:15 AM')
            ->assertSee('git fsck failed for purchase-process');
    }

    public function test_it_says_so_when_nothing_has_run_yet(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee(__('messages.backup_never'));
    }

    public function test_the_off_site_row_only_shows_when_enabled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        config(['backup.offsite.enabled' => false]);
        $this->actingAs($admin)->get(route('admin.backups.index'))->assertDontSee(__('messages.backup_last_offsite'));

        config(['backup.offsite.enabled' => true]);
        $this->actingAs($admin)->get(route('admin.backups.index'))->assertSee(__('messages.backup_last_offsite'));
    }

    public function test_other_users_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.backups.index'))->assertForbidden();
    }
}

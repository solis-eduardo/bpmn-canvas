<?php

namespace App\Console\Commands;

use App\Models\Share;
use App\Notifications\ShareExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Warns each Diagram Owner about their active Shares that are about to
 * expire (issue #21), so they can renew the link before recipients lose
 * access. Scheduled periodically from the `scheduler` compose service (see
 * bootstrap/app.php's `withSchedule`), same as `backup:run`.
 *
 * The lead time is configurable (config/shares.php, default 2 days). A
 * Share is only ever warned about once: `expiry_warned_at` (set here) is
 * the idempotence guard, and revoked or already-expired Shares are never
 * candidates (Share::isDueForExpiryWarning() — the same rule isValid()
 * uses).
 */
class WarnExpiringShares extends Command
{
    protected $signature = 'shares:warn-expiring';

    protected $description = 'Notify each Diagram Owner about their Shares expiring within the configured lead time';

    public function handle(): int
    {
        $leadTimeCutoff = Carbon::now()->addDays((int) config('shares.expiry_warning_lead_days'));

        $candidates = Share::query()
            ->whereNull('revoked_at')
            ->whereNull('expiry_warned_at')
            ->where('expires_at', '>', Carbon::now())
            ->where('expires_at', '<=', $leadTimeCutoff)
            ->with('diagram.owner')
            ->get();

        $warned = 0;

        foreach ($candidates as $share) {
            if (! $share->isDueForExpiryWarning($leadTimeCutoff)) {
                continue;
            }

            $share->diagram->owner->notify(new ShareExpiringSoon($share));
            $share->update(['expiry_warned_at' => Carbon::now()]);

            $warned++;
            $this->info("Warned Share #{$share->id} (Diagram '{$share->diagram->slug}') expiring at {$share->expires_at}.");
        }

        if ($warned === 0) {
            $this->info('No Shares due for an expiry warning.');
        }

        return self::SUCCESS;
    }
}

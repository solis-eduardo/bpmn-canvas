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
 * candidates. The one rule for "is this Share due for a warning" lives in
 * Share::isDueForExpiryWarning() — the query below only narrows on the
 * cheap, indexed nullity columns to keep the row set small, it isn't a
 * second copy of that rule.
 */
class WarnExpiringShares extends Command
{
    protected $signature = 'shares:warn-expiring';

    protected $description = 'Notify each Diagram Owner about their Shares expiring within the configured lead time';

    public function handle(): int
    {
        $now = Carbon::now();
        $leadTimeCutoff = $now->clone()->addDays(config('shares.expiry_warning_lead_days'));

        $candidates = Share::query()
            ->active()
            ->whereNull('expiry_warned_at')
            ->where('expires_at', '<=', $leadTimeCutoff)
            ->with('diagram.owner')
            ->get();

        $warnedIds = [];

        foreach ($candidates as $share) {
            try {
                $share->diagram->owner->notify(new ShareExpiringSoon($share));

                $warnedIds[] = $share->id;
                $this->info("Warned Share #{$share->id} (Diagram '{$share->diagram->slug}') expiring at {$share->expires_at}.");
            } catch (\Throwable $e) {
                // One bad Share (e.g. a lookup failure while resolving its
                // Diagram/Owner) shouldn't abort the whole run and leave
                // every other candidate unprocessed — it's simply retried
                // on the next scheduled run, since expiry_warned_at was
                // never set for it.
                $this->error("Failed to warn about Share #{$share->id}: {$e->getMessage()}");
            }
        }

        if ($warnedIds === []) {
            $this->info('No Shares due for an expiry warning.');

            return self::SUCCESS;
        }

        // One UPDATE for every Share warned this run instead of one
        // per Share.
        Share::whereIn('id', $warnedIds)->update(['expiry_warned_at' => $now]);

        return self::SUCCESS;
    }
}

<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * What the activity log was narrowed down to: one `action`, a substring of the
 * actor's or Diagram's name, and an inclusive `from`/`to` date range. Filters
 * combine with AND. Unknown actions and unparsable dates are ignored.
 */
final readonly class ActivityLogFilters
{
    public function __construct(
        public ?string $action = null,
        public string $actor = '',
        public string $diagram = '',
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $action = $request->query('action');

        return new self(
            action: is_string($action) && in_array($action, ActivityLog::ACTIONS, true) ? $action : null,
            actor: trim((string) $request->query('actor', '')),
            diagram: trim((string) $request->query('diagram', '')),
            from: self::date($request->query('from'))?->startOfDay()->utc(),
            to: self::date($request->query('to'))?->endOfDay()->utc(),
        );
    }

    public function isActive(): bool
    {
        return $this->action !== null || $this->actor !== '' || $this->diagram !== '' || $this->from !== null || $this->to !== null;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, auth()->user()?->displayTimezone() ?? config('app.timezone')) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}

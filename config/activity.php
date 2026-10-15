<?php

return [
    // Days an activity log entry is kept before `activity:prune` removes it
    // (CONTEXT.md, "Activity Log"; ADR-0025). 0 keeps entries forever.
    'retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 0),

    // Entries per page on the Diagram's Activity tab and the global log.
    'per_page' => 25,
];

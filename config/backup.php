<?php

return [
    // How many days a backup artifact is kept before being pruned by the
    // next `backup:run`. Artifacts older than this are deleted.
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
];

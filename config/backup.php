<?php

return [
    // How many days a backup artifact is kept before being pruned by the
    // next `backup:run`. Artifacts older than this are deleted.
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    // Optional second copy of every artifact on an off-site disk, so losing
    // the host or its volume does not take the backups with it (ADR-0026).
    // `disk` names a disk from config/filesystems.php, by default the
    // S3-compatible `backups_offsite`. It has its own, normally longer,
    // retention.
    'offsite' => [
        'enabled' => (bool) env('BACKUP_OFFSITE_ENABLED', false),
        'disk' => env('BACKUP_OFFSITE_DISK', 'backups_offsite'),
        'retention_days' => (int) env('BACKUP_OFFSITE_RETENTION_DAYS', 60),
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'diagrams' => [
            'driver' => 'local',
            'root' => storage_path('app/diagrams'),
            // Flysystem's default makes directories 0700, which locks the
            // host user out of the bind-mounted ./diagrams volume (the files
            // are owned by the container's www-data uid). 0755 keeps them
            // readable for host tooling while only www-data can write.
            'permissions' => [
                'dir' => ['public' => 0755, 'private' => 0755],
                'file' => ['public' => 0644, 'private' => 0644],
            ],
            'throw' => false,
            'report' => false,
        ],

        // Destination for backup artifacts produced by `backup:run` (see
        // config/backup.php). Defaults to a directory inside the app's own
        // storage, but is normally overridden to a path backed by a mounted
        // host volume (see docker-compose.yml's `scheduler` service) so an
        // artifact survives losing the container.
        'backups' => [
            'driver' => 'local',
            'root' => env('BACKUP_DESTINATION', storage_path('app/backup')),
            'throw' => false,
            'report' => false,
        ],

        // S3-compatible off-site destination for backup artifacts (see
        // config/backup.php, ADR-0026). Separate credentials from the
        // generic `s3` disk below so a backup bucket never shares them.
        'backups_offsite' => [
            'driver' => 's3',
            'key' => env('BACKUP_OFFSITE_KEY'),
            'secret' => env('BACKUP_OFFSITE_SECRET'),
            'region' => env('BACKUP_OFFSITE_REGION', 'us-east-1'),
            'bucket' => env('BACKUP_OFFSITE_BUCKET'),
            'endpoint' => env('BACKUP_OFFSITE_ENDPOINT'),
            'use_path_style_endpoint' => env('BACKUP_OFFSITE_PATH_STYLE', false),
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

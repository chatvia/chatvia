<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The `private` disk holds every file that is not public: knowledge files,
    | chat attachments, images from knowledge files and generated media. Its
    | files are opened only through signed links.
    |
    */

    'default' => 'private',

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    |
    | Where the app keeps its private files: "local" (this server) or "s3"
    | (the `private` bucket below, at an S3-compatible service such as AWS
    | S3, Scaleway, Cloudflare R2 or MinIO). The public images and the
    | backups go to the `public` (served from its `url`) and `backup` buckets
    | when they are named, else to this server. Each bucket has its own
    | connection. All are set in the admin panel (Settings → Storage).
    | Switching moves no files.
    |
    | Supported: "local", "s3"
    |
    */

    'storage' => 'local',

    'buckets' => [
        'private' => [
            'name' => null,
            'endpoint' => null,
            'region' => null,
            'key' => null,
            'secret' => null,
            'path_style' => false,
        ],
        'public' => [
            'name' => null,
            'url' => null,
            'endpoint' => null,
            'region' => null,
            'key' => null,
            'secret' => null,
            'path_style' => false,
        ],
        'backup' => [
            'name' => null,
            'endpoint' => null,
            'region' => null,
            'key' => null,
            'secret' => null,
            'path_style' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | The disks the app writes to. `private`, `assets` (profile photos, agent
    | logos) and `backups` stay on this server until their bucket is used
    | (see above); App\Admin\Settings\StorageDisks then builds each from the
    | `s3` disk and its bucket's connection. `local` keeps data exports.
    |
    | Supported drivers: "local", "s3"
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

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'url' => env('APP_URL').'/private-storage',
            'serve' => true,
            'throw' => true,
            'report' => true,
        ],

        // The base of every bucket's disk; the bucket and its connection
        // come from `buckets` above.
        's3' => [
            'driver' => 's3',
            'throw' => true,
            'report' => true,
        ],

        'assets' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'throw' => true,
            'report' => true,
            'visibility' => 'public',
        ],

        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => true,
            'report' => true,
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
    | The link serves the `assets` disk while it is on this server.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

<?php

declare(strict_types=1);

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The `private` disk holds every file that is not public: knowledge files,
    | chat attachments, images from knowledge files and backups. Its files
    | are opened only through signed links.
    |
    */

    'default' => 'private',

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    |
    | Where the `private` disk keeps its files: "local" (this server) or "s3"
    | (the S3-compatible service of the `s3` disk below, such as AWS S3,
    | Scaleway, Cloudflare R2 or MinIO). Both are set in the admin panel
    | (Settings → Storage). Switching moves no files.
    |
    | Supported: "local", "s3"
    |
    */

    'storage' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | The disks the app writes to. `private` stays on this server until the
    | storage above is "s3"; App\Admin\Settings\StorageDisks then gives it the
    | `s3` disk's settings. `assets` (profile photos, widget logos) uses Bunny
    | CDN when BUNNYCDN_STORAGE_ZONE is set, and this server otherwise.
    | `local` keeps data exports.
    |
    | Supported drivers: "local", "s3", "bunnycdn"
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

        's3' => [
            'driver' => 's3',
            'key' => null,
            'secret' => null,
            'region' => null,
            'bucket' => null,
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => true,
        ],

        'assets' => env('FILESYSTEM_ASSETS_DRIVER', filled(env('BUNNYCDN_STORAGE_ZONE')) ? 'bunnycdn' : 'local') === 'bunnycdn' ? [
            'driver' => 'bunnycdn',
            'storage_zone' => env('BUNNYCDN_STORAGE_ZONE'),
            'pull_zone' => env('BUNNYCDN_PULL_ZONE'),
            'api_key' => env('BUNNYCDN_API_KEY'),
            'region' => env('BUNNYCDN_REGION', BunnyCDNRegion::DEFAULT),
            'throw' => true,
            'report' => true,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'throw' => true,
            'report' => true,
            'visibility' => 'public',
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

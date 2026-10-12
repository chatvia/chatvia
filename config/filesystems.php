<?php

declare(strict_types=1);

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

/*
| The app writes to three named disks: `private` (knowledge files, backups),
| `public` (chat attachments, chunk images) and `assets` (profile photos,
| widget logos). `private` and `public` are on this server unless
| S3-compatible storage is chosen in the admin panel (Settings → Storage; see
| chatvia.storage). `assets` uses Bunny CDN when BUNNYCDN_STORAGE_ZONE is set
| (or FILESYSTEM_ASSETS_DRIVER says so), and the local disk otherwise. A fresh
| install works without any cloud account.
*/

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

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

        // The local disks. When S3-compatible storage is chosen (Settings →
        // Storage, or SCALEWAY_* in .env), App\Admin\Settings\StorageDisks
        // replaces these two with that service's disks (chatvia.storage).
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'url' => $appUrl.'/private-storage',
            'serve' => true,
            'throw' => true,
            'report' => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => $appUrl.'/storage',
            'throw' => true,
            'report' => true,
            'visibility' => 'public',
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
            'url' => $appUrl.'/storage',
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
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

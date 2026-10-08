<?php

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

/*
| The app writes to three named disks: `private` (knowledge files, backups),
| `public` (chat attachments, chunk images) and `assets` (profile photos,
| widget logos). Each one uses object storage when its bucket or zone is
| configured, and the local disk otherwise, so a fresh install works without
| any cloud account. Set FILESYSTEM_PRIVATE_DRIVER / FILESYSTEM_PUBLIC_DRIVER /
| FILESYSTEM_ASSETS_DRIVER to choose explicitly.
*/

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

$s3Configured = filled(env('SCALEWAY_BUCKET'));

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

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
        ],

        'private' => env('FILESYSTEM_PRIVATE_DRIVER', $s3Configured ? 's3' : 'local') === 's3' ? [
            'driver' => 's3',
            'key' => env('SCALEWAY_ACCESS_KEY_ID'),
            'secret' => env('SCALEWAY_SECRET_ACCESS_KEY'),
            'region' => env('SCALEWAY_DEFAULT_REGION'),
            'bucket' => env('SCALEWAY_BUCKET'),
            'url' => env('SCALEWAY_URL'),
            'endpoint' => env('SCALEWAY_ENDPOINT'),
            'use_path_style_endpoint' => env('SCALEWAY_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
        ] : [
            // Same folder as the `local` disk. Served through signed URLs only
            // (temporaryUrl), on its own path so it doesn't clash with `local`.
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'url' => $appUrl.'/private-storage',
            'serve' => true,
            'throw' => true,
            'report' => true,
        ],

        'public' => env('FILESYSTEM_PUBLIC_DRIVER', $s3Configured ? 's3' : 'local') === 's3' ? [
            'driver' => 's3',
            'key' => env('SCALEWAY_ACCESS_KEY_ID'),
            'secret' => env('SCALEWAY_SECRET_ACCESS_KEY'),
            'region' => env('SCALEWAY_DEFAULT_REGION'),
            'bucket' => env('SCALEWAY_BUCKET_PUBLIC'),
            // Separate URL env so `Storage::disk('public')->url(...)` returns
            // the public bucket's host (not the `private` disk's host, which
            // would 403 for external fetchers like Mistral).
            'url' => env('SCALEWAY_PUBLIC_URL'),
            'endpoint' => env('SCALEWAY_ENDPOINT'),
            'use_path_style_endpoint' => env('SCALEWAY_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
            'visibility' => 'public',
        ] : [
            // storage/app/public, reachable at /storage once `php artisan
            // storage:link` has created the public/storage symlink (see links).
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => $appUrl.'/storage',
            'throw' => true,
            'report' => true,
            'visibility' => 'public',
        ],

        'bunnycdn' => [
            'driver' => 'bunnycdn',
            'storage_zone' => env('BUNNYCDN_STORAGE_ZONE'),
            'pull_zone' => env('BUNNYCDN_PULL_ZONE'),
            'api_key' => env('BUNNYCDN_API_KEY'),
            'region' => env('BUNNYCDN_REGION', BunnyCDNRegion::DEFAULT),
            'throw' => true,
            'report' => true,
        ],

        // Public, CDN-served media (profile photos, widget logo/avatar).
        // Application code references this semantic 'assets' disk; the provider
        // is an implementation detail kept out of the code. BunnyCDN when a
        // storage zone is set, otherwise the same local folder as `public`.
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

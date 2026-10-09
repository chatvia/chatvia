<?php

namespace App\Providers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;

/**
 * Registers the `bunnycdn` filesystem driver. Each disk that uses it builds its
 * adapter from its own config, so several disks (for example the public
 * `assets` disk) can point at their own storage and pull zones.
 */
class FilesystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('bunnycdn', function ($app, array $config): FilesystemAdapter {
            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region'],
                ),
                $config['pull_zone'],
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }
}

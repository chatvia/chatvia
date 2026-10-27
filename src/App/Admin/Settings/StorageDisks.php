<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Builds the app's disks from `filesystems.storage` and `filesystems.buckets`
 * (set in Settings → Storage). With S3-compatible storage, each disk is the
 * `s3` disk with a bucket and that bucket's own connection: `private` the
 * private bucket, `assets` the public bucket (when one is set, else this
 * server) and `backups` the backup bucket (else the private one); otherwise
 * all three are the local disks of config/filesystems.php. A disk's config is a
 * whole array whose shape depends on its driver, so it is replaced as one,
 * and a disk a long-running process already opened is dropped so the next
 * use opens it again. {@see SettingsConfig} runs this after every apply.
 */
final class StorageDisks
{
    /** The disks that follow the storage setting. */
    public const array DISKS = ['private', 'assets', 'backups'];

    /** @var array<string, array<string, mixed>>|null The local disks of config/filesystems.php. */
    private ?array $local = null;

    private ?string $appliedHash = null;

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function apply(): void
    {
        // Read the local disks before the first replace: a process that
        // starts on S3 and is switched to local later must get them back.
        $this->localDisks();

        $disks = $this->disks();
        $hash = md5(serialize($disks));

        if ($hash === $this->appliedHash) {
            return;
        }

        foreach ($disks as $name => $disk) {
            $this->config->set("filesystems.disks.{$name}", $disk);
        }

        if ($this->container->resolved('filesystem')) {
            $filesystem = $this->container->make('filesystem');

            if ($filesystem instanceof FilesystemManager) {
                $filesystem->forgetDisk(self::DISKS);
            }
        }

        $this->appliedHash = $hash;
    }

    /**
     * The config of each disk for the storage in use.
     *
     * @return array<string, array<string, mixed>>
     */
    public function disks(): array
    {
        $local = $this->localDisks();

        if ($this->config->get('filesystems.storage') !== 's3') {
            return $local;
        }

        $private = $this->bucketDisk('private');

        return [
            'private' => $private,
            // No visibility: AWS blocks object ACLs by default and R2 has
            // none, so the bucket's own public access serves the images.
            'assets' => filled($this->config->get('filesystems.buckets.public.name'))
                ? [...$this->bucketDisk('public'), 'url' => $this->config->get('filesystems.buckets.public.url')]
                : $local['assets'],
            'backups' => filled($this->config->get('filesystems.buckets.backup.name')) ? $this->bucketDisk('backup') : $private,
        ];
    }

    /**
     * The `s3` disk with one bucket's name and connection.
     *
     * @return array<string, mixed>
     */
    private function bucketDisk(string $bucket): array
    {
        $config = (array) $this->config->get("filesystems.buckets.{$bucket}");

        return [
            ...(array) $this->config->get('filesystems.disks.s3'),
            'bucket' => $config['name'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'region' => $config['region'] ?? null,
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
            'use_path_style_endpoint' => (bool) ($config['path_style'] ?? false),
        ];
    }

    /**
     * The local disks of config/filesystems.php, read before this class
     * first replaces them.
     *
     * @return array<string, array<string, mixed>>
     */
    private function localDisks(): array
    {
        return $this->local ??= array_combine(self::DISKS, array_map(
            fn (string $disk): array => (array) $this->config->get("filesystems.disks.{$disk}"),
            self::DISKS,
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Builds the `private` and `public` disks from `chatvia.storage` (set in
 * Settings → Storage): the S3-compatible service when it is chosen, else the
 * local disks of config/filesystems.php. A disk's config is a whole array
 * whose shape depends on its driver, so it is replaced as one, and a disk a
 * long-running process already opened is dropped so the next use opens it
 * again. {@see SettingsConfig} runs this after every apply.
 */
final class StorageDisks
{
    /** The disks that follow the storage setting. */
    public const array DISKS = ['private', 'public'];

    /** @var array<string, array<string, mixed>>|null The local disks of config/filesystems.php. */
    private ?array $local = null;

    private ?string $appliedHash = null;

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function apply(): void
    {
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
        if ($this->config->get('chatvia.storage.driver') !== 's3') {
            return $this->localDisks();
        }

        /** @var array<string, mixed> $s3 */
        $s3 = (array) $this->config->get('chatvia.storage.s3');

        $service = [
            'driver' => 's3',
            'key' => $s3['key'] ?? null,
            'secret' => $s3['secret'] ?? null,
            'region' => $s3['region'] ?? null,
            'endpoint' => $s3['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($s3['use_path_style_endpoint'] ?? false),
            'throw' => true,
            'report' => true,
        ];

        return [
            'private' => $service + ['bucket' => $s3['private_bucket'] ?? null, 'url' => $s3['private_url'] ?? null],
            'public' => $service + ['bucket' => $s3['public_bucket'] ?? null, 'url' => $s3['public_url'] ?? null, 'visibility' => 'public'],
        ];
    }

    /**
     * The local disks of config/filesystems.php, read before this class first
     * replaces them.
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

<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Builds the `private` disk from `filesystems.storage` (set in Settings →
 * Storage): the `s3` disk when S3-compatible storage is chosen, else the
 * local disk of config/filesystems.php. A disk's config is a whole array
 * whose shape depends on its driver, so it is replaced as one, and a disk a
 * long-running process already opened is dropped so the next use opens it
 * again. {@see SettingsConfig} runs this after every apply.
 */
final class StorageDisks
{
    /** The disks that follow the storage setting. */
    public const array DISKS = ['private'];

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
        if ($this->config->get('filesystems.storage') !== 's3') {
            return $this->localDisks();
        }

        return ['private' => (array) $this->config->get('filesystems.disks.s3')];
    }

    /**
     * The local disk of config/filesystems.php, read before this class first
     * replaces it.
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

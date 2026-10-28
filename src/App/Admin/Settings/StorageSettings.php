<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Where the app keeps its files: on this server, or in S3-compatible object
 * storage with up to three buckets (private files, public images, backups),
 * each with its own address and keys, so each may sit with another service
 * or account. They fill `filesystems.storage` and `filesystems.buckets`;
 * {@see StorageDisks} then builds the `private`, `assets` and `backups`
 * disks from them.
 */
class StorageSettings extends AdminSettings
{
    #[ConfigKey('filesystems.storage')]
    public ?string $driver = null;

    #[ConfigKey('filesystems.buckets.private.name')]
    public ?string $s3_private_bucket = null;

    #[ConfigKey('filesystems.buckets.private.endpoint')]
    public ?string $s3_private_endpoint = null;

    #[ConfigKey('filesystems.buckets.private.region')]
    public ?string $s3_private_region = null;

    #[ConfigKey('filesystems.buckets.private.key')]
    public ?string $s3_private_key = null;

    #[ConfigKey('filesystems.buckets.private.secret'), ShouldBeEncrypted]
    public ?string $s3_private_secret = null;

    #[ConfigKey('filesystems.buckets.private.path_style')]
    public ?bool $s3_private_path_style = null;

    #[ConfigKey('filesystems.buckets.public.name')]
    public ?string $s3_public_bucket = null;

    #[ConfigKey('filesystems.buckets.public.url')]
    public ?string $s3_public_url = null;

    #[ConfigKey('filesystems.buckets.public.endpoint')]
    public ?string $s3_public_endpoint = null;

    #[ConfigKey('filesystems.buckets.public.region')]
    public ?string $s3_public_region = null;

    #[ConfigKey('filesystems.buckets.public.key')]
    public ?string $s3_public_key = null;

    #[ConfigKey('filesystems.buckets.public.secret'), ShouldBeEncrypted]
    public ?string $s3_public_secret = null;

    #[ConfigKey('filesystems.buckets.public.path_style')]
    public ?bool $s3_public_path_style = null;

    #[ConfigKey('filesystems.buckets.backup.name')]
    public ?string $s3_backup_bucket = null;

    #[ConfigKey('filesystems.buckets.backup.endpoint')]
    public ?string $s3_backup_endpoint = null;

    #[ConfigKey('filesystems.buckets.backup.region')]
    public ?string $s3_backup_region = null;

    #[ConfigKey('filesystems.buckets.backup.key')]
    public ?string $s3_backup_key = null;

    #[ConfigKey('filesystems.buckets.backup.secret'), ShouldBeEncrypted]
    public ?string $s3_backup_secret = null;

    #[ConfigKey('filesystems.buckets.backup.path_style')]
    public ?bool $s3_backup_path_style = null;

    public static function group(): string
    {
        return 'storage';
    }

    /**
     * Whether the backups go to the private files' bucket: the backups
     * bucket was saved as a copy of the private one, the private files
     * being in a bucket.
     */
    public function backupsInPrivateBucket(): bool
    {
        return $this->driver === 's3'
            && filled($this->s3_backup_bucket)
            && collect(['bucket', 'endpoint', 'region', 'key'])
                ->every(fn (string $field): bool => $this->{"s3_backup_{$field}"} === $this->{"s3_private_{$field}"});
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Where the app keeps its files: on this server, or in S3-compatible object
 * storage, with that service's address, keys and bucket. They fill
 * `filesystems.storage` and the `s3` disk; {@see StorageDisks} then builds
 * the `private` disk from them.
 */
class StorageSettings extends AdminSettings
{
    #[ConfigKey('filesystems.storage')]
    public ?string $driver = null;

    #[ConfigKey('filesystems.disks.s3.endpoint')]
    public ?string $s3_endpoint = null;

    #[ConfigKey('filesystems.disks.s3.region')]
    public ?string $s3_region = null;

    #[ConfigKey('filesystems.disks.s3.key')]
    public ?string $s3_key = null;

    #[ConfigKey('filesystems.disks.s3.secret'), ShouldBeEncrypted]
    public ?string $s3_secret = null;

    #[ConfigKey('filesystems.disks.s3.use_path_style_endpoint')]
    public ?bool $s3_path_style = null;

    #[ConfigKey('filesystems.disks.s3.bucket')]
    public ?string $s3_bucket = null;

    public static function group(): string
    {
        return 'storage';
    }
}

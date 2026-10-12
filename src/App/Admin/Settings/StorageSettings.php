<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Where the app keeps its files: on this server, or in S3-compatible object
 * storage, with that service's address, keys and the bucket of the private
 * files (knowledge files, backups) and of the public ones (chat attachments,
 * chunk images). {@see StorageDisks} builds the disks from `chatvia.storage`.
 */
class StorageSettings extends AdminSettings
{
    #[ConfigKey('chatvia.storage.driver')]
    public ?string $driver = null;

    #[ConfigKey('chatvia.storage.s3.endpoint')]
    public ?string $s3_endpoint = null;

    #[ConfigKey('chatvia.storage.s3.region')]
    public ?string $s3_region = null;

    #[ConfigKey('chatvia.storage.s3.key')]
    public ?string $s3_key = null;

    #[ConfigKey('chatvia.storage.s3.secret'), ShouldBeEncrypted]
    public ?string $s3_secret = null;

    #[ConfigKey('chatvia.storage.s3.use_path_style_endpoint')]
    public ?bool $s3_path_style = null;

    #[ConfigKey('chatvia.storage.s3.private_bucket')]
    public ?string $private_bucket = null;

    #[ConfigKey('chatvia.storage.s3.public_bucket')]
    public ?string $public_bucket = null;

    #[ConfigKey('chatvia.storage.s3.public_url')]
    public ?string $public_url = null;

    public static function group(): string
    {
        return 'storage';
    }
}

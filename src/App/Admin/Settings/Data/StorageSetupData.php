<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * Where an install keeps its files, as the admin setup asks for it: the
 * private files, the public images and the backups each on this server or
 * in an S3-compatible bucket with its own connection. The private files use
 * their bucket with the "s3" driver; a missing public or backups bucket
 * keeps those on this server.
 */
final class StorageSetupData extends Data
{
    public function __construct(
        public string $driver = 'local',
        public ?S3BucketData $private = null,
        public ?S3BucketData $public = null,
        public ?S3BucketData $backup = null,
    ) {}

    public function usesS3(): bool
    {
        return $this->driver === 's3';
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * Where an install keeps its files, as the admin setup asks for it: this
 * server, or S3-compatible storage with a private bucket and, optionally, a
 * public bucket and a backups bucket, each with its own connection. A
 * missing public or backups bucket keeps public images on this server and
 * backups in the private bucket.
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

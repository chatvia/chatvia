<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * Where an install keeps its files, as the admin setup asks for it: this
 * server, or an S3-compatible service with its address, keys and bucket.
 * A null secret keeps the saved one.
 */
final class StorageSetupData extends Data
{
    public function __construct(
        public string $driver = 'local',
        public ?string $endpoint = null,
        public ?string $region = null,
        public ?string $key = null,
        public ?string $secret = null,
        public bool $pathStyle = false,
        public ?string $bucket = null,
    ) {}

    public function usesS3(): bool
    {
        return $this->driver === 's3';
    }
}

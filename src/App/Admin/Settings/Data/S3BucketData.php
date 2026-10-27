<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * One bucket of S3-compatible storage with its own connection: the bucket's
 * name, the service's address, region and keys, and for the public bucket
 * the URL that serves its files. A null secret keeps the saved one.
 */
final class S3BucketData extends Data
{
    public function __construct(
        public ?string $bucket = null,
        public ?string $endpoint = null,
        public ?string $region = null,
        public ?string $key = null,
        public ?string $secret = null,
        public bool $pathStyle = false,
        public ?string $url = null,
    ) {}
}

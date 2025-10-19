<?php

namespace App\Data\Crawler;

use Spatie\LaravelData\Data;

class LinkData extends Data
{
    public function __construct(
        public string $link,
        public \DateTimeInterface $crawled_at,
        public string $checksum = '',
    ) {}
}

<?php

namespace App\Data\Crawler;

use Spatie\LaravelData\Data;

class LinkData extends Data
{
    public function __construct(
        public string $link,
        public \DateTimeInterface $crawled_at,
        public ?string $found_on_url = null,
        public ?string $text = null,
        public string $hash = '',
    ) {}
}

<?php

namespace Domain\Analytics\Data;

use Spatie\LaravelData\Data;

class DocumentMetadataData extends Data
{
    public function __construct(
        public string $complexity_level,
        public string $target_audience,
        public string $writing_style,
        public int $estimated_reading_time_minutes,
        /** @var array<string> */
        public array $key_topics,
        public bool $contains_sensitive_info,
    ) {}
}

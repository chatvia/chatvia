<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Topics;

use Spatie\LaravelData\Data;

/**
 * A conversation where a suggested topic came up (public id and title).
 */
class SuggestedTopicExampleData extends Data
{
    public function __construct(
        public string $conversation_id,
        public ?string $title,
    ) {}
}

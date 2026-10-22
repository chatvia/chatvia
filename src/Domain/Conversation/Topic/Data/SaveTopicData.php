<?php

declare(strict_types=1);

namespace Domain\Conversation\Topic\Data;

use Spatie\LaravelData\Data;

/**
 * The name and description of a workspace topic to create or rename, shared
 * by the dashboard and the public API.
 */
class SaveTopicData extends Data
{
    public function __construct(
        public string $topic,
        public string $description,
    ) {}
}

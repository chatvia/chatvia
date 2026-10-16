<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Topic;

use Carbon\CarbonInterface;
use Domain\Conversation\Topic\Models\Topic;
use Spatie\LaravelData\Data;

/**
 * A conversation topic over the public API. System topics are shared by every
 * workspace and read-only; workspace topics can be renamed and detached.
 */
class TopicData extends Data
{
    public function __construct(
        /**
         * The topic's ID.
         */
        public string $id,
        /**
         * The topic's name ("Billing & Payments").
         */
        public string $topic,
        /**
         * What conversations the topic covers — written for the analysis model.
         */
        public string $description,
        /**
         * Whether this is a built-in system topic (read-only).
         */
        public bool $is_system,
        /**
         * When the topic was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(Topic $topic): self
    {
        return new self(
            id: $topic->external_id,
            topic: $topic->topic,
            description: $topic->description,
            is_system: $topic->workspace_id === null,
            created_at: $topic->created_at,
        );
    }
}

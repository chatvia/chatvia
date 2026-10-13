<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Spatie\LaravelData\Data;

/**
 * One piece of knowledge an assistant answer was given, as it is stored with
 * the answer (`chat_message_sources`). Property names are snake_case because
 * it is queued inside StoreMessageJob.
 */
class MessageSourceData extends Data
{
    public function __construct(
        public int $rank,
        public ?int $chunk_id,
        public string $sourceable_type,
        public int $sourceable_id,
        public ?float $score,
        public string $label,
        public ?string $url = null,
        public ?int $page = null,
    ) {}

    public static function fromRetrieved(RetrievedSourceData $source, int $rank): self
    {
        return new self(
            rank: $rank,
            chunk_id: $source->id,
            sourceable_type: $source->sourceable_type,
            sourceable_id: $source->sourceable_id,
            score: $source->relevance,
            label: $source->label,
            url: $source->url,
            page: $source->page,
        );
    }
}

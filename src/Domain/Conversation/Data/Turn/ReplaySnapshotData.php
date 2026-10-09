<?php

namespace Domain\Conversation\Data\Turn;

use Spatie\LaravelData\Data;

/**
 * A finished answer kept for a short while under its request id, so a retry
 * of the same request (a flaky network, a double submit) is replayed instead
 * of paying for a second model call.
 */
class ReplaySnapshotData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $sources  the RAG sources, as the `sources` event sends them
     */
    public function __construct(
        public string $assistant_message_id,
        public string $content,
        public ?string $language = null,
        public array $sources = [],
    ) {}
}

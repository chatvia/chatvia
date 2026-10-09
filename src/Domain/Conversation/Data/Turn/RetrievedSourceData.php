<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Spatie\LaravelData\Data;

/**
 * One knowledge chunk retrieved for a turn, as the hosted chat shows it in
 * the `sources` event and as the reply's source list is cached. The array
 * form is part of the stream and cache contract, so the property names are
 * kept in snake_case.
 */
class RetrievedSourceData extends Data
{
    /**
     * @param  array{filename: string, document_id: int, chunkable_type: string, chunkable_id: int}  $metadata
     * @param  array<string, string>  $images  the chunk's described images: storage path => description
     */
    public function __construct(
        public int $id,
        public ?string $external_id,
        public string $content,
        public ?float $confidence_score,
        public array $metadata,
        public array $images = [],
    ) {}
}

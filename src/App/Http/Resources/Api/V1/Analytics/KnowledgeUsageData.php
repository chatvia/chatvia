<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Analytics;

use Domain\Analytics\Data\Agent\Usage\SourceUsageData;
use Spatie\LaravelData\Data;

/**
 * One knowledge source the agent retrieved, with its retrieval count and the
 * last day it was used. The name is the one recorded at retrieval time, so a
 * deleted source still shows; a row recorded without one shows null.
 */
class KnowledgeUsageData extends Data
{
    public function __construct(
        /**
         * The source's name when it was retrieved.
         */
        public ?string $name,
        /**
         * The source's kind (`source_file`, `source_website`, `source_text`…).
         */
        public string $kind,
        /**
         * The file's MIME type, for file sources.
         */
        public ?string $mime_type,
        /**
         * How many answers retrieved this source.
         */
        public int $retrievals,
        /**
         * The last day the source was retrieved (`Y-m-d`).
         */
        public ?string $last_used_on,
    ) {}

    public static function fromUsage(SourceUsageData $source): self
    {
        return new self(
            name: $source->name,
            kind: $source->kind,
            mime_type: $source->mimeType,
            retrievals: $source->retrievals,
            last_used_on: $source->lastUsedOn,
        );
    }
}

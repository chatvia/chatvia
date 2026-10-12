<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Spatie\LaravelData\Data;

/**
 * Pages of a file or text source that one chunk job stores, the agent whose
 * page shows the progress, and the source's progress (0-100) once they are
 * stored; null sends no progress event. `enhancement` overrides the source's
 * own setting (the last-chance fallback stores pages without it).
 */
final class SourceChunkData extends Data
{
    public function __construct(
        public Agent $agent,
        public SourceFile|SourceText $source,
        public PageGroupData $group,
        public ?int $progress = null,
        public ?ChunkEnhancement $enhancement = null,
    ) {}
}

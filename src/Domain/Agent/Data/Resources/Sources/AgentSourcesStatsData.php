<?php

namespace Domain\Agent\Data\Resources\Sources;

use Domain\Agent\Queries\AgentSourceCountsQuery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many trained knowledge sources an agent has, per kind. Built by
 * {@see AgentSourceCountsQuery}.
 */
#[TypeScript]
class AgentSourcesStatsData extends Data
{
    public function __construct(
        public int $websites,
        public int $texts,
        public int $files,
        public int $markdown,
        public int $audio,
        public int $video,
        public int $youtube,
    ) {}
}

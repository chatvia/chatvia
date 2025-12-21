<?php

namespace App\Data\Resources\Agent;

use App\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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

    public static function fromAgent(Agent $agent): self
    {
        $pdfCount = $agent->files()->ingested()->pdf()->count();
        $wordCount = $agent->files()->ingested()->word()->count();

        return new self(
            websites: $agent->websites()->where('source_websites.status', 'ingested')->count(),
            texts: $agent->texts()->where('source_texts.status', 'ingested')->count(),
            files: $pdfCount + $wordCount,
            markdown: $agent->files()->ingested()->markdown()->count(),
            audio: $agent->files()->ingested()->audio()->count(),
            video: $agent->files()->ingested()->video()->count(),
            youtube: 0, // YouTube videos feature disabled for now
        );
    }
}

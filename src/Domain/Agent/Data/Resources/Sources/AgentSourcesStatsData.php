<?php

namespace Domain\Agent\Data\Resources\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;
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

    /**
     * One aggregation query per source table (files + websites + texts).
     * Replaces eight sequential COUNT()s over the same polymorphic JOIN that
     * fired on every Sources page load (and during ingestion polling).
     */
    public static function fromAgent(Agent $agent): self
    {
        $mimes = [
            'pdf' => config('mimes.pdf'),
            'word' => config('mimes.word'),
            'markdown' => config('mimes.markdown'),
            'audio' => config('mimes.audio'),
            'video' => config('mimes.video'),
        ];

        $files = DB::table('agent_sources')
            ->join('source_files', 'source_files.id', '=', 'agent_sources.sourceable_id')
            ->where('agent_sources.agent_id', $agent->id)
            ->where('agent_sources.sourceable_type', (new SourceFile)->getMorphClass())
            ->where('source_files.status', 'ingested')
            ->selectRaw(
                'COUNT(*) FILTER (WHERE mime_type = ANY(?)) AS pdf,
                 COUNT(*) FILTER (WHERE mime_type = ANY(?)) AS word,
                 COUNT(*) FILTER (WHERE mime_type = ANY(?)) AS markdown,
                 COUNT(*) FILTER (WHERE mime_type = ANY(?)) AS audio,
                 COUNT(*) FILTER (WHERE mime_type = ANY(?)) AS video',
                [
                    '{'.implode(',', $mimes['pdf']).'}',
                    '{'.implode(',', $mimes['word']).'}',
                    '{'.implode(',', $mimes['markdown']).'}',
                    '{'.implode(',', $mimes['audio']).'}',
                    '{'.implode(',', $mimes['video']).'}',
                ]
            )
            ->first();

        $websites = $agent->websites()->where('source_websites.status', 'ingested')->count();
        $texts = $agent->texts()->where('source_texts.status', 'ingested')->count();

        return new self(
            websites: $websites,
            texts: $texts,
            files: (int) ($files->pdf ?? 0) + (int) ($files->word ?? 0),
            markdown: (int) ($files->markdown ?? 0),
            audio: (int) ($files->audio ?? 0),
            video: (int) ($files->video ?? 0),
            youtube: 0, // YouTube videos feature disabled for now
        );
    }
}

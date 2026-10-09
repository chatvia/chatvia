<?php

namespace Domain\Agent\Queries;

use Domain\Agent\Data\Resources\Sources\AgentSourcesStatsData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;

/**
 * How many trained knowledge sources an agent has, per kind, for the
 * Knowledge page tabs. Files are counted per MIME group in one aggregation
 * over the polymorphic pivot; websites and texts with one count each. Only
 * sources that finished training count.
 */
class AgentSourceCountsQuery
{
    public function get(Agent $agent): AgentSourcesStatsData
    {
        $files = $this->fileCounts($agent);

        return new AgentSourcesStatsData(
            websites: $agent->websites()->where('source_websites.status', SourceStatus::Completed->value)->count(),
            texts: $agent->texts()->where('source_texts.status', SourceStatus::Completed->value)->count(),
            files: (int) ($files->pdf ?? 0) + (int) ($files->word ?? 0),
            markdown: (int) ($files->markdown ?? 0),
            audio: (int) ($files->audio ?? 0),
            video: (int) ($files->video ?? 0),
            youtube: 0, // YouTube sources are behind a feature flag and never trained yet.
        );
    }

    private function fileCounts(Agent $agent): ?object
    {
        $groups = ['pdf', 'word', 'markdown', 'audio', 'video'];

        return DB::table('agent_sources')
            ->join('source_files', 'source_files.id', '=', 'agent_sources.sourceable_id')
            ->where('agent_sources.agent_id', $agent->id)
            ->where('agent_sources.sourceable_type', (new SourceFile)->getMorphClass())
            ->where('source_files.status', SourceStatus::Completed->value)
            ->selectRaw(
                implode(', ', array_map(static fn (string $group): string => "COUNT(*) FILTER (WHERE mime_type = ANY(?)) AS {$group}", $groups)),
                array_map(static fn (string $group): string => '{'.implode(',', (array) config("mimes.{$group}")).'}', $groups),
            )
            ->first();
    }
}

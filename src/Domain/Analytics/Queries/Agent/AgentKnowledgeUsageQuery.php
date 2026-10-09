<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\FileUsageData;
use Domain\Analytics\Data\Agent\Usage\SourceUsageData;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;

/**
 * Which knowledge an agent's answers draw on, most retrieved first.
 */
class AgentKnowledgeUsageQuery extends AgentAnalyticsQuery
{
    /**
     * The agent's own files by retrieval count, from the live `agent_sources`
     * pivot (main DB) — a deleted file drops out.
     *
     * @return list<FileUsageData>
     */
    public function topFiles(Agent $agent, int $limit = 5): array
    {
        return DB::table('agent_sources')
            ->join('source_files', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', (new SourceFile)->getMorphClass());
            })
            ->where('agent_sources.agent_id', $agent->id)
            ->where('agent_sources.retrieval_count', '>', 0)
            ->orderByDesc('agent_sources.retrieval_count')
            ->limit($limit)
            ->get(['source_files.filename', 'source_files.document_classification', 'agent_sources.retrieval_count'])
            ->map(static fn (object $row): FileUsageData => new FileUsageData(
                filename: (string) $row->filename,
                classification: $row->document_classification,
                retrievals: (int) $row->retrieval_count,
            ))
            ->all();
    }

    /**
     * Every source the agent retrieved from, from the durable per-day rollup —
     * not the live pivot, so a source deleted since still shows, with the
     * name it had when it was retrieved.
     *
     * @return list<SourceUsageData>
     */
    public function sources(Agent $agent, int $limit = 50): array
    {
        return $this->dailyRollup($agent, 'analytics_agent_source_daily')
            ->groupBy('sourceable_type', 'sourceable_id')
            ->select([
                'sourceable_type',
                'sourceable_id',
                DB::raw('SUM(retrieval_count) as count'),
                DB::raw('MAX(bucket_day) as last_used_at'),
                DB::raw('MAX(source_name) as name'),
                DB::raw('MAX(source_mime) as mime_type'),
            ])
            ->orderByRaw('SUM(retrieval_count) DESC')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): SourceUsageData => new SourceUsageData(
                kind: (string) $row->sourceable_type,
                sourceId: (int) $row->sourceable_id,
                name: $row->name !== null && $row->name !== '' ? (string) $row->name : null,
                mimeType: $row->mime_type,
                retrievals: (int) $row->count,
                lastUsedOn: $row->last_used_at !== null ? (string) $row->last_used_at : null,
            ))
            ->all();
    }
}

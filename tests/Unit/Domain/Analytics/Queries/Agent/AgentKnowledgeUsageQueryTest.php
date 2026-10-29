<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\SourceUsageData;
use Domain\Analytics\Queries\Agent\AgentKnowledgeUsageQuery;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;

/**
 * The knowledge numbers on an agent's Sources analytics page: every source
 * it retrieved from, summed over the per-day rollup, most retrieved first.
 */
function akuSourceDay(Agent $agent, string $type, int $id, string $day, int $count, ?string $name = null, ?string $mime = null): void
{
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')->insert([
        'agent_id' => $agent->id,
        'workspace_id' => $agent->workspace_id,
        'bucket_day' => $day,
        'sourceable_type' => $type,
        'sourceable_id' => $id,
        'retrieval_count' => $count,
        'source_name' => $name,
        'source_mime' => $mime,
    ]);
}

it('sums each source over its days, most retrieved first, with the name it was recorded under', function (): void {
    $agent = Agent::factory()->create();
    $fileType = (new SourceFile)->getMorphClass();
    $textType = (new SourceText)->getMorphClass();

    akuSourceDay($agent, $fileType, 10, '2026-06-01', 3, 'prices.pdf', 'application/pdf');
    akuSourceDay($agent, $fileType, 10, '2026-06-02', 2, 'prices.pdf', 'application/pdf');
    akuSourceDay($agent, $textType, 4, '2026-06-01', 7, 'Opening hours');
    akuSourceDay(Agent::factory()->create(), $fileType, 11, '2026-06-01', 100, 'not-ours.pdf');

    $query = app(AgentKnowledgeUsageQuery::class);

    expect($query->sources($agent))->toEqual([
        new SourceUsageData(kind: $textType, sourceId: 4, name: 'Opening hours', mimeType: null, retrievals: 7, lastUsedOn: '2026-06-01'),
        new SourceUsageData(kind: $fileType, sourceId: 10, name: 'prices.pdf', mimeType: 'application/pdf', retrievals: 5, lastUsedOn: '2026-06-02'),
    ])->and($query->sources($agent, limit: 1))->toHaveCount(1);
});

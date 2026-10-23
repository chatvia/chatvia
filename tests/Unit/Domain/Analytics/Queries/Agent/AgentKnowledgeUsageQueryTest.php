<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\FileUsageData;
use Domain\Analytics\Data\Agent\Usage\SourceUsageData;
use Domain\Analytics\Queries\Agent\AgentKnowledgeUsageQuery;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;

/**
 * The knowledge numbers on an agent's Sources analytics page: its own files
 * by all-time retrievals (live pivot), and every source it retrieved from by
 * the per-day rollup, most retrieved first.
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

it('lists the agent\'s own retrieved files, most retrieved first', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create();
    $fileType = (new SourceFile)->getMorphClass();

    $file = fn (string $name, ?string $classification = null): SourceFile => AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'filename' => $name,
        'document_classification' => $classification,
    ]);
    $prices = $file('prices.pdf', 'pricing');
    $faq = $file('faq.pdf');
    $unused = $file('unused.pdf');

    DB::table('agent_sources')->insert([
        ['agent_id' => $agent->id, 'sourceable_type' => $fileType, 'sourceable_id' => $faq->id, 'retrieval_count' => 2],
        ['agent_id' => $agent->id, 'sourceable_type' => $fileType, 'sourceable_id' => $prices->id, 'retrieval_count' => 7],
        ['agent_id' => $agent->id, 'sourceable_type' => $fileType, 'sourceable_id' => $unused->id, 'retrieval_count' => 0],
        // Another kind of source with a file's id, and another agent's use.
        ['agent_id' => $agent->id, 'sourceable_type' => (new SourceText)->getMorphClass(), 'sourceable_id' => $unused->id, 'retrieval_count' => 50],
        ['agent_id' => $other->id, 'sourceable_type' => $fileType, 'sourceable_id' => $unused->id, 'retrieval_count' => 90],
    ]);

    $query = app(AgentKnowledgeUsageQuery::class);

    expect($query->topFiles($agent))->toEqual([
        new FileUsageData(filename: 'prices.pdf', classification: 'pricing', retrievals: 7),
        new FileUsageData(filename: 'faq.pdf', classification: null, retrievals: 2),
    ])->and($query->topFiles($agent, limit: 1))->toHaveCount(1);
});

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

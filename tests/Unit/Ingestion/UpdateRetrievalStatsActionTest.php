<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Retrieval\Actions\UpdateRetrievalStatsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Behavioural coverage for the per-retrieval stats bump. The action resolves
 * each chunk's morph alias to its source model and increments the all-time
 * `retrieval_count` (+ `last_retrieved_at`) on both the source table and the
 * `agent_sources` pivot, then dispatches {@see SourceRetrieved} for the
 * date-bucketed rollup.
 *
 * The morph alias "source_file" is registered via enforceMorphMap in
 * AppServiceProvider, so getMorphClass() round-trips through the action.
 */
it('increments the retrieval counter on the source and the agent_sources pivot', function (): void {
    Event::fake([SourceRetrieved::class]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['retrieval_count' => 0]);
    $source->agents()->attach($agent->id, ['retrieval_count' => 0]);

    $type = $source->getMorphClass();

    app(UpdateRetrievalStatsAction::class)->execute(
        results: [
            ['chunkable_type' => $type, 'chunkable_id' => $source->id],
        ],
        agentId: $agent->id,
    );

    $source->refresh();

    expect($source->retrieval_count)->toBe(1)
        ->and($source->last_retrieved_at)->not->toBeNull();

    $pivot = DB::table('agent_sources')
        ->where('agent_id', $agent->id)
        ->where('sourceable_type', $type)
        ->where('sourceable_id', $source->id)
        ->first();

    expect((int) $pivot->retrieval_count)->toBe(1)
        ->and($pivot->last_retrieved_at)->not->toBeNull();

    // The rollup event carries the agent, the resolved label and the morph type.
    Event::assertDispatched(SourceRetrieved::class, function (SourceRetrieved $event) use ($agent, $source, $type): bool {
        return $event->agentId === $agent->id
            && collect($event->sources)->contains(fn (array $r): bool => $r['type'] === $type && $r['id'] === $source->id);
    });
});

it('skips groups whose chunkable_type is not a real class — no UPDATE is issued', function (): void {
    Event::fake([SourceRetrieved::class]);

    // A real source exists, but the action must not touch it: the fake morph
    // type resolves to no class, so the group is filtered out before any write.
    $source = AgentFileFactory::new()->create(['retrieval_count' => 0]);

    DB::enableQueryLog();

    app(UpdateRetrievalStatsAction::class)->execute(
        results: [
            ['chunkable_type' => 'NotAClass\\Made\\Up', 'chunkable_id' => 1],
        ],
        agentId: 1,
    );

    $updates = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'update'));

    DB::disableQueryLog();

    expect($updates)->toBeEmpty();

    // The untouched source keeps its starting counter, and no rollup fires.
    expect($source->refresh()->retrieval_count)->toBe(0);
    Event::assertNotDispatched(SourceRetrieved::class);
});

it('no-ops when the results array is empty — no UPDATE and no rollup event', function (): void {
    Event::fake([SourceRetrieved::class]);

    DB::enableQueryLog();

    app(UpdateRetrievalStatsAction::class)->execute([], agentId: 1);

    $updates = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'update'));

    DB::disableQueryLog();

    expect($updates)->toBeEmpty();
    Event::assertNotDispatched(SourceRetrieved::class);
});

it('still reads full search-result rows queued by an older release', function (): void {
    Event::fake([SourceRetrieved::class]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['retrieval_count' => 0]);
    $source->agents()->attach($agent->id, ['retrieval_count' => 0]);

    // The old payload shape: the raw stdClass rows from the hybrid search.
    $legacyRow = (object) [
        'id' => 1,
        'chunk' => 'customer text that should no longer be queued',
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->id,
    ];

    app(UpdateRetrievalStatsAction::class)->execute([$legacyRow], agentId: $agent->id);

    expect($source->refresh()->retrieval_count)->toBe(1);
});

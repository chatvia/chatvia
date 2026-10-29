<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Retrieval\Actions\UpdateRetrievalStatsAction;
use Domain\Knowledge\Retrieval\Data\SourceRefData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The per-retrieval stats bump: the action resolves each chunk's morph alias
 * to its source model and increments the all-time `retrieval_count` (+
 * `last_retrieved_at`) on both the source table and the `agent_sources`
 * pivot, then dispatches {@see SourceRetrieved} for the date-bucketed rollup,
 * also when the agent was deleted during the turn.
 */
beforeEach(function (): void {
    Event::fake([SourceRetrieved::class]);
});

it('increments the retrieval counter on the source and the agent_sources pivot', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'filename' => 'price-list.pdf',
        'mime_type' => 'application/pdf',
        'retrieval_count' => 0,
    ]);
    $source->agents()->attach($agent->id, ['retrieval_count' => 0]);

    $type = $source->getMorphClass();

    app(UpdateRetrievalStatsAction::class)->execute(
        sources: collect([
            new SourceRefData($type, $source->id),
        ]),
        agentId: $agent->id,
    );

    $source->refresh();
    $pivot = DB::table('agent_sources')
        ->where('agent_id', $agent->id)
        ->where('sourceable_type', $type)
        ->where('sourceable_id', $source->id)
        ->first();

    expect($source->retrieval_count)->toBe(1)
        ->and($source->last_retrieved_at->equalTo(now()))->toBeTrue()
        ->and((int) $pivot->retrieval_count)->toBe(1)
        ->and(Carbon::parse($pivot->last_retrieved_at)->equalTo(now()))->toBeTrue();

    Event::assertDispatched(SourceRetrieved::class, fn (SourceRetrieved $event): bool => $event->agentId === $agent->id
        && $event->workspaceId === $agent->workspace_id
        && $event->sources === [['type' => $type, 'id' => $source->id, 'name' => 'price-list.pdf', 'mime' => 'application/pdf']]);
});

it('still counts for the workspace when the agent was deleted during the turn', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->delete();

    app(UpdateRetrievalStatsAction::class)->execute(collect([new SourceRefData($source->getMorphClass(), $source->id)]), $agent->id);

    Event::assertDispatched(SourceRetrieved::class, fn (SourceRetrieved $event): bool => $event->workspaceId === $agent->workspace_id);
});

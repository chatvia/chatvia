<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Retrieval\Actions\UpdateRetrievalStatsAction;
use Domain\Knowledge\Retrieval\Data\SourceRefData;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Behavioural coverage for the per-retrieval stats bump. The action resolves
 * each chunk's morph alias to its source model and increments the all-time
 * `retrieval_count` (+ `last_retrieved_at`) on both the source table and the
 * `agent_sources` pivot, then dispatches {@see SourceRetrieved} for the
 * date-bucketed rollup.
 *
 * The rollup names each source while it still exists (file name and mime,
 * website URL, a short text excerpt, video title), so a deleted source keeps
 * its name on the Sources page.
 */
beforeEach(function (): void {
    Event::fake([SourceRetrieved::class]);
});

it('leaves the source updated_at alone, since a retrieval is not an edit', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $source->agents()->attach($agent->id);
    $source->forceFill(['updated_at' => now()->subWeek()->startOfSecond()])->saveQuietly();
    $updatedAt = $source->fresh()->updated_at;

    $this->travel(1)->minutes();

    app(UpdateRetrievalStatsAction::class)->execute(
        sources: collect([new SourceRefData($source->getMorphClass(), $source->id)]),
        agentId: $agent->id,
    );

    expect($source->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and($source->fresh()->retrieval_count)->toBe(1);
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

    expect($source->retrieval_count)->toBe(1)
        ->and($source->last_retrieved_at->equalTo(now()))->toBeTrue();

    $pivot = DB::table('agent_sources')
        ->where('agent_id', $agent->id)
        ->where('sourceable_type', $type)
        ->where('sourceable_id', $source->id)
        ->first();

    expect((int) $pivot->retrieval_count)->toBe(1)
        ->and(Carbon::parse($pivot->last_retrieved_at)->equalTo(now()))->toBeTrue();

    // The rollup event carries the agent, its workspace, and the source with its name and mime.
    Event::assertDispatched(SourceRetrieved::class, fn (SourceRetrieved $event): bool => $event->agentId === $agent->id
        && $event->workspaceId === $agent->workspace_id
        && $event->sources === [['type' => $type, 'id' => $source->id, 'name' => 'price-list.pdf', 'mime' => 'application/pdf']]);
});

it('names each kind of source for the rollup', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://docs.example.test']);
    $text = SourceText::factory()->ingested()->create([
        'workspace_id' => $agent->workspace_id,
        'excerpt' => "Our   opening\nhours:\n\n".Str::repeat('nine to five ', 10),
    ]);

    app(UpdateRetrievalStatsAction::class)->execute(collect([
        new SourceRefData('source_website', $website->id),
        new SourceRefData('source_text', $text->id),
    ]), $agent->id);

    expect(Event::dispatched(SourceRetrieved::class)->sole()[0]->sources)->toBe([
        ['type' => 'source_website', 'id' => $website->id, 'name' => 'https://docs.example.test', 'mime' => null],
        // Whitespace squashed, then cut to 80 characters.
        ['type' => 'source_text', 'id' => $text->id, 'name' => 'Our opening hours: nine to five nine to five nine to five nine to five nine to f...', 'mime' => null],
    ]);
});

it('still counts for the workspace when the agent was deleted during the turn', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->delete();

    app(UpdateRetrievalStatsAction::class)->execute(collect([new SourceRefData($source->getMorphClass(), $source->id)]), $agent->id);

    Event::assertDispatched(SourceRetrieved::class, fn (SourceRetrieved $event): bool => $event->workspaceId === $agent->workspace_id);
});

it('skips a result whose chunkable_type is no model, changing nothing and dispatching no rollup', function (): void {
    // A real source exists, but the action must not touch it: the fake morph
    // type resolves to no class, so the group is filtered out before any write.
    $source = AgentFileFactory::new()->create(['retrieval_count' => 0]);

    app(UpdateRetrievalStatsAction::class)->execute(
        sources: collect([
            new SourceRefData('NotAClass\\Made\\Up', $source->id),
        ]),
        agentId: 1,
    );

    expect($source->refresh()->retrieval_count)->toBe(0);
    Event::assertNotDispatched(SourceRetrieved::class);
});

it('changes nothing and dispatches no rollup for no results', function (): void {
    $source = AgentFileFactory::new()->create(['retrieval_count' => 0]);

    app(UpdateRetrievalStatsAction::class)->execute(collect(), agentId: 1);

    expect($source->refresh()->retrieval_count)->toBe(0);
    Event::assertNotDispatched(SourceRetrieved::class);
});

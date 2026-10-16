<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Retrieval\Actions\UpdateRetrievalStatsAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

it('increments the retrieval counter on the source and the agent_sources pivot', function (): void {
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

    // The rollup event carries the agent, its workspace, and the source with its name and mime.
    Event::assertDispatched(SourceRetrieved::class, fn (SourceRetrieved $event): bool => $event->agentId === $agent->id
        && $event->workspaceId === $agent->workspace_id
        && $event->sources === [['type' => $type, 'id' => $source->id, 'name' => 'price-list.pdf', 'mime' => 'application/pdf']]);
});

it('names each kind of source for the rollup', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $text = SourceText::query()->create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => 'workspaces/x/sources/source_text/y/a.txt',
        'excerpt' => "Our   opening\nhours:\n\n".str_repeat('nine to five ', 10),
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Completed,
    ]);
    $titled = SourceYoutubeVideo::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Product tour', 'length' => 1, 'status' => 'ingested']);
    $untitled = SourceYoutubeVideo::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://youtu.be/aaaaaaaaaaa', 'length' => 1, 'status' => 'ingested']);

    app(UpdateRetrievalStatsAction::class)->execute([
        ['chunkable_type' => 'source_website', 'chunkable_id' => $website->id],
        ['chunkable_type' => 'source_text', 'chunkable_id' => $text->id],
        ['chunkable_type' => 'source_youtube_video', 'chunkable_id' => $titled->id],
        ['chunkable_type' => 'source_youtube_video', 'chunkable_id' => $untitled->id],
    ], $agent->id);

    expect(Event::dispatched(SourceRetrieved::class)->sole()[0]->sources)->toBe([
        ['type' => 'source_website', 'id' => $website->id, 'name' => 'https://docs.example.test', 'mime' => null],
        // Whitespace squashed, then cut to 80 characters.
        ['type' => 'source_text', 'id' => $text->id, 'name' => 'Our opening hours: nine to five nine to five nine to five nine to five nine to f...', 'mime' => null],
        ['type' => 'source_youtube_video', 'id' => $titled->id, 'name' => 'Product tour', 'mime' => null],
        // A video without a title is named by its URL.
        ['type' => 'source_youtube_video', 'id' => $untitled->id, 'name' => 'https://youtu.be/aaaaaaaaaaa', 'mime' => null],
    ]);
});

it('still counts for the workspace when the agent was deleted during the turn', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->delete();

    app(UpdateRetrievalStatsAction::class)->execute([['chunkable_type' => $source->getMorphClass(), 'chunkable_id' => $source->id]], $agent->id);

    Event::assertDispatched(SourceRetrieved::class, fn (SourceRetrieved $event): bool => $event->workspaceId === $agent->workspace_id);
});

it('skips groups whose chunkable_type is not a real class — no UPDATE is issued', function (): void {
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
    DB::enableQueryLog();

    app(UpdateRetrievalStatsAction::class)->execute([], agentId: 1);

    $updates = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_starts_with(strtolower(ltrim($sql)), 'update'));

    DB::disableQueryLog();

    expect($updates)->toBeEmpty();
    Event::assertNotDispatched(SourceRetrieved::class);
});

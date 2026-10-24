<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestTextAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Jobs\Text\TextChunkIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Knowledge\Ingestion\FakeTextSplitter;

/**
 * IngestTextAction starts the ingestion of a text source: it marks the text
 * as processing, tells the Text sources page, splits the stored text as plain
 * text, and hands the parts as chunk jobs to the shared batch step. Finishing
 * the batch is that step's job (DispatchSourceIngestionBatchActionTest).
 */
it('marks the text as processing, announces it, and hands its parts on as chunk jobs', function (): void {
    Storage::fake();
    Event::fake([TextIngestionStarted::class]);

    $agent = Agent::factory()->create();
    $text = SourceText::factory()->attachedTo($agent)->create(['excerpt' => 'Opening hours.']);
    Storage::put($text->filepath, "Opening hours.\n\nMonday to Friday.");

    $handedOn = null;
    $this->mock(DispatchSourceIngestionBatchAction::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function (SourceText $source, Agent $for, Collection $jobs) use ($text, $agent, &$handedOn): bool {
            $handedOn = $jobs;

            return $source->is($text) && $for->is($agent);
        })
        ->andReturnNull();

    app(IngestTextAction::class)->execute($text, $agent);

    /** @var FakeTextSplitter $splitter */
    $splitter = app(TextSplitter::class);

    expect($text->fresh()->status)->toBe(SourceStatus::Processing)
        ->and($splitter->kinds)->toBe([ContentKind::Text])
        ->and($handedOn->every(fn (mixed $job): bool => $job instanceof TextChunkIngestionJob && $job->agent->is($agent)))->toBeTrue()
        ->and($handedOn->flatMap(fn (TextChunkIngestionJob $job): array => $job->group->pages)->map(fn (PageData $page): string => $page->content)->all())
        ->toBe(['Opening hours.', 'Monday to Friday.']);

    // Sent once the text is processing, so the page shows the new status.
    Event::assertDispatched(TextIngestionStarted::class, fn (TextIngestionStarted $event): bool => $event->text->is($text)
        && $event->agentExternalId === $agent->external_id
        && $event->broadcastWith()['status'] === SourceStatus::Processing->value);
});

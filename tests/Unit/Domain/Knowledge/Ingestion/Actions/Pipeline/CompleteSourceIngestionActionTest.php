<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\CompleteSourceIngestionAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Event;

/**
 * Finishing a source's ingestion batch marks it ingested and broadcasts the
 * final state on the channel of its kind, so its sources page stops showing
 * "ingesting". (A file's completion is covered with the batch that runs it, in
 * DispatchSourceIngestionBatchActionTest.)
 */
it('broadcasts a text source completion on the text channel', function (): void {
    Event::fake([TextIngestionStarted::class]);
    $agent = Agent::factory()->create();
    $text = SourceText::factory()->processing()->attachedTo($agent)->create();

    app(CompleteSourceIngestionAction::class)->execute($text, $agent->external_id);

    expect($text->fresh()->status)->toBe(SourceStatus::Completed);
    Event::assertDispatched(TextIngestionStarted::class, fn (TextIngestionStarted $event): bool => $event->broadcastWith()['status'] === 'ingested');
});

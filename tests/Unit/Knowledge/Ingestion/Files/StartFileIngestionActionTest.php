<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Files\StartFileIngestionAction;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\TextFile\TextFileIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Support\Facades\Bus;

/**
 * An uploaded file is queued on the pipeline that fits its type: media is
 * transcribed, text files and ebooks are split directly, and everything else
 * goes through the document parser.
 */
beforeEach(function (): void {
    Bus::fake();
});

it('queues each file type on its own pipeline', function (string $filename, string $mimeType, string $jobClass): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => $filename, 'mime_type' => $mimeType]);

    app(StartFileIngestionAction::class)->execute($file, $agent);

    expect($file->fresh()->status)->toBe(SourceStatus::Processing);
    Bus::assertDispatched($jobClass);
})->with([
    'audio' => ['talk.mp3', 'audio/mpeg', MediaIngestionJob::class],
    'video' => ['demo.mp4', 'video/mp4', MediaIngestionJob::class],
    'markdown' => ['notes.md', 'text/markdown', TextFileIngestionJob::class],
    'ebook' => ['book.epub', 'application/epub+zip', TextFileIngestionJob::class],
    'pdf' => ['manual.pdf', 'application/pdf', DocumentIngestionJob::class],
]);

it('leaves an ingested file alone', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);

    app(StartFileIngestionAction::class)->execute($file, $agent);

    Bus::assertNothingDispatched();
});

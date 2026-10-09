<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentEnhanceChunkJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextEnhanceChunkJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\Text\Events\TextIngestionProgressed;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * The body of every per-chunk job: create the chunk, report progress, and
 * queue the enhancement that fits the source (documents with their images and
 * tables, everything else from its text).
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class, TextIngestionProgressed::class]);
});

function iscPage(string $content): PageData
{
    return new PageData(pageNumber: 1, content: $content, checksum: md5($content.uniqid()));
}

it('enhances document pages with their visuals when enhancement is on', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id, 'mime_type' => 'application/pdf']);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, iscPage('A page')));

    Event::assertDispatched(FileIngestionProgressed::class);
    Bus::assertDispatched(DocumentEnhanceChunkJob::class);
    Bus::assertNotDispatched(TextEnhanceChunkJob::class);
});

it('enhances media transcript parts from their text only', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id, 'mime_type' => 'audio/mpeg']);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, iscPage('Spoken words')));

    Bus::assertDispatched(TextEnhanceChunkJob::class);
    Bus::assertNotDispatched(DocumentEnhanceChunkJob::class);
});

it('does not enhance file chunks when enhancement is off', function (): void {
    config(['chatvia.ai.chunker.enhance' => false]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id, 'mime_type' => 'application/pdf']);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, iscPage('A page')));

    Bus::assertNothingDispatched();
});

it('enhances text sources by default, even with file enhancement off, and reports their progress', function (): void {
    config(['chatvia.ai.chunker.enhance' => false]);

    $agent = Agent::factory()->create();
    $text = SourceText::query()->create([
        'user_id' => $agent->user_id,
        'filepath' => 'users/x/agents/y/train/text/a.txt',
        'excerpt' => 'excerpt',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Processing,
    ]);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $text, iscPage('Some text')));

    Event::assertDispatched(TextIngestionProgressed::class);
    Bus::assertDispatched(TextEnhanceChunkJob::class);
});

it('does not enhance text sources when text enhancement is off', function (): void {
    config(['chatvia.ai.chunker.enhance' => true, 'chatvia.ai.chunker.enhance_text' => false]);

    $agent = Agent::factory()->create();
    $text = SourceText::query()->create([
        'user_id' => $agent->user_id,
        'filepath' => 'users/x/agents/y/train/text/b.txt',
        'excerpt' => 'excerpt',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Processing,
    ]);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $text, iscPage('Other text')));

    Event::assertDispatched(TextIngestionProgressed::class);
    Bus::assertNotDispatched(TextEnhanceChunkJob::class);
});

it('skips a duplicate page: no progress event, no enhancement', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id, 'mime_type' => 'application/pdf']);
    $page = iscPage('Same page');

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, $page));
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, $page));

    Event::assertNotDispatched(FileIngestionProgressed::class);
    Bus::assertNothingDispatched();
});

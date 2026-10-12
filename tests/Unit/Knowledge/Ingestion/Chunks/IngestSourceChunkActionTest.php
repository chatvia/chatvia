<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\Text\Events\TextIngestionProgressed;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * The body of every chunk job of a file or text source: store the job's pages,
 * enhanced as the source's setting says, and report the batch's progress.
 */
beforeEach(function (): void {
    Event::fake([FileIngestionProgressed::class, TextIngestionProgressed::class]);

    config(['services.scalewayai.api_key' => 'test-key']);
    Http::fake(fn (Request $request) => Http::response(['choices' => [['message' => ['content' => json_encode([
        'summary' => 'A short summary.',
        'questions' => ['What is this about?'],
    ])]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]));
});

function iscGroup(string ...$contents): PageGroupData
{
    return new PageGroupData(array_values(array_map(
        fn (string $content, int $index): PageData => new PageData(pageNumber: $index + 1, content: $content, checksum: md5($content.uniqid())),
        $contents,
        array_keys($contents),
    )));
}

function iscText(Agent $agent): SourceText
{
    return SourceText::query()->create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => 'users/x/agents/y/train/text/'.uniqid().'.txt',
        'excerpt' => 'excerpt',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Processing,
    ]);
}

it('stores document pages without model calls when enhancement is off, and reports the progress', function (): void {
    config(['chatvia.ai.chunker.enhance' => false]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'mime_type' => 'application/pdf', 'status' => 'ingesting']);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, iscGroup('Page one', 'Page two'), progress: 40));

    expect($file->chunks()->count())->toBe(2)
        ->and($file->chunks()->where('is_enhanced', true)->exists())->toBeFalse();

    Http::assertNothingSent();
    Event::assertDispatched(FileIngestionProgressed::class, fn (FileIngestionProgressed $event): bool => $event->broadcastWith()['progress'] === 40);
});

it('enhances document and media pages when enhancement is on', function (string $mimeType): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'mime_type' => $mimeType]);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, iscGroup('Some content')));

    expect($file->chunks()->sole()->is_enhanced)->toBeTrue();
    Http::assertSentCount(1);
})->with(['document' => 'application/pdf', 'audio' => 'audio/mpeg']);

it('enhances text sources by default, even with file enhancement off, and reports their progress', function (): void {
    config(['chatvia.ai.chunker.enhance' => false]);

    $agent = Agent::factory()->create();
    $text = iscText($agent);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $text, iscGroup('Some text'), progress: 100));

    expect($text->chunks()->sole()->is_enhanced)->toBeTrue();
    // Only the batch's completion step says 100.
    Event::assertDispatched(TextIngestionProgressed::class, fn (TextIngestionProgressed $event): bool => $event->broadcastWith()['progress'] === 99);
});

it('does not enhance text sources when text enhancement is off', function (): void {
    config(['chatvia.ai.chunker.enhance' => true, 'chatvia.ai.chunker.enhance_text' => false]);

    $agent = Agent::factory()->create();
    $text = iscText($agent);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $text, iscGroup('Other text')));

    expect($text->chunks()->sole()->is_enhanced)->toBeFalse();
    Http::assertNothingSent();
});

it('stores the pages without enhancement when asked to, whatever the setting', function (): void {
    $agent = Agent::factory()->create();
    $text = iscText($agent);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $text, iscGroup('Fallback text'), enhancement: ChunkEnhancement::Off));

    expect($text->chunks()->sole()->is_enhanced)->toBeFalse();
    Http::assertNothingSent();
});

it('reports progress for a duplicate page, which makes no chunk and no model call', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'mime_type' => 'application/pdf']);
    $group = iscGroup('Same page');

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, $group));
    Http::fake();
    Event::fake([FileIngestionProgressed::class]);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, $group, progress: 50));

    expect($file->chunks()->count())->toBe(1);
    Http::assertNothingSent();
    Event::assertDispatched(FileIngestionProgressed::class);
});

it('sends no progress event without a batch progress', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'mime_type' => 'application/pdf']);

    app(IngestSourceChunkAction::class)->execute(new SourceChunkData($agent, $file, iscGroup('A page')));

    Event::assertNotDispatched(FileIngestionProgressed::class);
});

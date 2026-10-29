<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\Text\Events\TextIngestionProgressed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The body of every chunk job of a file source: store the job's pages,
 * enhanced as the source's setting says, and report the batch's progress.
 */
beforeEach(function (): void {
    Event::fake([FileIngestionProgressed::class, TextIngestionProgressed::class]);

    Http::fake(fn (Request $request) => Http::response(['choices' => [['message' => ['content' => json_encode([
        'summary' => 'A short summary.',
        'questions' => ['What is this about?'],
    ])]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]));
});

function iscGroup(string ...$contents): PageGroupData
{
    return new PageGroupData(array_values(array_map(
        fn (string $content, int $index): PageData => new PageData(pageNumber: $index + 1, content: $content, checksum: md5($content.Str::random())),
        $contents,
        array_keys($contents),
    )));
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

<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * One enhancement step for every source type ("contextual retrieval"): the
 * chunker's page summary is prepended to the embedded text and its questions
 * get their own embedding. Document pages also describe their images and
 * tables; text chunks keep their metadata as it was.
 */
const ECA_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC';

beforeEach(function (): void {
    Storage::fake();

    config([
        'services.scalewayai.api_key' => 'test-key',
        'chatvia.ai.provider' => 'scaleway',
    ]);

    Http::fake(function (Request $request) {
        $body = $request->data();

        if (isset($body['response_format'])) {
            return Http::response(['choices' => [['message' => ['content' => json_encode([
                'summary' => 'A page about oceans.',
                'questions' => ['Which ocean is largest?', 'How deep is it?'],
            ])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]);
        }

        $parts = $body['messages'][0]['content'] ?? null;
        $isVision = is_array($parts) && ($parts[1]['type'] ?? null) === 'image_url';

        return Http::response(['choices' => [['message' => ['content' => $isVision ? 'A chart of ocean depths.' : 'One table of depths.']]]]);
    });
});

function ecaChunk(PageData $page): Chunk
{
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

    return app(CreateSourceChunkAction::class)->execute($agent, $file, $page);
}

it('prepends the summary and stores the questions of a text chunk', function (): void {
    $page = new PageData(pageNumber: 1, content: 'The Pacific is the largest ocean.', checksum: md5(uniqid()));
    $chunk = ecaChunk($page);

    app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(chunk: $chunk, page: $page));

    $chunk->refresh();

    expect($chunk->is_enhanced)->toBeTrue()
        ->and($chunk->summary)->toBe('A page about oceans.')
        ->and($chunk->questions)->toBe("Which ocean is largest?\nHow deep is it?")
        ->and($chunk->questions_embedding)->not->toBeNull()
        ->and($chunk->cost_in_micro_cents)->toBeGreaterThan(0)
        ->and($chunk->metadata)->toBe([]);

    Http::assertSentCount(1);
});

it('describes the images and tables of a document page and keeps them in the metadata', function (): void {
    $page = new PageData(
        pageNumber: 1,
        content: 'Depths of the oceans.',
        checksum: md5(uniqid()),
        imagesBase64: [ECA_PNG],
        tables: ["| Ocean | Depth |\n|---|---|\n| Pacific | 10994 |", '  '],
    );
    $chunk = ecaChunk($page);

    app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(chunk: $chunk, page: $page, imageDirectory: 'users/ws/agents/ag'));

    $metadata = $chunk->refresh()->metadata;
    $imagePath = array_key_first($metadata['images']);

    expect($metadata['images'][$imagePath])->toBe('A chart of ocean depths.')
        ->and($imagePath)->toStartWith('users/ws/agents/ag/')
        ->and($metadata['tables']['markdown'])->toHaveCount(1)
        ->and($metadata['tables']['descriptions'])->toBe('One table of depths.');

    Storage::assertExists($imagePath);
    Http::assertSentCount(3);
});

it('does nothing for a chunk that is already enhanced', function (): void {
    $page = new PageData(pageNumber: 1, content: 'Already done.', checksum: md5(uniqid()));
    $chunk = ecaChunk($page);
    $chunk->update(['is_enhanced' => true]);

    app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(chunk: $chunk, page: $page));

    Http::assertNothingSent();
});

<?php

use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * One enhancement step for every source type ("contextual retrieval"), run
 * before the chunk is stored: the chunker's page summary is prepended to the
 * text to embed and its questions are returned for their own embedding.
 * Document pages also describe their images and tables.
 */
const ECA_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC';

beforeEach(function (): void {
    Storage::fake();

    config([
        'services.scalewayai.api_key' => 'test-key',
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

it('prepends the summary to the text to embed and returns the questions', function (): void {
    $page = new PageData(pageNumber: 1, content: 'The Pacific is the largest ocean.', checksum: md5(uniqid()));

    $enhanced = app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(page: $page));

    expect($enhanced->summary)->toBe('A page about oceans.')
        ->and($enhanced->embeddingText)->toBe("A page about oceans.\n\nThe Pacific is the largest ocean.")
        ->and($enhanced->questions)->toBe("Which ocean is largest?\nHow deep is it?")
        ->and($enhanced->costInCents)->toBeGreaterThan(0.0)
        ->and($enhanced->metadata)->toBeNull();

    Http::assertSentCount(1);
});

it('describes the images and tables of a document page and returns them as metadata', function (): void {
    $page = new PageData(
        pageNumber: 1,
        content: 'Depths of the oceans.',
        checksum: md5(uniqid()),
        imagesBase64: [ECA_PNG],
        tables: ["| Ocean | Depth |\n|---|---|\n| Pacific | 10994 |", '  '],
    );

    $enhanced = app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(page: $page, imageDirectory: 'users/ws/agents/ag'));

    $metadata = $enhanced->metadata;
    $imagePath = array_key_first($metadata['images']);

    expect($metadata['images'][$imagePath])->toBe('A chart of ocean depths.')
        ->and($imagePath)->toStartWith('users/ws/agents/ag/')
        ->and($metadata['tables']['markdown'])->toHaveCount(1)
        ->and($metadata['tables']['descriptions'])->toBe('One table of depths.')
        ->and($enhanced->embeddingText)->toContain('A chart of ocean depths.');

    Storage::assertExists($imagePath);
    Http::assertSentCount(3);
});

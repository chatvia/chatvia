<?php

use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Knowledge\Document\PngImage;

/**
 * One enhancement step for every source type ("contextual retrieval"), run
 * before the chunk is stored: the chunker's page summary is prepended to the
 * text to embed and its questions are returned for their own embedding.
 * Document pages also describe their images and tables.
 */
beforeEach(function (): void {
    Storage::fake();

    Http::fake(function (Request $request): PromiseInterface {
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
        ->and($enhanced->metadata)->toBeNull();

    Http::assertSentCount(1);
});

it('describes the images and tables of a document page and returns them as metadata', function (): void {
    // A 4x4 PNG.
    $page = new PageData(
        pageNumber: 1,
        content: 'Depths of the oceans.',
        checksum: md5(uniqid()),
        imagesBase64: [PngImage::BASE64],
        tables: ["| Ocean | Depth |\n|---|---|\n| Pacific | 10994 |", '  '],
    );

    $enhanced = app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(page: $page, imageDirectory: 'workspaces/ws/sources/source_file/sf/images'));

    $metadata = $enhanced->metadata;
    $imagePath = array_key_first($metadata['images']);

    expect($metadata['images'][$imagePath])->toBe('A chart of ocean depths.')
        ->and($imagePath)->toStartWith('workspaces/ws/sources/source_file/sf/images/')
        ->and($metadata['tables']['markdown'])->toHaveCount(1)
        ->and($metadata['tables']['descriptions'])->toBe('One table of depths.')
        ->and($enhanced->embeddingText)->toContain('A chart of ocean depths.');

    Storage::assertExists($imagePath);
    Http::assertSentCount(3);
});

it('skips the images of a page when image descriptions are off, and still describes its tables', function (): void {
    config(['chatvia.ai.chunker.describe_images' => false]);
    $page = new PageData(
        pageNumber: 1,
        content: 'Depths of the oceans.',
        checksum: md5(uniqid()),
        imagesBase64: [PngImage::BASE64],
        tables: ["| Ocean | Depth |\n|---|---|\n| Pacific | 10994 |"],
    );

    $enhanced = app(EnhanceChunkAction::class)->execute(new EnhanceChunkData(page: $page, imageDirectory: 'workspaces/ws/sources/source_file/sf/images'));

    expect($enhanced->metadata['images'] ?? [])->toBe([])
        ->and($enhanced->metadata['tables']['descriptions'])->toBe('One table of depths.');

    // The page's enhancement and its tables: no call to describe the image.
    Http::assertSentCount(2);
});

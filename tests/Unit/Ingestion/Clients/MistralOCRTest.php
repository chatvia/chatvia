<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Wire-format coverage for the Mistral OCR client — the externally-hosted parser
 * that turns uploaded PDFs/images into PageData. Was on the review's #3 named
 * coverage gap (ingestion OCR/parse clients). HTTP is faked so no real Mistral
 * API call is made; mirrors the pattern proven by Qwen3RerankTest.
 */
beforeEach(function (): void {
    config()->set('services.mistral.api_key', 'test-key');
});

/** Wrap a payload in the OCR response envelope a single page would produce. */
function ocrResponse(array $page): array
{
    return ['pages' => [$page]];
}

it('posts the expected OCR envelope with bearer auth', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(ocrResponse([
            'index' => 0,
            'markdown' => 'hello',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    app(MistralOcr::class)->fromUrl('https://files.test/manual.pdf');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.mistral.ai/v1/ocr'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'mistral-ocr-latest'
            && $request['document'] === ['type' => 'document_url', 'document_url' => 'https://files.test/manual.pdf']
            && $request['table_format'] === 'markdown'
            && $request['include_image_base64'] === true;
    });
});

it('sends images under image_url instead of document_url', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(ocrResponse([
            'index' => 0,
            'markdown' => 'text on the image',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    app(MistralOcr::class)->fromImageUrl('https://files.test/scan.png');

    Http::assertSent(function (Request $request): bool {
        return $request['document'] === ['type' => 'image_url', 'image_url' => 'https://files.test/scan.png'];
    });
});

it('maps an OCR page to PageData with stripped content, checksum, images, tables, hyperlinks', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(ocrResponse([
            'index' => 1,
            // Markdown that should resolve to a clean text body after strip_tags.
            'markdown' => "# Title\n\nBody paragraph with **bold**.",
            'images' => [
                ['image_base64' => 'iVBORw0KGgoFAKE_A=='],
                ['image_base64' => 'iVBORw0KGgoFAKE_B=='],
            ],
            'tables' => [
                ['content' => "| h | h |\n|---|---|\n| a | b |"],
            ],
            'hyperlinks' => ['https://docs.test/a', 'https://docs.test/b'],
        ]), 200),
    ]);

    $pages = app(MistralOcr::class)->fromUrl('https://files.test/manual.pdf');

    expect($pages)->toHaveCount(1);

    /** @var PageData $page */
    $page = $pages->first();

    expect($page)->toBeInstanceOf(PageData::class)
        ->and($page->pageNumber)->toBe(1)
        ->and($page->content)->toContain('Title')
        ->and($page->content)->toContain('Body paragraph')
        ->and($page->content)->not->toContain('<')
        ->and($page->content)->not->toContain('**')
        ->and($page->checksum)->toBe(hash('xxh128', $page->content))
        ->and($page->imagesBase64)->toBe(['iVBORw0KGgoFAKE_A==', 'iVBORw0KGgoFAKE_B=='])
        ->and($page->tables)->toBe(["| h | h |\n|---|---|\n| a | b |"])
        ->and($page->hyperlinks)->toBe(['https://docs.test/a', 'https://docs.test/b']);
});

it('normalises a zero-based page index to one-based pageNumber', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(ocrResponse([
            'index' => 0,
            'markdown' => 'first page',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    $page = app(MistralOcr::class)->fromUrl('https://files.test/x.pdf')->first();

    expect($page->pageNumber)->toBe(1);
});

it('propagates upstream HTTP errors via ->throw()', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(['error' => 'rate limited'], 429),
    ]);

    expect(fn () => app(MistralOcr::class)->fromUrl('https://files.test/x.pdf'))
        ->toThrow(RequestException::class);
});

it('omits hyperlinks when the upstream payload doesn\'t include them', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(ocrResponse([
            'index' => 2,
            'markdown' => 'page without hyperlinks',
            'images' => [],
            'tables' => [],
            // no 'hyperlinks' key on purpose
        ]), 200),
    ]);

    $page = app(MistralOcr::class)->fromUrl('https://files.test/x.pdf')->first();

    expect($page->hyperlinks)->toBe([])
        ->and($page->pageNumber)->toBe(2);
});

it('writes the temporary OCR copy under the owner prefix and deletes it after success', function (): void {
    Storage::fake();
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path): string => 'https://files.test/'.$path);

    $seenPath = null;

    Http::fake(function (Request $request) use (&$seenPath) {
        $seenPath = str_replace('https://files.test/', '', (string) $request['document']['document_url']);

        // The copy must exist while Mistral fetches it.
        expect(Storage::exists($seenPath))->toBeTrue();

        return Http::response(ocrResponse([
            'index' => 1,
            'markdown' => 'page',
            'images' => [],
            'tables' => [],
        ]), 200);
    });

    $local = create_temp_file('manual.pdf', '%PDF-1.4 test');

    $pages = app(MistralOcr::class)->fromFile($local, 'users/ws-ext/agents/agent-ext/tmp');

    expect($pages)->toHaveCount(1)
        ->and($seenPath)->toStartWith('users/ws-ext/agents/agent-ext/tmp/')
        ->and(Storage::exists($seenPath))->toBeFalse()
        ->and(Storage::allFiles())->toBe([]);
});

it('deletes the temporary OCR copy when the OCR call fails', function (): void {
    Storage::fake();
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path): string => 'https://files.test/'.$path);

    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(['error' => 'boom'], 500),
    ]);

    $local = create_temp_file('manual.pdf', '%PDF-1.4 test');

    expect(fn () => app(MistralOcr::class)->fromFile($local, 'users/ws-ext/agents/agent-ext/tmp'))
        ->toThrow(RequestException::class);

    expect(Storage::allFiles())->toBe([]);
});

<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Wire-format coverage for the Mistral OCR client — the externally-hosted parser
 * that turns uploaded PDFs/images into PageData. HTTP is faked so no real
 * Mistral API call is made. The file goes inside the request as a data: URL,
 * so OCR works on an install Mistral cannot reach.
 */
beforeEach(function (): void {
    config()->set('services.mistral.api_key', 'test-key');
});

/** Wrap a payload in the OCR response envelope a single page would produce. */
function mocrResponse(array $page): array
{
    return ['pages' => [$page]];
}

/** A small local PDF to OCR. */
function mocrPdf(): string
{
    return create_temp_file('manual.pdf', '%PDF-1.4 test');
}

it('posts the expected OCR envelope with bearer auth and the PDF inside the request', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(mocrResponse([
            'index' => 0,
            'markdown' => 'hello',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    app(MistralOcr::class)->fromFile(mocrPdf());

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.mistral.ai/v1/ocr'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'mistral-ocr-latest'
        && $request['document'] === ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 test')]
        && $request['table_format'] === 'markdown'
        && $request['include_image_base64'] === true);
});

it('sends images under image_url instead of document_url', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(mocrResponse([
            'index' => 0,
            'markdown' => 'text on the image',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    app(MistralOcr::class)->fromFile(create_temp_file('scan.jpg', 'jpeg bytes'));

    Http::assertSent(fn (Request $request): bool => $request['document'] === ['type' => 'image_url', 'image_url' => 'data:image/jpeg;base64,'.base64_encode('jpeg bytes')]);
});

it('types an office file by its extension', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(mocrResponse([
            'index' => 0,
            'markdown' => 'slide',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    app(MistralOcr::class)->fromFile(create_temp_file('deck.pptx', 'pptx bytes'));

    Http::assertSent(fn (Request $request): bool => $request['document']['document_url']
        === 'data:application/vnd.openxmlformats-officedocument.presentationml.presentation;base64,'.base64_encode('pptx bytes'));
});

it('keeps no copy of the file in storage', function (): void {
    Storage::fake();
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(mocrResponse([
            'index' => 0,
            'markdown' => 'page',
            'images' => [],
            'tables' => [],
        ]), 200),
    ]);

    expect(app(MistralOcr::class)->fromFile(mocrPdf()))->toHaveCount(1)
        ->and(Storage::allFiles())->toBe([]);
});

it('maps an OCR page to PageData with stripped content, checksum, images, tables, hyperlinks', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(mocrResponse([
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

    $pages = app(MistralOcr::class)->fromFile(mocrPdf());

    expect($pages)->toHaveCount(1);

    /** @var PageData $page */
    $page = $pages->first();

    expect($page->pageNumber)->toBe(2)
        ->and($page->content)->toContain('Title')
        ->and($page->content)->toContain('Body paragraph')
        ->and($page->content)->not->toContain('<')
        ->and($page->content)->not->toContain('**')
        ->and($page->checksum)->toBe(hash('xxh128', $page->content))
        ->and($page->imagesBase64)->toBe(['iVBORw0KGgoFAKE_A==', 'iVBORw0KGgoFAKE_B=='])
        ->and($page->tables)->toBe(["| h | h |\n|---|---|\n| a | b |"])
        ->and($page->hyperlinks)->toBe(['https://docs.test/a', 'https://docs.test/b']);
});

it('propagates upstream HTTP errors via ->throw()', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(['error' => 'rate limited'], 429),
    ]);

    expect(fn () => app(MistralOcr::class)->fromFile(mocrPdf()))
        ->toThrow(RequestException::class);
});

it('omits hyperlinks when the upstream payload doesn\'t include them', function (): void {
    Http::fake([
        'api.mistral.ai/v1/ocr' => Http::response(mocrResponse([
            'index' => 2,
            'markdown' => 'page without hyperlinks',
            'images' => [],
            'tables' => [],
            // no 'hyperlinks' key on purpose
        ]), 200),
    ]);

    $page = app(MistralOcr::class)->fromFile(mocrPdf())->first();

    expect($page->hyperlinks)->toBe([])
        ->and($page->pageNumber)->toBe(3);
});

it('refuses a file that does not exist', function (): void {
    Http::fake();

    expect(fn () => app(MistralOcr::class)->fromFile('/nowhere/manual.pdf'))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

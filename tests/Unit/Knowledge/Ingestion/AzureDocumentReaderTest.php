<?php

use App\Admin\Settings\AiProviderCheck;
use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Ingestion\Services\Parsing\AzureDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

/**
 * Documents are read by Mistral OCR or Azure Document Intelligence. Azure
 * gets the file in the request and is polled until it is done; its Markdown
 * is cut into pages by their spans, with the tables apart. Without a reader,
 * PDF, Word, slides and images cannot be uploaded.
 */
beforeEach(function (): void {
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }

    config([
        'services.azure_document_intelligence.endpoint' => null,
        'chatvia.ai.document_parser' => 'auto',
        'chatvia.knowledge.file_types' => array_column(KnowledgeFileType::cases(), 'value'),
    ]);
});

function adrConnectAzure(): void
{
    config([
        'services.azure_document_intelligence.key' => 'az-key',
        'services.azure_document_intelligence.endpoint' => 'https://docs.cognitiveservices.azure.com/',
    ]);
}

it('picks the reader: Mistral, else Azure, else none', function (): void {
    expect(app(DocumentReaders::class)->driver())->toBe('none');

    adrConnectAzure();

    expect(app(DocumentReaders::class)->driver())->toBe('azure');

    connectAiProviders(AiProvider::Mistral);

    expect(app(DocumentReaders::class)->driver())->toBe('mistral');

    config(['chatvia.ai.document_parser' => 'none']);

    expect(app(DocumentReaders::class)->driver())->toBe('none')
        ->and(app(DocumentReaders::class)->available())->toBeFalse();
});

it('needs the Azure endpoint as well as its key', function (): void {
    config(['services.azure_document_intelligence.key' => 'az-key']);

    expect(AiProvider::Azure->isConnected())->toBeFalse()
        ->and(app(DocumentReaders::class)->available())->toBeFalse();
});

it('offers PDF, Word, slides and images only with a reader', function (): void {
    $values = fn (): array => array_map(fn (KnowledgeFileType $type): string => $type->value, KnowledgeFileType::enabled());

    expect($values())->not->toContain('pdf', 'word', 'presentation', 'image')
        ->and($values())->toContain('text', 'spreadsheet', 'ebook');

    adrConnectAzure();

    expect($values())->toContain('pdf', 'word', 'presentation', 'image');
});

it('sends the file to Azure, waits for it, and reads its pages and tables', function (): void {
    Sleep::fake();
    adrConnectAzure();

    $first = "# Prices\n\nOur plan costs 10 euros.\n<!-- PageBreak -->\n";
    $second = "<table><tr><td>Plan</td></tr></table>\nRefunds within 30 days.";
    $content = $first.$second;

    Http::fake([
        'docs.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout:analyze*' => Http::response('', 202, [
            'Operation-Location' => 'https://docs.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout/analyzeResults/r1?api-version=2024-11-30',
        ]),
        'docs.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout/analyzeResults/*' => Http::sequence()
            ->push(['status' => 'running'], 200, ['Retry-After' => '1'])
            ->push(['status' => 'succeeded', 'analyzeResult' => [
                'content' => $content,
                'pages' => [
                    ['pageNumber' => 1, 'spans' => [['offset' => 0, 'length' => mb_strlen($first)]]],
                    ['pageNumber' => 2, 'spans' => [['offset' => mb_strlen($first), 'length' => mb_strlen($second)]]],
                ],
                'tables' => [[
                    'rowCount' => 2,
                    'columnCount' => 2,
                    'boundingRegions' => [['pageNumber' => 2]],
                    'cells' => [
                        ['rowIndex' => 0, 'columnIndex' => 0, 'content' => 'Plan'],
                        ['rowIndex' => 0, 'columnIndex' => 1, 'content' => 'Price'],
                        ['rowIndex' => 1, 'columnIndex' => 0, 'content' => 'Pro'],
                        ['rowIndex' => 1, 'columnIndex' => 1, 'content' => '10 €'],
                    ],
                ]],
            ]]),
    ]);

    $file = create_temp_file('prices.pdf', '%PDF-1.4 test');

    try {
        $pages = app(AzureDocumentParser::class)->parse($file, 'tmp');
    } finally {
        @unlink($file);
    }

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->content)->toContain('Prices')->toContain('Our plan costs 10 euros.')->not->toContain('PageBreak')
        ->and($pages[1]->pageNumber)->toBe(2)
        ->and($pages[1]->content)->toBe('Refunds within 30 days.')
        ->and($pages[1]->tables)->toBe(["| Plan | Price |\n| --- | --- |\n| Pro | 10 € |"]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), ':analyze')
        && str_contains($request->url(), 'outputContentFormat=markdown')
        && $request->header('Ocp-Apim-Subscription-Key') === ['az-key']
        && $request['base64Source'] === base64_encode('%PDF-1.4 test'));
    Sleep::assertSleptTimes(1);
});

it('checks an Azure key against its resource', function (): void {
    Http::fake([
        'good.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0]]),
        'bad.cognitiveservices.azure.com/*' => Http::response(['error' => ['code' => '401']], 401),
    ]);

    $check = app(AiProviderCheck::class);

    expect($check->check(AiProvider::Azure, 'az-key', 'https://good.cognitiveservices.azure.com')->connected)->toBeTrue()
        ->and($check->check(AiProvider::Azure, 'az-key', 'https://bad.cognitiveservices.azure.com')->keyRefused)->toBeTrue()
        ->and($check->check(AiProvider::Azure, 'az-key')->message)->toBe('Add the endpoint of your Azure resource too.');
});

it('warns when the Azure resource is on the free tier', function (): void {
    Http::fake([
        'free.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0, 'limit' => 500]]),
        'paid.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0, 'limit' => 5000]]),
    ]);

    $check = app(AiProviderCheck::class);
    $free = $check->check(AiProvider::Azure, 'az-key', 'https://free.cognitiveservices.azure.com');

    expect($free->connected)->toBeTrue()
        ->and($free->warning)->toContain('free tier (F0)')->toContain('first 2 pages')
        ->and($check->check(AiProvider::Azure, 'az-key', 'https://paid.cognitiveservices.azure.com')->warning)->toBeNull();
});

/** Azure accepts the file and answers with these pages (each a short text). */
function adrFakeAnalysis(int $pages = 1): void
{
    $content = implode("\n", array_map(fn (int $page): string => "Page {$page}.", range(1, $pages)));
    $offset = 0;

    Http::fake([
        'docs.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout:analyze*' => Http::response('', 202, [
            'Operation-Location' => 'https://docs.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout/analyzeResults/r1?api-version=2024-11-30',
        ]),
        'docs.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout/analyzeResults/*' => Http::response(['status' => 'succeeded', 'analyzeResult' => [
            'content' => $content,
            'pages' => array_map(function (int $page) use (&$offset): array {
                $length = mb_strlen("Page {$page}.");
                $span = ['pageNumber' => $page, 'spans' => [['offset' => $offset, 'length' => $length]]];
                $offset += $length + 1;

                return $span;
            }, range(1, $pages)),
        ]]),
    ]);
}

/** An image file of this size and format, in the temp folder. */
function adrTempImage(int $width, int $height, string $format): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    $format === 'gif' ? imagegif($image) : imagepng($image);

    return create_temp_file("image.{$format}", (string) ob_get_clean());
}

it('reads Excel with the app, as Azure loses its tables', function (): void {
    adrConnectAzure();
    Http::fake();
    $local = Mockery::mock(LocalDocumentParser::class);
    $local->shouldReceive('parse')->once()->andReturn(collect());
    app()->instance(LocalDocumentParser::class, $local);

    $file = create_temp_file('prices.xlsx', 'not really excel');

    try {
        app(AzureDocumentParser::class)->parse($file, 'tmp');
    } finally {
        @unlink($file);
    }

    Http::assertNothingSent();
});

it('sends Azure a PNG for an image format or size it does not take', function (string $format, int $width, int $height): void {
    adrConnectAzure();
    adrFakeAnalysis();
    $file = adrTempImage($width, $height, $format);

    try {
        app(AzureDocumentParser::class)->parse($file, 'tmp');
    } finally {
        @unlink($file);
    }

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), ':analyze')) {
            return false;
        }

        $size = getimagesizefromstring(base64_decode((string) $request['base64Source']));

        return $size !== false && $size['mime'] === 'image/png' && min($size[0], $size[1]) >= 50;
    });
})->with([
    'a GIF' => ['gif', 60, 60],
    'a picture under 50 pixels' => ['png', 20, 30],
]);

it('fails a PDF the free tier read only 2 pages of, rather than training part of it', function (): void {
    adrConnectAzure();
    adrFakeAnalysis(pages: 2);
    Process::fake(['*' => Process::result("Pages:          30\n")]);
    $file = create_temp_file('manual.pdf', '%PDF-1.4 test');

    try {
        expect(fn () => app(AzureDocumentParser::class)->parse($file, 'tmp'))
            ->toThrow(DocumentParsingException::class, 'Azure read only the first 2 of 30 pages');
    } finally {
        @unlink($file);
    }
});

it('shows why Azure refused a file', function (): void {
    adrConnectAzure();
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['error' => [
        'code' => 'InvalidRequest',
        'message' => 'Invalid request.',
        'innererror' => ['code' => 'InvalidContentLength', 'message' => 'The input file is too large. The limit is 4 MB.'],
    ]], 400)]);
    Process::fake(['*' => Process::result("Pages:          1\n")]);
    $file = create_temp_file('big.pdf', '%PDF-1.4 test');

    try {
        expect(fn () => app(AzureDocumentParser::class)->parse($file, 'tmp'))
            ->toThrow(DocumentParsingException::class, 'The input file is too large. The limit is 4 MB.');
    } finally {
        @unlink($file);
    }
});

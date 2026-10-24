<?php

use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Domain\Knowledge\Ingestion\Services\Parsing\AzureDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Azure Document Intelligence reads a document: it gets the file in the
 * request and is polled until it is done; its Markdown is cut into pages by
 * their spans, with the tables apart. Excel stays with the app's own reader,
 * an image Azure does not take goes as a PNG, and a refusal or a partly read
 * PDF fails with Azure's reason.
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

function adpConnectAzure(): void
{
    config([
        'services.azure_document_intelligence.key' => 'az-key',
        'services.azure_document_intelligence.endpoint' => 'https://docs.cognitiveservices.azure.com/',
    ]);
}

it('sends the file to Azure, waits for it, and reads its pages and tables', function (): void {
    Sleep::fake();
    adpConnectAzure();

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
                    ['pageNumber' => 1, 'spans' => [['offset' => 0, 'length' => Str::length($first)]]],
                    ['pageNumber' => 2, 'spans' => [['offset' => Str::length($first), 'length' => Str::length($second)]]],
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

    $pages = app(AzureDocumentParser::class)->parse(tempTestFile('prices.pdf', '%PDF-1.4 test'), 'tmp');

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->content)->toContain('Prices')->toContain('Our plan costs 10 euros.')->not->toContain('PageBreak')
        ->and($pages[1]->pageNumber)->toBe(2)
        ->and($pages[1]->content)->toBe('Refunds within 30 days.')
        ->and($pages[1]->tables)->toBe(["| Plan | Price |\n| --- | --- |\n| Pro | 10 € |"]);

    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), ':analyze')
        && Str::contains($request->url(), 'outputContentFormat=markdown')
        && $request->header('Ocp-Apim-Subscription-Key') === ['az-key']
        && $request['base64Source'] === base64_encode('%PDF-1.4 test'));
    Sleep::assertSleptTimes(1);
});

/** Azure accepts the file and answers with these pages (each a short text). */
function adpFakeAnalysis(int $pages = 1): void
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
                $length = Str::length("Page {$page}.");
                $span = ['pageNumber' => $page, 'spans' => [['offset' => $offset, 'length' => $length]]];
                $offset += $length + 1;

                return $span;
            }, range(1, $pages)),
        ]]),
    ]);
}

/** An image file of this size and format, in the temp folder for this test. */
function adpTempImage(int $width, int $height, string $format): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    $format === 'gif' ? imagegif($image) : imagepng($image);

    return tempTestFile("image.{$format}", (string) ob_get_clean());
}

it('reads Excel with the app, as Azure loses its tables', function (): void {
    adpConnectAzure();
    Http::fake();
    $local = Mockery::mock(LocalDocumentParser::class);
    $local->shouldReceive('parse')->once()->andReturn(collect());
    app()->instance(LocalDocumentParser::class, $local);

    app(AzureDocumentParser::class)->parse(tempTestFile('prices.xlsx', 'not really excel'), 'tmp');

    Http::assertNothingSent();
});

it('sends Azure a PNG for an image format or size it does not take', function (string $format, int $width, int $height): void {
    adpConnectAzure();
    adpFakeAnalysis();

    app(AzureDocumentParser::class)->parse(adpTempImage($width, $height, $format), 'tmp');

    Http::assertSent(function (Request $request): bool {
        if (! Str::contains($request->url(), ':analyze')) {
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
    adpConnectAzure();
    adpFakeAnalysis(pages: 2);
    Process::fake(['*' => Process::result("Pages:          30\n")]);
    $file = tempTestFile('manual.pdf', '%PDF-1.4 test');

    expect(fn () => app(AzureDocumentParser::class)->parse($file, 'tmp'))
        ->toThrow(DocumentParsingException::class, 'Azure read only the first 2 of 30 pages');
});

it('shows why Azure refused a file', function (): void {
    adpConnectAzure();
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['error' => [
        'code' => 'InvalidRequest',
        'message' => 'Invalid request.',
        'innererror' => ['code' => 'InvalidContentLength', 'message' => 'The input file is too large. The limit is 4 MB.'],
    ]], 400)]);
    Process::fake(['*' => Process::result("Pages:          1\n")]);
    $file = tempTestFile('big.pdf', '%PDF-1.4 test');

    expect(fn () => app(AzureDocumentParser::class)->parse($file, 'tmp'))
        ->toThrow(DocumentParsingException::class, 'The input file is too large. The limit is 4 MB.');
});

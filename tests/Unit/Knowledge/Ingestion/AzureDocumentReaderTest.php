<?php

use App\Admin\Settings\AiProviderCheck;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Services\Clients\AzureDocumentIntelligence;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Ingestion\Services\Parsing\AzureDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\MistralDocumentParser;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

function connectAzure(): void
{
    config([
        'services.azure_document_intelligence.key' => 'az-key',
        'services.azure_document_intelligence.endpoint' => 'https://docs.cognitiveservices.azure.com/',
    ]);
}

it('picks the reader: Mistral, else Azure, else none', function (): void {
    expect(app(DocumentReaders::class)->driver())->toBe('none')
        ->and(app(DocumentParser::class))->toBeInstanceOf(LocalDocumentParser::class);

    connectAzure();

    expect(app(DocumentReaders::class)->driver())->toBe('azure')
        ->and(app(DocumentParser::class))->toBeInstanceOf(AzureDocumentParser::class)
        ->and(app(DocumentParser::class)->name())->toBe('azure:prebuilt-layout');

    connectAiProviders(AiProvider::Mistral);

    expect(app(DocumentParser::class))->toBeInstanceOf(MistralDocumentParser::class);

    config(['chatvia.ai.document_parser' => 'none']);

    expect(app(DocumentReaders::class)->available())->toBeFalse();
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

    connectAzure();

    expect($values())->toContain('pdf', 'word', 'presentation', 'image');
});

it('sends the file to Azure, waits for it, and reads its pages and tables', function (): void {
    Sleep::fake();
    connectAzure();

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

    expect(AzureDocumentIntelligence::READS)->toContain('pdf', 'docx', 'pptx', 'xlsx', 'png');
});

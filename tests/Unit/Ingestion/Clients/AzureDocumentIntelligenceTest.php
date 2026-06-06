<?php

use Domain\Knowledge\Ingestion\Services\Clients\AzureDocumentIntelligence;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Wire-format coverage for the Azure Document Intelligence client (review's #3
 * named gap: external OCR/parse clients). Two HTTP seams to pin:
 *   - analyzeDocument()  → POST, returns the Operation-Location header
 *   - getAnalysisResult() → GET the operation location, returns json body
 *
 * Authenticates via Ocp-Apim-Subscription-Key (Azure uses this instead of bearer).
 */
beforeEach(function (): void {
    config()->set('services.azure_document_intelligence.endpoint', 'https://example.cognitiveservices.azure.com/');
    config()->set('services.azure_document_intelligence.api_key', 'azure-test-key');
});

it('posts the analyze envelope, sends the subscription key, and returns the Operation-Location header', function (): void {
    Http::fake([
        'example.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-read:analyze*' => Http::response(
            ['status' => 'accepted'],
            202,
            ['Operation-Location' => 'https://example.cognitiveservices.azure.com/operations/abc-123'],
        ),
    ]);

    $operationLocation = app(AzureDocumentIntelligence::class)
        ->analyzeDocument('https://files.test/manual.pdf');

    expect($operationLocation)->toBe('https://example.cognitiveservices.azure.com/operations/abc-123');

    Http::assertSent(function (Request $request): bool {
        $url = $request->url();

        return $request->method() === 'POST'
            && str_starts_with($url, 'https://example.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-read:analyze')
            && str_contains($url, 'api-version=2024-11-30')
            && str_contains($url, 'outputContentFormat=markdown')
            && $request->hasHeader('Ocp-Apim-Subscription-Key', 'azure-test-key')
            && $request['urlSource'] === 'https://files.test/manual.pdf';
    });
});

it('honours a custom modelId in the analyze URL path', function (): void {
    Http::fake([
        'example.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-layout:analyze*' => Http::response(
            ['status' => 'accepted'],
            202,
            ['Operation-Location' => 'https://example.cognitiveservices.azure.com/operations/xyz-999'],
        ),
    ]);

    app(AzureDocumentIntelligence::class)
        ->analyzeDocument('https://files.test/x.pdf', modelId: 'prebuilt-layout');

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'documentModels/prebuilt-layout:analyze'));
});

it('throws on a non-2xx analyze response with status + body in the message', function (): void {
    Http::fake([
        'example.cognitiveservices.azure.com/*' => Http::response(['error' => 'forbidden'], 403),
    ]);

    expect(fn () => app(AzureDocumentIntelligence::class)->analyzeDocument('https://files.test/x.pdf'))
        ->toThrow(RuntimeException::class, '403');
});

it('getAnalysisResult GETs the operation location and returns the json body', function (): void {
    Http::fake([
        'example.cognitiveservices.azure.com/operations/done-1' => Http::response([
            'status' => 'succeeded',
            'analyzeResult' => ['pages' => [['pageNumber' => 1, 'content' => 'hello']]],
        ], 200),
    ]);

    $result = app(AzureDocumentIntelligence::class)
        ->getAnalysisResult('https://example.cognitiveservices.azure.com/operations/done-1');

    expect($result['status'])->toBe('succeeded')
        ->and($result['analyzeResult']['pages'][0]['content'])->toBe('hello');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
        && $r->url() === 'https://example.cognitiveservices.azure.com/operations/done-1'
        && $r->hasHeader('Ocp-Apim-Subscription-Key', 'azure-test-key'));
});

it('getAnalysisResult resolves a SourceFile by reading its azure_operation_location metadata', function (): void {
    Http::fake([
        '*operations/from-meta*' => Http::response(['status' => 'succeeded'], 200),
    ]);

    $file = new SourceFile;
    $file->metadata = ['azure_operation_location' => 'https://example.cognitiveservices.azure.com/operations/from-meta'];

    app(AzureDocumentIntelligence::class)->getAnalysisResult($file);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'operations/from-meta'));
});

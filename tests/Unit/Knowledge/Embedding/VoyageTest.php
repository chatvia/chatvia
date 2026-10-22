<?php

use Domain\Knowledge\Embedding\Voyage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Embeddings from Voyage AI: documents and questions with their own
 * `input_type`, the stored size, the input order, and batches small enough
 * for Voyage's limit on tokens per request.
 */
beforeEach(function (): void {
    Cache::flush();
    config([
        'chatvia.embeddings.dimensions' => 1024,
        'services.voyage.api_key' => 'pa-test',
        'services.voyage.api_endpoint' => 'https://api.voyageai.com/v1',
        'services.voyage.embedding_model' => 'voyage-4',
    ]);
});

it('embeds documents as documents, at the stored size, and keeps the input order', function (): void {
    Http::fake(['api.voyageai.com/v1/embeddings' => Http::response(['data' => [
        ['index' => 1, 'embedding' => [0.2, 0.4]],
        ['index' => 0, 'embedding' => [0.1, 0.3]],
    ]])]);

    expect(app(Voyage::class)->embedBatch(['first', 'second']))->toBe([[0.1, 0.3], [0.2, 0.4]]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.voyageai.com/v1/embeddings'
        && $request->header('Authorization') === ['Bearer pa-test']
        && $request['model'] === 'voyage-4'
        && $request['input'] === ['first', 'second']
        && $request['input_type'] === 'document'
        && $request['output_dimension'] === 1024);
});

it('embeds a question as a query', function (): void {
    Http::fake(['api.voyageai.com/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [0.5, 0.6]]]])]);

    expect(app(Voyage::class)->embedQuery('How do I reset my password?'))->toBe([0.5, 0.6]);

    Http::assertSent(fn (Request $request): bool => $request['input'] === ['How do I reset my password?']
        && $request['input_type'] === 'query');
});

it('splits many documents over several requests', function (): void {
    Http::fake(['api.voyageai.com/v1/embeddings' => fn (Request $request) => Http::response(['data' => array_map(
        fn (int $index): array => ['index' => $index, 'embedding' => [(float) $index]],
        array_keys($request['input']),
    )])]);

    $vectors = app(Voyage::class)->embedBatch(array_map(fn (int $i): string => "text {$i}", range(1, 100)));

    expect($vectors)->toHaveCount(100);
    Http::assertSentCount(2);
});

it('returns only the sizes the voyage-4 models make', function (int $stored, bool $fits): void {
    config(['chatvia.embeddings.dimensions' => $stored]);

    expect(app(Voyage::class)->fitsStorage())->toBe($fits);
})->with([
    '256' => [256, true],
    '1024' => [1024, true],
    '2048' => [2048, true],
    '768' => [768, false],
    '1536' => [1536, false],
]);

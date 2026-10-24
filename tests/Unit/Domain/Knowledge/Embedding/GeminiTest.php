<?php

use Domain\Knowledge\Embedding\Gemini;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The Google Gemini embedding service: documents and questions each get
 * their own task, the vectors come back scaled to length 1, and a big batch
 * goes as several requests of at most 100 texts.
 */
beforeEach(function (): void {
    config([
        'chatvia.embeddings.dimensions' => 256,
        'services.gemini.api_key' => 'gm-test',
        'services.gemini.api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'services.gemini.embedding_model' => 'gemini-embedding-001',
    ]);
});

it('embeds documents and questions with their own Gemini task, scaled to length 1', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embeddings' => [
        ['values' => [0.3, 0.4]],
    ]])]);

    $gemini = app(Gemini::class);

    expect($gemini->embed('A document'))->toEqualWithDelta([0.6, 0.8], 1e-9)
        ->and($gemini->embedQuery('A question?'))->toEqualWithDelta([0.6, 0.8], 1e-9);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents'
        && $request->header('x-goog-api-key') === ['gm-test']
        && $request['requests'][0]['model'] === 'models/gemini-embedding-001'
        && $request['requests'][0]['content'] === ['parts' => [['text' => 'A document']]]
        && $request['requests'][0]['taskType'] === 'RETRIEVAL_DOCUMENT'
        && $request['requests'][0]['outputDimensionality'] === 256);

    Http::assertSent(fn (Request $request): bool => $request['requests'][0]['taskType'] === 'RETRIEVAL_QUERY');
});

it('sends Gemini at most 100 texts per request', function (): void {
    Http::fake(fn (Request $request) => Http::response(['embeddings' => array_map(
        fn (): array => ['values' => [0.6, 0.8]],
        $request['requests'],
    )]));

    expect(app(Gemini::class)->embedBatch(array_fill(0, 150, 'text')))->toHaveCount(150);

    Http::assertSentCount(2);
});

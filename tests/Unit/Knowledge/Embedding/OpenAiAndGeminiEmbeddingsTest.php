<?php

use Domain\Knowledge\Embedding\Gemini;
use Domain\Knowledge\Embedding\OpenAi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The OpenAI and Google Gemini embedding services: what each request asks
 * for (the model, the stored size, the task) and how the vectors come back
 * (in input order, Gemini's scaled to length 1).
 */
beforeEach(function (): void {
    config([
        'chatvia.embeddings.dimensions' => 256,
        'services.openai.api_key' => 'sk-test',
        'services.openai.api_endpoint' => 'https://api.openai.com/v1',
        'services.openai.embedding_model' => 'text-embedding-3-small',
        'services.gemini.api_key' => 'gm-test',
        'services.gemini.api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'services.gemini.embedding_model' => 'gemini-embedding-001',
    ]);
});

it('asks OpenAI for the stored size and keeps the input order', function (): void {
    Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [
        ['index' => 1, 'embedding' => [0.2, 0.4]],
        ['index' => 0, 'embedding' => [0.1, 0.3]],
    ]])]);

    expect(app(OpenAi::class)->embedBatch(['first', 'second']))
        ->toBe([[0.1, 0.3], [0.2, 0.4]]);

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer sk-test']
        && $request['model'] === 'text-embedding-3-small'
        && $request['input'] === ['first', 'second']
        && $request['dimensions'] === 256);
});

it('knows the sizes each OpenAI model can return', function (): void {
    config(['chatvia.embeddings.dimensions' => 3072]);

    expect(app(OpenAi::class)->fitsStorage())->toBeFalse();

    config(['services.openai.embedding_model' => 'text-embedding-3-large']);

    expect(app(OpenAi::class)->fitsStorage())->toBeTrue();
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

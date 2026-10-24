<?php

use Domain\Knowledge\Embedding\OpenAi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The OpenAI embedding service: what each request asks for (the model, the
 * stored size), the vectors in input order, and which models fit the stored
 * size.
 */
beforeEach(function (): void {
    config([
        'chatvia.embeddings.dimensions' => 256,
        'services.openai.api_key' => 'sk-test',
        'services.openai.api_endpoint' => 'https://api.openai.com/v1',
        'services.openai.embedding_model' => 'text-embedding-3-small',
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

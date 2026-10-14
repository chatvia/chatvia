<?php

use Domain\Knowledge\Embedding\Enums\EmbeddingCheckStatus;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A test embedding with a key and model typed but not saved: it checks the
 * key, the model and the vector size, and puts the saved config back.
 */
it('embeds with the typed key and model, then puts the saved ones back', function (): void {
    config(['services.cohere.api_key' => 'saved-key']);
    Http::fake(['api.cohere.com/v2/embed' => Http::response(['embeddings' => ['float' => [array_fill(0, 1024, 0.1)]]])]);

    $result = app(EmbeddingCheck::class)->check(EmbeddingProvider::Cohere, 'embed-v4.0', 'typed-key', 1024);

    expect($result->ok())->toBeTrue()
        ->and($result->returned)->toBe(1024)
        ->and(config('services.cohere.api_key'))->toBe('saved-key');

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer typed-key']);
});

it('says what went wrong', function (int $status, ?array $body, ?int $stored, EmbeddingCheckStatus $expected): void {
    Http::fake(['api.cohere.com/v2/embed' => Http::response($body ?? ['message' => 'no'], $status)]);

    expect(app(EmbeddingCheck::class)->check(EmbeddingProvider::Cohere, apiKey: 'key', dimensions: $stored)->status)->toBe($expected);
})->with([
    'a refused key' => [401, null, null, EmbeddingCheckStatus::KeyRefused],
    'an unknown model' => [400, null, null, EmbeddingCheckStatus::ModelFailed],
    'another size' => [200, ['embeddings' => ['float' => [array_fill(0, 512, 0.1)]]], 1024, EmbeddingCheckStatus::WrongSize],
]);

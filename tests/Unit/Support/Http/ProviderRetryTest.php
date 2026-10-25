<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Support\Http\ProviderRetry;

/**
 * A provider that turns a call away for a moment (429, 502, 503, 504) is
 * asked again, waiting as long as its Retry-After says (capped) or a short,
 * doubling backoff. Other errors are answered at once, as before.
 */
beforeEach(function (): void {
    Sleep::fake();
});

it('asks again after a rate limit and returns the answer', function (): void {
    Http::fake(['ai.test/*' => Http::sequence()
        ->push(['error' => 'slow down'], 429, ['Retry-After' => '2'])
        ->push(['ok' => true], 200)]);

    $response = ProviderRetry::request()->post('https://ai.test/v1/embeddings');

    expect($response->json('ok'))->toBeTrue();
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration): bool => (int) $duration->totalMilliseconds === 2000);
});

it('gives the last answer, not an exception, when every try is turned away', function (): void {
    Http::fake(['ai.test/*' => Http::response(['error' => 'busy'], 503)]);

    $response = ProviderRetry::request()->post('https://ai.test/v1/embeddings');

    expect($response->status())->toBe(503);
    Http::assertSentCount(ProviderRetry::TRIES);
});

it('does not ask again after a refused key', function (): void {
    Http::fake(['ai.test/*' => Http::response(['error' => 'bad key'], 401)]);

    expect(ProviderRetry::request()->post('https://ai.test/v1/embeddings')->status())->toBe(401);
    Http::assertSentCount(1);
});

it('doubles the backoff and caps a long Retry-After', function (): void {
    $exception = fn (array $headers): RequestException => new RequestException(new Response(new GuzzleHttp\Psr7\Response(429, $headers)));

    expect(ProviderRetry::delay(1, $exception([])))->toBe(500)
        ->and(ProviderRetry::delay(2, $exception([])))->toBe(1000)
        ->and(ProviderRetry::delay(1, $exception(['Retry-After' => '120'])))->toBe(10_000);
});

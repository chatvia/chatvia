<?php

use Domain\Knowledge\Ingestion\Services\TokenCounter;
use Domain\Llm\Services\TikToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Token counts come from the chunker with the tokenizer chosen in the admin
 * panel, and from the built-in tokenizer (same tokenizer) when the chunker
 * is not set up or cannot count.
 */
beforeEach(function (): void {
    config([
        'services.chunkfit.base_url' => 'http://chunker:8000',
        'services.chunkfit.api_key' => null,
        'chatvia.ai.tokenizer' => 'o200k_base',
    ]);
});

it('counts with the chunker, in one request, with the chosen tokenizer', function (): void {
    Http::fake(['chunker:8000/v1/tokens' => Http::response(['encoding' => 'o200k_base', 'data' => [
        ['index' => 1, 'tokens' => 2],
        ['index' => 0, 'tokens' => 5],
    ]])]);

    expect(app(TokenCounter::class)->countMany(['first text', 'second']))->toBe([5, 2]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://chunker:8000/v1/tokens'
        && $request['input'] === ['first text', 'second']
        && $request['encoding'] === 'o200k_base');
});

it('counts with the built-in tokenizer when no chunker is set up', function (): void {
    config(['services.chunkfit.base_url' => '']);
    Http::fake();

    expect(app(TokenCounter::class)->count('Hello world'))->toBe(TikToken::countTokens('Hello world'));

    Http::assertNothingSent();
});

it('falls back, and stops asking a chunker that cannot count', function (): void {
    Http::fake(['chunker:8000/v1/tokens' => Http::response(['detail' => 'Not Found'], 404)]);

    $counter = app(TokenCounter::class);

    expect($counter->count('Hello world'))->toBe(TikToken::countTokens('Hello world'))
        ->and($counter->count('Again'))->toBe(TikToken::countTokens('Again'));

    Http::assertSentCount(1);
});

it('falls back for one count when the chunker fails, and asks again next time', function (): void {
    Http::fake(['chunker:8000/v1/tokens' => Http::sequence()
        ->push('Bad gateway', 502)
        ->push(['data' => [['index' => 0, 'tokens' => 9]]])]);

    $counter = app(TokenCounter::class);

    expect($counter->count('Hello world'))->toBe(TikToken::countTokens('Hello world'))
        ->and($counter->count('Hello world'))->toBe(9);
});

it('uses the chosen tokenizer for the built-in count', function (): void {
    $text = 'Tokenizers split words like antidisestablishmentarianism differently.';

    config(['chatvia.ai.tokenizer' => 'cl100k_base']);
    $cl100k = TikToken::countTokens($text);
    config(['chatvia.ai.tokenizer' => 'r50k_base']);
    $r50k = TikToken::countTokens($text);
    config(['chatvia.ai.tokenizer' => 'not-a-tokenizer']);

    expect($cl100k)->not->toBe($r50k)
        ->and(TikToken::encoding())->toBe('cl100k_base');
});

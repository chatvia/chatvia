<?php

use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/**
 * The rewriter asks the model for `<lang>|<query>`. A reply without the pipe
 * (or a failed call) used to throw an "undefined array key" error and fail the
 * chat turn; it must fall back gracefully instead.
 */
beforeEach(function (): void {
    config([
        'services.scalewayai.api_key' => 'test-key',
        'chatvia.ai.provider' => 'scaleway',
        'chatvia.ai.query.rewriter.enabled' => true,
    ]);
});

function fakeRewriterReply(?string $content): void
{
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);
}

it('splits a well-formed reply into language and query', function (): void {
    fakeRewriterReply('fr|quels sont les horaires ?');

    expect(app(QueryRewriter::class)->rewrite('horaires ?'))
        ->toBe(['lang' => 'fr', 'query' => 'quels sont les horaires ?']);
});

it('keeps pipes that are part of the rewritten query', function (): void {
    fakeRewriterReply('en|compare plan A | plan B');

    expect(app(QueryRewriter::class)->rewrite('A vs B'))
        ->toBe(['lang' => 'en', 'query' => 'compare plan A | plan B']);
});

it('uses the reply as the query when it has no language prefix', function (): void {
    fakeRewriterReply('what are the opening hours?');

    expect(app(QueryRewriter::class)->rewrite('hours?'))
        ->toBe(['lang' => null, 'query' => 'what are the opening hours?']);
});

it('falls back to the original query on an empty reply', function (?string $reply): void {
    fakeRewriterReply($reply);

    expect(app(QueryRewriter::class)->rewrite('hours?')['query'])->toBe('hours?');
})->with(['empty' => [''], 'null' => [null], 'only a language' => ['en|']]);

it('reports a failed call and keeps the original query', function (): void {
    Exceptions::fake();
    Http::fake(['*' => Http::response('upstream error', 500)]);

    expect(app(QueryRewriter::class)->rewrite('hours?'))
        ->toBe(['lang' => null, 'query' => 'hours?']);

    Exceptions::assertReported(RequestException::class);
});

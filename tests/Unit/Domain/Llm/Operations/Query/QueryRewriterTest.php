<?php

declare(strict_types=1);

use Domain\Llm\Data\Query\RewrittenQueryData;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The rewriter turns a follow-up into a full question from the chat before
 * it, asking the model for `<lang>|<question>`. A message with no chat before
 * it is asked about only for its language. With clarifying on, a message too
 * vague to search comes back as `<lang>|?|<question for the visitor>`. A
 * reply without the pipe, or a failed call, must fall back gracefully instead
 * of failing the chat turn.
 */
beforeEach(function (): void {
    config(['chatvia.ai.query.rewriter.enabled' => true]);
});

/** A short chat before the message. */
function qrwHistory(): array
{
    return [
        ['role' => 'user', 'content' => 'How long does express delivery take?'],
        ['role' => 'assistant', 'content' => "One to two\nworking days."],
    ];
}

it('splits a well-formed reply into language and query', function (): void {
    fakeLlmCompletion('fr|quels sont les horaires ?');

    expect(app(QueryRewriter::class)->rewrite('horaires ?', qrwHistory()))
        ->toEqual(new RewrittenQueryData(language: 'fr', query: 'quels sont les horaires ?'));
});

it('keeps pipes that are part of the rewritten query', function (): void {
    fakeLlmCompletion('en|compare plan A | plan B');

    expect(app(QueryRewriter::class)->rewrite('A vs B', qrwHistory()))
        ->toEqual(new RewrittenQueryData(language: 'en', query: 'compare plan A | plan B'));
});

it('uses the reply as the query when it has no language prefix', function (): void {
    fakeLlmCompletion('what are the opening hours?');

    expect(app(QueryRewriter::class)->rewrite('hours?', qrwHistory()))
        ->toEqual(new RewrittenQueryData(language: null, query: 'what are the opening hours?'));
});

it('falls back to the original query on an empty reply', function (?string $reply): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $reply]]]])]);

    expect(app(QueryRewriter::class)->rewrite('hours?', qrwHistory())->query)->toBe('hours?');
})->with(['empty' => [''], 'null' => [null], 'only a language' => ['en|']]);

it('reports a failed call and keeps the original query', function (): void {
    Exceptions::fake();
    Http::fake(['*' => Http::response('upstream error', 500)]);

    expect(app(QueryRewriter::class)->rewrite('hours?', qrwHistory()))
        ->toEqual(RewrittenQueryData::unchanged('hours?'));

    Exceptions::assertReported(RequestException::class);
});

it('sends the last turns of the chat and the message', function (): void {
    fakeLlmCompletion('en|How much does express delivery cost?');

    expect(app(QueryRewriter::class)->rewrite('and how much is it?', qrwHistory())->query)->toBe('How much does express delivery cost?');

    Http::assertSent(fn (Request $request): bool => Str::contains((string) json_encode($request->data()), 'Visitor: How long does express delivery take?\\nAgent: One to two working days.')
        && Str::contains((string) json_encode($request->data()), 'Last message: and how much is it?'));
});

it('asks for the language of a message with no chat before it and keeps the message as the query', function (): void {
    fakeLlmCompletion('en|summary of the insurance');

    expect(app(QueryRewriter::class)->rewrite('summary', []))
        ->toEqual(new RewrittenQueryData(language: 'en', query: 'summary'));

    Http::assertSent(fn (Request $request): bool => Str::contains((string) json_encode($request->data()), 'Chat:\\n(no earlier messages)')
        && Str::contains((string) json_encode($request->data()), 'Last message: summary'));
});

it('reads a question for the visitor and searches the message as it is', function (array $history): void {
    config(['chatvia.ai.query.rewriter.clarify' => true]);
    fakeLlmCompletion('en|?|What would you like to know about 8675309?');

    expect(app(QueryRewriter::class)->rewrite('8675309', $history))
        ->toEqual(new RewrittenQueryData(language: 'en', query: '8675309', clarifyingQuestion: 'What would you like to know about 8675309?'));

    Http::assertSent(fn (Request $request): bool => Str::contains((string) json_encode($request->data()), 'too vague to search'));
})->with(['first message' => [[]], 'follow-up' => [fn (): array => qrwHistory()]]);

it('leaves the vague-message rule out of the prompt while clarifying is off', function (): void {
    fakeLlmCompletion('en|8675309');

    app(QueryRewriter::class)->rewrite('8675309', qrwHistory());

    Http::assertSent(fn (Request $request): bool => ! Str::contains((string) json_encode($request->data()), 'too vague to search'));
});

it('makes no call when switched off', function (array $history): void {
    Http::fake();
    config(['chatvia.ai.query.rewriter.enabled' => false]);

    expect(app(QueryRewriter::class)->rewrite('hours?', $history))->toEqual(RewrittenQueryData::unchanged('hours?'));

    Http::assertNothingSent();
})->with(['first message' => [[]], 'follow-up' => [fn (): array => qrwHistory()]]);

<?php

declare(strict_types=1);

use Domain\Llm\Data\Query\RewrittenQueryData;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

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

/**
 * A short chat before the message.
 *
 * @return list<array{role: string, content: string}>
 */
function qrwHistory(): array
{
    return [
        ['role' => 'user', 'content' => 'How long does express delivery take?'],
        ['role' => 'assistant', 'content' => "One to two\nworking days."],
    ];
}

it('reads the language, the question and a question for the visitor from the reply, keeping the message when the reply gives no query', function (string $message, array $history, ?string $reply, RewrittenQueryData $expected): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $reply]]]])]);

    expect(app(QueryRewriter::class)->rewrite($message, $history))->toEqual($expected);
})->with([
    'language and query' => ['horaires ?', qrwHistory(), 'fr|quels sont les horaires ?', new RewrittenQueryData(language: 'fr', query: 'quels sont les horaires ?')],
    'pipes inside the query' => ['A vs B', qrwHistory(), 'en|compare plan A | plan B', new RewrittenQueryData(language: 'en', query: 'compare plan A | plan B')],
    'no language prefix' => ['hours?', qrwHistory(), 'what are the opening hours?', new RewrittenQueryData(language: null, query: 'what are the opening hours?')],
    'an empty reply' => ['hours?', qrwHistory(), null, RewrittenQueryData::unchanged('hours?')],
    'only a language' => ['hours?', qrwHistory(), 'en|', new RewrittenQueryData(language: 'en', query: 'hours?')],
    'a question for the visitor' => ['8675309', qrwHistory(), 'en|?|What would you like to know about 8675309?', new RewrittenQueryData(language: 'en', query: '8675309', clarifyingQuestion: 'What would you like to know about 8675309?')],
    'no chat before the message' => ['summary', [], 'en|summary of the insurance', new RewrittenQueryData(language: 'en', query: 'summary')],
]);

it('keeps the message and reports a failed call, and makes no call while switched off', function (): void {
    Exceptions::fake();
    Http::fake(['*' => Http::response('upstream error', 500)]);

    $failed = app(QueryRewriter::class)->rewrite('hours?', qrwHistory());
    config(['chatvia.ai.query.rewriter.enabled' => false]);
    $off = app(QueryRewriter::class)->rewrite('hours?', qrwHistory());

    expect($failed)->toEqual(RewrittenQueryData::unchanged('hours?'))
        ->and($off)->toEqual(RewrittenQueryData::unchanged('hours?'));
    Exceptions::assertReported(RequestException::class);
    Http::assertSentCount(1);
});

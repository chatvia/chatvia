<?php

use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Domain\Conversation\Services\Turn\TurnStops;
use Tests\Support\Conversation\TestChatContext;

/**
 * A Stop names its turn by the scoped request id; the turn ends at its next
 * look, once, and only that turn does.
 */
it('ends the turn whose Stop was asked for, once', function (): void {
    $stops = app(TurnStops::class);
    $context = TestChatContext::make(requestId: 'chat.a.s.c.r1');

    $stops->request('chat.a.s.c.r1');

    expect(fn () => $stops->throwIfStopped($context))->toThrow(TurnStopped::class)
        ->and(fn () => $stops->throwIfStopped($context))->not->toThrow(TurnStopped::class);
});

it('never ends another turn, nor one without a request id', function (): void {
    $stops = app(TurnStops::class);
    $stops->request('chat.a.s.c.r1');

    expect(fn () => $stops->throwIfStopped(TestChatContext::make(requestId: 'chat.a.s.c.r2')))->not->toThrow(TurnStopped::class)
        ->and(fn () => $stops->throwIfStopped(TestChatContext::bare()))->not->toThrow(TurnStopped::class);
});

<?php

use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Domain\Conversation\Services\Turn\TurnStops;
use Illuminate\Support\Carbon;
use Tests\Support\Conversation\TestChatContext;

/**
 * A Stop names its turn by the scoped request id; the turn ends at its next
 * look, and only that turn does. The answer's read loop looks at most every
 * half second.
 */
it('ends the turn whose Stop was asked for, once', function (): void {
    $stops = app(TurnStops::class);
    $context = TestChatContext::make(requestId: 'chat.a.s.c.r1');

    $stops->request('chat.a.s.c.r1');

    expect(fn () => $stops->throwIfStopped($context))->toThrow(TurnStopped::class);
    $stops->throwIfStopped($context);
});

it('never ends another turn', function (): void {
    $stops = app(TurnStops::class);
    $stops->request('chat.a.s.c.r1');

    $stops->throwIfStopped(TestChatContext::make(requestId: 'chat.a.s.c.r2'));

    expect(true)->toBeTrue();
});

it('lets a turn without a request id run', function (): void {
    app(TurnStops::class)->throwIfStopped(TestChatContext::bare());

    expect(true)->toBeTrue();
});

it('looks at most every half second from the read loop', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00.000');
    $stops = new TurnStops;
    $context = TestChatContext::make(requestId: 'chat.a.s.c.r1');

    $stops->throwIfStoppedLately($context);
    $stops->request('chat.a.s.c.r1');

    Carbon::setTestNow('2026-10-26 12:00:00.300');
    $stops->throwIfStoppedLately($context);

    Carbon::setTestNow('2026-10-26 12:00:00.600');
    expect(fn () => $stops->throwIfStoppedLately($context))->toThrow(TurnStopped::class);
});

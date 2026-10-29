<?php

use Domain\Conversation\Services\Turn\RunningTurns;

/**
 * A conversation counts as answering from the moment its turn starts until
 * the turn's events end or fail, and only that conversation does.
 */
it('marks only the conversation as running from the start of the turn until its events end', function (): void {
    $turns = app(RunningTurns::class);
    $seen = [];

    $events = $turns->track('c1', function () use ($turns, &$seen): Generator {
        $seen[] = $turns->isRunning('c1');

        return (function () use ($turns, &$seen): Generator {
            yield 'a';
            $seen[] = $turns->isRunning('c1');
        })();
    });
    $runningBefore = [$turns->isRunning('c1'), $turns->isRunning('c2')];
    iterator_to_array($events);

    expect($runningBefore)->toBe([true, false])
        ->and($seen)->toBe([true, true])
        ->and($turns->isRunning('c1'))->toBeFalse();
});

it('stops counting a turn that fails, before or after its first event', function (): void {
    $turns = app(RunningTurns::class);
    $events = $turns->track('c2', fn (): Generator => (function (): Generator {
        yield 'a';
        throw new RuntimeException('boom');
    })());

    expect(fn () => $turns->track('c1', fn (): Generator => throw new RuntimeException('boom')))->toThrow(RuntimeException::class)
        ->and($turns->isRunning('c1'))->toBeFalse()
        ->and(fn () => iterator_to_array($events))->toThrow(RuntimeException::class)
        ->and($turns->isRunning('c2'))->toBeFalse();
});

<?php

use Domain\Conversation\Services\Turn\RunningTurns;

/**
 * A conversation counts as answering from the moment its turn starts until
 * the turn's events end, fail or are dropped.
 */
it('marks the conversation as running from the start of the turn until its events end', function (): void {
    $turns = app(RunningTurns::class);
    $seen = [];

    $events = $turns->track('c1', function () use ($turns, &$seen): Generator {
        $seen[] = $turns->isRunning('c1');

        return (function () use ($turns, &$seen): Generator {
            yield 'a';
            $seen[] = $turns->isRunning('c1');
        })();
    });

    expect($turns->isRunning('c1'))->toBeTrue();

    iterator_to_array($events);

    expect($seen)->toBe([true, true])
        ->and($turns->isRunning('c1'))->toBeFalse();
});

it('stops counting a turn that fails before its first event', function (): void {
    $turns = app(RunningTurns::class);

    expect(fn () => $turns->track('c1', fn (): Generator => throw new RuntimeException('boom')))->toThrow(RuntimeException::class)
        ->and($turns->isRunning('c1'))->toBeFalse();
});

it('stops counting a turn that fails', function (): void {
    $turns = app(RunningTurns::class);

    $events = $turns->track('c1', fn (): Generator => (function (): Generator {
        yield 'a';
        throw new RuntimeException('boom');
    })());

    expect(fn () => iterator_to_array($events))->toThrow(RuntimeException::class)
        ->and($turns->isRunning('c1'))->toBeFalse();
});

it('stops counting a turn whose stream was dropped part way', function (): void {
    $turns = app(RunningTurns::class);

    $events = $turns->track('c1', fn (): Generator => (function (): Generator {
        yield 'a';
        yield 'b';
    })());
    $events->current();

    expect($turns->isRunning('c1'))->toBeTrue();

    unset($events);
    gc_collect_cycles();

    expect($turns->isRunning('c1'))->toBeFalse();
});

it('keeps other conversations apart', function (): void {
    $turns = app(RunningTurns::class);

    $turns->track('c1', fn (): Generator => (function (): Generator {
        yield 'a';
    })());

    expect($turns->isRunning('c2'))->toBeFalse();
});

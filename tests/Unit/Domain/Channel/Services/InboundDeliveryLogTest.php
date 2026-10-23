<?php

use Domain\Channel\Services\InboundDeliveryLog;

/**
 * The inbound dedupe every channel webhook uses: an event runs once, a repeat
 * is skipped, and an event whose dispatch failed gives its claim back so the
 * provider's retry still gets queued.
 */
it('runs the first delivery of an event and skips a repeat', function (): void {
    $log = new InboundDeliveryLog;
    $runs = 0;

    $log->once('slack:event:1:Ev1', now()->addDay(), function () use (&$runs): void {
        $runs++;
    });
    $log->once('slack:event:1:Ev1', now()->addDay(), function () use (&$runs): void {
        $runs++;
    });

    expect($runs)->toBe(1);
});

it('gives the claim back when the dispatch throws, so the retry runs', function (): void {
    $log = new InboundDeliveryLog;
    $runs = 0;

    expect(fn () => $log->once('slack:event:1:Ev2', now()->addDay(), function (): void {
        throw new RuntimeException('Queue is down');
    }))->toThrow(RuntimeException::class, 'Queue is down');

    $log->once('slack:event:1:Ev2', now()->addDay(), function () use (&$runs): void {
        $runs++;
    });

    expect($runs)->toBe(1);
});

it('always runs an event without an id, as a repeat cannot be told apart', function (): void {
    $log = new InboundDeliveryLog;
    $runs = 0;

    $log->once(null, now()->addDay(), function () use (&$runs): void {
        $runs++;
    });
    $log->once(null, now()->addDay(), function () use (&$runs): void {
        $runs++;
    });

    expect($runs)->toBe(2);
});

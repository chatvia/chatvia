<?php

declare(strict_types=1);

use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Illuminate\Support\Facades\Cache;

/**
 * A finished answer is kept by request id for a few minutes, encrypted, so a
 * retried request can replay it.
 */
it('returns null for an unknown request id', function (): void {
    expect(new RequestDedupeStore()->find('req-missing'))->toBeNull();
});

it('remembers and reads a snapshot', function (): void {
    $store = new RequestDedupeStore;

    $store->remember(
        requestId: 'req-1',
        assistantMessageId: 'msg-1',
        content: 'Hello world',
        language: 'en',
        sources: [['id' => 7]],
    );

    $snapshot = $store->find('req-1');

    expect($snapshot->assistant_message_id)->toBe('msg-1')
        ->and($snapshot->content)->toBe('Hello world')
        ->and($snapshot->language)->toBe('en')
        ->and($snapshot->sources)->toBe([['id' => 7]]);
});

it('forgets a snapshot', function (): void {
    $store = new RequestDedupeStore;

    $store->remember('req-1', 'msg-1', 'content', null, []);
    $store->forget('req-1');

    expect($store->find('req-1'))->toBeNull();
});

it('drops the snapshot once the configured time to live has passed', function (): void {
    config(['chatvia.sse.dedupe.ttl_seconds' => 60]);
    $store = new RequestDedupeStore;

    $store->remember('req-1', 'msg-1', 'Hello world', null);

    $this->travel(59)->seconds();
    expect($store->find('req-1'))->not->toBeNull();

    $this->travel(2)->seconds();
    expect($store->find('req-1'))->toBeNull();
});

it('stores the answer encrypted, never as readable text', function (): void {
    new RequestDedupeStore()->remember('req-1', 'msg-1', 'The secret answer', 'en');

    $raw = Cache::store()->get('chat:req:req-1');

    expect($raw)->toBeString()
        ->and($raw)->not->toContain('The secret answer')
        ->and($raw)->not->toContain('msg-1');
});

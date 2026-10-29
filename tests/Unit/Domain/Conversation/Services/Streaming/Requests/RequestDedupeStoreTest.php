<?php

declare(strict_types=1);

use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Illuminate\Support\Facades\Cache;

/**
 * A finished answer is kept by request id for a few minutes, encrypted, so a
 * retried request can replay it.
 */
it('replays a snapshot until the configured time to live has passed, then drops it', function (): void {
    config(['chatvia.sse.dedupe.ttl_seconds' => 60]);
    $store = new RequestDedupeStore;

    $store->remember('req-1', 'msg-1', 'Hello world', null);

    $this->travel(59)->seconds();
    expect($store->find('req-1')?->content)->toBe('Hello world');

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

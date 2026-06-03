<?php

namespace Tests\Unit\Chat\Streaming;

use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Tests\TestCase;

class RequestDedupeStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('sse.dedupe.store', 'array');
        config()->set('sse.dedupe.ttl_seconds', 60);
    }

    public function test_returns_null_for_unknown_request_id(): void
    {
        $store = new RequestDedupeStore;

        $this->assertNull($store->find('req-missing'));
    }

    public function test_remembers_and_reads_a_snapshot(): void
    {
        $store = new RequestDedupeStore;

        $store->remember(
            requestId: 'req-1',
            assistantMessageId: 'msg-1',
            content: 'Hello world',
            language: 'en',
            sources: [['id' => 7]],
        );

        $snapshot = $store->find('req-1');

        $this->assertSame('msg-1', $snapshot['assistant_message_id']);
        $this->assertSame('Hello world', $snapshot['content']);
        $this->assertSame('en', $snapshot['language']);
        $this->assertSame([['id' => 7]], $snapshot['sources']);
    }

    public function test_forgets_a_snapshot(): void
    {
        $store = new RequestDedupeStore;

        $store->remember('req-1', 'msg-1', 'content', null, []);
        $store->forget('req-1');

        $this->assertNull($store->find('req-1'));
    }
}

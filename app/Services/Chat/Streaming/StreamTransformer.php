<?php

namespace App\Services\Chat\Streaming;

use App\Services\Chat\ChatContext;
use Generator;
use Psr\Http\Message\ResponseInterface;

interface StreamTransformer
{
    /**
     * Convert a provider's raw SSE response body into a stream of normalized
     * StreamEvent instances. Implementations should be tolerant of malformed
     * chunks (skip + log) and must not throw on upstream connection errors —
     * those are surfaced via the caller's catch block.
     *
     * The generator may yield Delta and Meta events; Start/Done are emitted
     * by the calling Source so persistence ordering stays consistent.
     *
     * @return Generator<StreamEvent>
     */
    public function transform(ResponseInterface $upstream, ChatContext $context): Generator;
}

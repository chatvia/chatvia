<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Data\Turn\ReplaySnapshotData;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;

/**
 * Replays a finished answer for a retried request (see
 * {@see RequestDedupeStore}). `replayed: true` tells the client this is not a
 * fresh answer.
 */
class ReplaySource
{
    /**
     * @return Generator<int, StreamEvent>
     */
    public function events(string $requestId, ReplaySnapshotData $snapshot): Generator
    {
        $assistantId = $snapshot->assistant_message_id;

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => null,
            'request_id' => $requestId,
            'language' => $snapshot->language,
            'replayed' => true,
        ]);

        if ($snapshot->content !== '') {
            yield StreamEvent::delta($assistantId, $snapshot->content);
        }

        if ($snapshot->sources !== []) {
            yield StreamEvent::sources($assistantId, $snapshot->sources);
        }

        yield StreamEvent::done($assistantId, 'complete');
    }
}

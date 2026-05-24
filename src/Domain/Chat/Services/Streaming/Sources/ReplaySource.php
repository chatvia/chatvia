<?php

namespace Domain\Chat\Services\Streaming\Sources;

use Domain\Chat\Services\Streaming\StreamEvent;
use Generator;

class ReplaySource
{
    /**
     * Replay a previously-completed stream for an idempotent retry.
     *
     * The snapshot comes from RequestDedupeStore. The `replayed: true`
     * flag tells the client this isn't fresh generation.
     *
     * @param  array{assistant_message_id: string, content: string, language: ?string, sources: array}  $snapshot
     * @return Generator<StreamEvent>
     */
    public function events(string $requestId, array $snapshot): Generator
    {
        $assistantId = $snapshot['assistant_message_id'];

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => null,
            'request_id' => $requestId,
            'language' => $snapshot['language'] ?? null,
            'replayed' => true,
        ]);

        if (($snapshot['content'] ?? '') !== '') {
            yield StreamEvent::delta($assistantId, $snapshot['content']);
        }

        if (! empty($snapshot['sources'])) {
            yield StreamEvent::sources($assistantId, $snapshot['sources']);
        }

        yield StreamEvent::done($assistantId, 'complete');
    }
}

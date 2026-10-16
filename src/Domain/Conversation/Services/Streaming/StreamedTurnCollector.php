<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming;

use Domain\Conversation\Data\Turn\CompletedTurnData;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;

/**
 * Drains a streamed turn and folds its events into one result, for callers
 * that answer with a single payload instead of relaying the stream (the
 * public API's JSON mode). Consuming the generator runs the same pipeline the
 * SSE clients get — every gate, guardrail and persistence side effect — so a
 * folded turn behaves exactly like a streamed one.
 */
class StreamedTurnCollector
{
    /**
     * @param  iterable<int, StreamEvent>  $events
     */
    public function collect(iterable $events): CompletedTurnData
    {
        // Draining waits on the LLM end to end, like SseResponder does for
        // the streamed modes, so the PHP time limit must not cut the turn.
        set_time_limit(0);

        $assistantMessageId = '';
        $userMessageId = null;
        $content = '';
        $sources = [];
        $doneReason = null;
        $responseTimeMs = null;
        $errorCode = null;
        $errorMessage = null;
        $errorRetryable = false;

        foreach ($events as $event) {
            $assistantMessageId = $event->id;

            switch ($event->type) {
                case EventType::Start:
                    $userMessageId = $event->data['user_message_id'] ?? null;
                    break;
                case EventType::Delta:
                    $content .= $event->data['text'] ?? '';
                    break;
                    // An output guardrail rewrote the already-streamed text.
                case EventType::Replace:
                    $content = $event->data['text'] ?? '';
                    break;
                case EventType::Sources:
                    $sources = $event->data['sources'] ?? [];
                    break;
                case EventType::Error:
                    $errorCode = $event->data['code'] ?? null;
                    $errorMessage = $event->data['message'] ?? null;
                    $errorRetryable = (bool) ($event->data['retryable'] ?? false);
                    break;
                case EventType::Done:
                    $doneReason = $event->data['reason'] ?? null;
                    $responseTimeMs = $event->data['response_time_ms'] ?? null;
                    break;
                case EventType::Meta:
                case EventType::Tool:
                    // Tool progress and meta keys have no place in a folded reply.
                    break;
            }
        }

        return new CompletedTurnData(
            assistantMessageId: $assistantMessageId,
            content: $content,
            userMessageId: $userMessageId,
            sources: $sources,
            doneReason: $doneReason,
            responseTimeMs: $responseTimeMs,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            errorRetryable: $errorRetryable,
        );
    }
}

<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;
use Illuminate\Support\Str;

/**
 * Ends a turn that was refused before any model work began (a monthly
 * message limit or the rate limit reached, an approval already handled) with
 * one typed error event.
 */
class ErrorSource
{
    /**
     * Emit a single typed error event followed by `done`. Used for message
     * limits and any other reason the request was refused before LLM work
     * began.
     *
     * @return Generator<int, StreamEvent>
     */
    public function events(ErrorCode $code, string $message, ?string $assistantMessageId = null): Generator
    {
        $id = $assistantMessageId ?? (string) Str::uuid7();

        yield StreamEvent::start($id, [
            'assistant_message_id' => $id,
            'user_message_id' => null,
            'request_id' => null,
            'language' => null,
            'replayed' => false,
        ]);

        yield StreamEvent::error($id, $code, $message);

        yield StreamEvent::done($id, 'error');
    }
}

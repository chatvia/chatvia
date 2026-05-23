<?php

namespace App\Services\Chat\Streaming\Sources;

use App\Services\Chat\Streaming\ErrorCode;
use App\Services\Chat\Streaming\StreamEvent;
use Generator;
use Illuminate\Support\Str;

class ErrorSource
{
    /**
     * Emit a single typed error event followed by `done`. Used for
     * subscription/credit failures and any other reason the request was
     * refused before LLM work began.
     *
     * @return Generator<StreamEvent>
     */
    public function events(ErrorCode $code, string $message, ?string $assistantMessageId = null): Generator
    {
        $id = $assistantMessageId ?? (string) Str::uuid();

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

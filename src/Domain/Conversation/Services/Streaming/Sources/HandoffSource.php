<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;
use Illuminate\Support\Str;

class HandoffSource
{
    /**
     * Acknowledge a message received while the conversation is handed off
     * to a human. No assistant content is generated — the user message has
     * already been persisted and broadcast to the admin upstream.
     *
     * @return Generator<StreamEvent>
     */
    public function events(Conversation $conversation): Generator
    {
        $id = (string) Str::uuid();

        yield StreamEvent::start($id, [
            'assistant_message_id' => $id,
            'user_message_id' => null,
            'request_id' => null,
            'language' => null,
            'replayed' => false,
        ]);

        // The widget reads `conversation_id` and stores it as its active
        // conversation handle (sent back as the X-CONVERSATION-ID header), so it
        // MUST be the external UUID — never the internal PK.
        yield StreamEvent::meta($id, 'handoff', [
            'status' => 'human',
            'conversation_id' => $conversation->external_id,
            'external_conversation_id' => $conversation->external_id,
        ]);

        yield StreamEvent::done($id, 'complete');
    }
}

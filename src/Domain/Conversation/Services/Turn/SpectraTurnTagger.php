<?php

namespace Domain\Conversation\Services\Turn;

use Closure;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Spectra\Facades\Spectra;

/**
 * Puts the AI calls Spectra records during a chat turn in the conversation they
 * serve, so its Conversations screen reads them as a transcript, one turn per
 * reply (the per-reply link is the trace id stored on the message row, see
 * {@see AssistantReplyRecorder}). The turn is the number of assistant replies
 * so far plus one: replies are saved when their turn ends, so the count holds
 * however late the queued visitor message lands, and a resumed turn keeps its
 * number. Who the calls belong to is the workspace, not the conversation (see
 * WorkspaceAttributedSpectra). A closure defers the conversation lookup until
 * Spectra is known to record (`spectra.enabled` is the install-and-recording
 * switch, see ToolRecording); a turn without a conversation row tags nothing.
 */
class SpectraTurnTagger
{
    /** @param  Conversation|Closure(): ?Conversation|null  $conversation */
    public function tag(Conversation|Closure|null $conversation): void
    {
        if (! config('spectra.enabled')) {
            return;
        }

        if ($conversation instanceof Closure) {
            $conversation = $conversation();
        }

        if (! $conversation instanceof Conversation) {
            return;
        }

        Spectra::forConversation($conversation, turn: $conversation->messages()->where('role', MessageRole::Assistant)->count() + 1);
    }
}

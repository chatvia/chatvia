<?php

namespace Domain\Conversation\Services\Turn;

use Closure;
use Domain\Conversation\Models\Conversation;
use Spectra\Facades\Spectra;

/**
 * Tags the AI calls Spectra records during a chat turn with the conversation
 * they serve, so /spectra's Trackables screen aggregates calls and cost per
 * conversation across turns (the per-reply link is the trace id stored on the
 * message row, see {@see AssistantReplyRecorder}). An explicit trackable wins
 * over Spectra's signed-in-user fallback (`auto_track_user`), so even a staff
 * preview's calls land on the conversation. A closure defers the conversation
 * lookup until Spectra is known to record (`spectra.enabled` is the
 * install-and-recording switch, see ToolRecording); a turn without a
 * conversation row tags nothing.
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

        Spectra::forTrackable($conversation);
    }
}

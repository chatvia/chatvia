<?php

namespace Domain\Conversation\Services\Streaming\Sources\Concerns;

use Domain\Conversation\Services\ChatContext;
use Domain\Llm\Usage\TokenUsageContext;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * Per-turn Spectra setup shared by the streaming sources. Establishes two
 * globals that every Spectra::stream() record produced during the turn inherits
 * — including the tool-loop iterations driven by ToolLoopRunner:
 *
 *   - trace_id  → groups the whole agentic chain as one logical operation.
 *   - trackable → the owning workspace, so anonymous-widget turns (no auth user)
 *     are still attributed. For an authenticated chatter Spectra's
 *     `auto_track_user` overrides this with the chatter in RequestContext, so it
 *     only fills the no-auth gap — same pattern as {@see TokenUsageContext}.
 */
trait AttributesSpectraTurn
{
    private function bindSpectraTurn(ChatContext $context): void
    {
        Spectra::withTraceId($context->assistantMessageId);

        $workspaceId = $context->requestData->agent->user_id;

        if ($workspaceId === null) {
            return;
        }

        try {
            // Spectra (>=1.2) resolves the id to the configured user model.
            Spectra::forUser($workspaceId);
        } catch (Throwable) {
            // Attribution must never break the streamed turn.
        }
    }
}

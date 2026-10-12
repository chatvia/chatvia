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
 *   - trackable → the agent's workspace, for every turn: Spectra's user
 *     auto-tracking is off, so a signed-in chatter or teammate never takes
 *     the cost away from the workspace (same rule as {@see TokenUsageContext}).
 */
trait AttributesSpectraTurn
{
    private function bindSpectraTurn(ChatContext $context): void
    {
        Spectra::withTraceId($context->assistantMessageId);

        try {
            Spectra::forTrackable($context->requestData->agent->workspace);
        } catch (Throwable $e) {
            // Attribution must never break the streamed turn — but a failure
            // means cost tracking is silently wrong, so make it visible.
            report($e);
        }
    }
}

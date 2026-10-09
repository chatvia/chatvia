<?php

namespace Domain\Conversation\Services\Streaming\Sse;

/**
 * Where a tool call is in its lifecycle, as sent in the `phase` field of the
 * SSE `tool` event. The chat clients switch on these strings, so they must
 * not change.
 */
enum ToolCallPhase: string
{
    /** The LLM emitted a complete tool call and it is about to run. */
    case Start = 'start';

    /** The call finished (or was refused); carries the result text. */
    case End = 'end';

    /** The call is gated and waits for a person to approve it. */
    case AwaitingApproval = 'awaiting_approval';
}

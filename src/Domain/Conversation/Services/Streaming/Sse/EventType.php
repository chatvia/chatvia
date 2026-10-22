<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Sse;

/**
 * The kinds of event in a streamed chat turn. The chat clients switch on
 * these strings, so they must not change.
 */
enum EventType: string
{
    case Start = 'start';
    case Delta = 'delta';
    // A piece of the model's thinking, shown apart from the answer and never
    // stored. Sent only to surfaces that show it, while the agent reasons.
    case Reasoning = 'reasoning';
    case Meta = 'meta';
    case Sources = 'sources';
    case Tool = 'tool';
    // A guardrail corrected the answer after it streamed: the client replaces
    // the rendered message with `text` (the redacted / safe version).
    case Replace = 'replace';
    case Error = 'error';
    case Done = 'done';
}

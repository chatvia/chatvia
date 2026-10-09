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
    case Meta = 'meta';
    case Sources = 'sources';
    case Tool = 'tool';
    // A guardrail corrected the answer after it streamed: the client replaces
    // the rendered message with `text` (the redacted / safe version).
    case Replace = 'replace';
    case Error = 'error';
    case Done = 'done';
}

<?php

namespace Domain\Conversation\Services\Streaming\Sse;

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

<?php

namespace Domain\Conversation\Services\Streaming\Sse;

enum EventType: string
{
    case Start = 'start';
    case Delta = 'delta';
    case Meta = 'meta';
    case Sources = 'sources';
    case Tool = 'tool';
    case Error = 'error';
    case Done = 'done';
}

<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * The author of one message in a chat-completion request, as the
 * OpenAI-compatible APIs name it on the wire.
 */
enum ChatRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}

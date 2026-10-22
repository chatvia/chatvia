<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Transformers;

/**
 * Where a reply is in an inline `<think>…</think>` block, which some servers
 * (LM Studio, Ollama, Groq's raw format) put in the reply text instead of a
 * separate reasoning field. Undecided until the reply's first characters
 * show whether it opens with the tag; Done once the block is closed, or when
 * there was none.
 */
enum ThinkPhase
{
    case Undecided;
    case Thinking;
    case Done;
}

<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Transformers;

/**
 * How the transformer treats a reply's text while it streams. Some
 * open-weight models write a tool call as JSON in the reply text instead of in
 * `tool_calls`, so when tools are offered the first characters decide.
 */
enum ContentMode
{
    /** Nothing but whitespace has arrived yet. */
    case Undecided;

    /** Starts like JSON: held back, parsed as a tool call at the end. */
    case HeldAsJson;

    /** Ordinary text: streamed to the visitor as it arrives. */
    case Text;
}

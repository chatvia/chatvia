<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Transformers;

/**
 * What {@see OpenAiCompatibleTransformer} remembers while it reads one
 * upstream stream: whether the generation id was announced, where the reply
 * is in an inline `<think>` block, how the reply text is being treated, the
 * text held back while it may be a JSON tool call, and whether the end of
 * the stream was handled.
 */
final class StreamReadState
{
    public bool $generationIdSeen = false;

    public ThinkPhase $think = ThinkPhase::Undecided;

    /** Text held back while it may still be (part of) a `<think>` tag. */
    public string $thinkBuffer = '';

    public ContentMode $mode = ContentMode::Undecided;

    public string $heldContent = '';

    public bool $finalized = false;

    public function __construct(
        /** Without offered tools, text that looks like JSON is just an answer. */
        public readonly bool $toolsOffered,
    ) {}
}

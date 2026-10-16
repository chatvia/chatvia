<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Memory;

use Domain\Conversation\Enums\MessageRole;

/**
 * One history turn as the LLM replays it: who spoke and the LLM-facing text
 * (the masked message without its reference material). Internal carrier
 * between the chat memory and the prompt assembly; {@see self::toWire()} is
 * the OpenAI message shape the prompt is built from.
 */
final readonly class LlmMessageData
{
    public function __construct(
        public MessageRole $role,
        public string $content,
    ) {}

    /**
     * @return array{role: string, content: string}
     */
    public function toWire(): array
    {
        return ['role' => $this->role->value, 'content' => $this->content];
    }
}

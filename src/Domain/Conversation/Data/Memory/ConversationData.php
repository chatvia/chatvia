<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Memory;

use Spatie\LaravelData\Data;

/**
 * One conversation as the visitor's cached conversation list holds it and as
 * the chat clients receive it.
 */
class ConversationData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $messages  the conversation's cached chat messages
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $timestamp,
        public bool $is_visible,
        public array $messages = [],
    ) {}

    /** The same conversation renamed and made visible; DTOs never mutate. */
    public function renamedTo(string $title): self
    {
        return new self($this->id, $title, $this->timestamp, is_visible: true, messages: $this->messages);
    }

    /** The same conversation, visible in the list; DTOs never mutate. */
    public function visible(): self
    {
        return new self($this->id, $this->title, $this->timestamp, is_visible: true, messages: $this->messages);
    }
}

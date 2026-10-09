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
}

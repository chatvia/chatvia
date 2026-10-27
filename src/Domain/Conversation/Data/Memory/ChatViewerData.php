<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Memory;

use Domain\Conversation\Enums\ChatViewerKind;
use Spatie\LaravelData\Data;

/**
 * The signed-in person on a hosted chat: a chatter or a staff account. Both
 * the kind and the id are needed, because chatter ids and staff ids come from
 * different tables and can be equal. {@see key()} is the form used in cache
 * keys and ownership markers.
 */
class ChatViewerData extends Data
{
    public function __construct(
        public ChatViewerKind $kind,
        public int $id,
    ) {}

    public static function chatter(int $id): self
    {
        return new self(ChatViewerKind::Chatter, $id);
    }

    public static function staff(int $id): self
    {
        return new self(ChatViewerKind::Staff, $id);
    }

    public function isChatter(): bool
    {
        return $this->kind === ChatViewerKind::Chatter;
    }

    /** The `chat_sessions` column that links a session to this viewer. */
    public function sessionColumn(): string
    {
        return $this->isChatter() ? 'chatter_id' : 'user_id';
    }

    /** `chatter:{id}` or `user:{id}`. */
    public function key(): string
    {
        return "{$this->kind->value}:{$this->id}";
    }
}

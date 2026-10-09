<?php

namespace Domain\Llm\Data\Chat;

use Domain\Llm\Enums\ChatRole;
use Spatie\LaravelData\Data;

/**
 * One message of a chat-completion request. The content is plain text, or a
 * list of OpenAI-style content parts when the message carries an image or a
 * document (`image_url` / `document_url` parts next to `text` parts).
 */
final class ChatMessageData extends Data
{
    /**
     * @param  string|list<array<string, mixed>>  $content
     */
    public function __construct(
        public ChatRole $role,
        public string|array $content,
    ) {}

    public static function system(string $text): self
    {
        return new self(ChatRole::System, $text);
    }

    /**
     * @param  string|list<array<string, mixed>>  $content
     */
    public static function user(string|array $content): self
    {
        return new self(ChatRole::User, $content);
    }

    /**
     * A user message made of a text instruction and one attachment part.
     *
     * @param  array<string, mixed>  $attachment  an `image_url` or `document_url` content part
     */
    public static function userWithAttachment(string $text, array $attachment): self
    {
        return self::user([
            ['type' => 'text', 'text' => $text],
            $attachment,
        ]);
    }

    /**
     * The message in the provider's wire format.
     *
     * @return array{role: string, content: string|list<array<string, mixed>>}
     */
    public function toPayload(): array
    {
        return ['role' => $this->role->value, 'content' => $this->content];
    }
}

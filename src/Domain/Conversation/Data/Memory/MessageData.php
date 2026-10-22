<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Memory;

use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Conversation\Enums\MessageRole;
use Spatie\LaravelData\Data;

/**
 * One message as the live chat memory caches it and as the chat clients
 * receive it in a conversation's history. `message_for_llm` is the private
 * LLM-facing text the history replays (the masked message, without the
 * reference material of its turn); it is cached but stripped before the
 * history leaves the server.
 */
class MessageData extends Data
{
    /** The only message type the chat memory stores today. */
    public const string TYPE_TEXT = 'text';

    /**
     * @param  int  $timestamp  unix seconds
     * @param  int|null  $read_at  unix seconds; only operator and handed-off visitor messages carry read receipts
     * @param  array<string, mixed>|null  $metadata  `artifacts` (tool results the UI renders, e.g. charts) and `tool_calls` (tool names the turn ran); null for plain text turns
     */
    public function __construct(
        public string $id,
        public MessageRole $role,
        public string $content,
        public string $type,
        public int $timestamp,
        public ?int $read_at = null,
        public ?int $llm_id = null,
        public ?string $feedback = null,
        public ?string $language = null,
        public ?string $message_for_llm = null,
        public ?bool $has_llm_found_answer = null,
        public ?array $metadata = null,
        /** @var list<AttachmentData>|null set by the attachment hydrator; null in the cache */
        public ?array $attachments = null,
    ) {}

    /** The client copy: the same message without the private LLM-facing text. */
    public function forClient(): self
    {
        return $this->copy(message_for_llm: null);
    }

    public function withMessageForLlm(?string $messageForLlm): self
    {
        return $this->copy(message_for_llm: $messageForLlm);
    }

    public function withReadAt(int $timestamp): self
    {
        return $this->copy(read_at: $timestamp);
    }

    public function withFeedback(?string $feedback): self
    {
        return $this->copy(feedback: $feedback);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function withMetadata(?array $metadata): self
    {
        return $this->copy(metadata: $metadata);
    }

    /**
     * @param  list<AttachmentData>  $attachments
     */
    public function withAttachments(array $attachments): self
    {
        return $this->copy(attachments: $attachments);
    }

    /**
     * The same message with the named fields replaced; DTOs never mutate.
     * An omitted argument keeps the current value, so every other field
     * carries over untouched.
     */
    private function copy(mixed ...$replacements): self
    {
        $fields = [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
            'read_at' => $this->read_at,
            'llm_id' => $this->llm_id,
            'feedback' => $this->feedback,
            'language' => $this->language,
            'message_for_llm' => $this->message_for_llm,
            'has_llm_found_answer' => $this->has_llm_found_answer,
            'metadata' => $this->metadata,
            'attachments' => $this->attachments,
        ];

        return new self(...[...$fields, ...$replacements]);
    }
}

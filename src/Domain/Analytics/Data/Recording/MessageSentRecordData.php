<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\MessageSent;
use Spatie\LaravelData\Data;

/**
 * One chat message to record in the analytics tables: who sent it (`role` is
 * `user` or `assistant`), where, with which model, how fast the reply came and
 * how many tokens it used. Built from a {@see MessageSent} event.
 */
class MessageSentRecordData extends Data
{
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public ?int $chatterId,
        public string $conversationExternalId,
        public string $messageExternalId,
        public string $sessionExternalId,
        public string $role,
        public ?string $llmExternalName,
        public ?string $countryCode,
        public ?int $responseTimeMs,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(MessageSent $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            chatterId: $event->chatterId,
            conversationExternalId: $event->conversationExternalId,
            messageExternalId: $event->messageExternalId,
            sessionExternalId: $event->sessionExternalId,
            role: $event->role,
            llmExternalName: $event->llmExternalName,
            countryCode: $event->countryCode,
            responseTimeMs: $event->responseTimeMs,
            promptTokens: $event->promptTokens,
            completionTokens: $event->completionTokens,
            occurredAt: $event->occurredAt,
        );
    }

    public function isFromVisitor(): bool
    {
        return $this->role === 'user';
    }

    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function totalTokens(): int
    {
        return (int) $this->promptTokens + (int) $this->completionTokens;
    }
}

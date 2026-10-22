<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\MessageSent;
use Spatie\LaravelData\Data;

/**
 * One chat message to record in the analytics tables: who sent it (`role` is
 * `user` or `assistant`), where, with which model, how fast the reply came,
 * how many tokens it used and, for an AI reply, whether it found an answer
 * and how confident it was. Built from a {@see MessageSent} event.
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
        public ?int $llmId,
        public ?string $countryCode,
        public ?int $responseTimeMs,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public DateTimeInterface $occurredAt,
        public ?bool $hasFoundAnswer = null,
        public ?float $confidenceScore = null,
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
            llmId: $event->llmId,
            countryCode: $event->countryCode,
            responseTimeMs: $event->responseTimeMs,
            promptTokens: $event->promptTokens,
            completionTokens: $event->completionTokens,
            occurredAt: $event->occurredAt,
            hasFoundAnswer: $event->hasFoundAnswer,
            confidenceScore: $event->confidenceScore,
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

    /** An AI reply that could not find an answer. */
    public function isUnanswered(): bool
    {
        return $this->isFromAssistant() && $this->hasFoundAnswer === false;
    }

    /** The confidence of an AI reply, or null for a visitor message or a reply without one. */
    public function replyConfidence(): ?float
    {
        return $this->isFromAssistant() ? $this->confidenceScore : null;
    }

    public function totalTokens(): int
    {
        return (int) $this->promptTokens + (int) $this->completionTokens;
    }
}

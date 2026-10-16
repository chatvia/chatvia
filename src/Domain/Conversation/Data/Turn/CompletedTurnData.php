<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\StreamedTurnCollector;
use Spatie\LaravelData\Data;

/**
 * A streamed turn folded into one value: what a caller that cannot (or does
 * not want to) consume server-sent events needs — the finished text, the
 * cited sources and how the turn ended. Built by
 * {@see StreamedTurnCollector}.
 */
class CompletedTurnData extends Data
{
    /**
     * @param  string  $assistantMessageId  the assistant message's external id
     * @param  string  $content  the final text (guardrail corrections already applied)
     * @param  list<array<string, mixed>>  $sources  the knowledge sources the reply cited
     * @param  string|null  $doneReason  `complete`, `error` or `awaiting_approval`
     * @param  string|null  $errorCode  a {@see ErrorCode} value
     */
    public function __construct(
        public string $assistantMessageId,
        public string $content,
        public ?string $userMessageId = null,
        public array $sources = [],
        public ?string $doneReason = null,
        public ?int $responseTimeMs = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $errorRetryable = false,
    ) {}

    /**
     * True when the turn was refused outright — an error with no text at all,
     * so the HTTP layer can answer with an error status instead of a reply.
     */
    public function failedBeforeAnyContent(): bool
    {
        return $this->errorCode !== null && $this->content === '';
    }
}

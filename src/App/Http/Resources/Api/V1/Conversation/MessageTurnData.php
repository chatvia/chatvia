<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Conversation;

use Domain\Conversation\Data\Turn\CompletedTurnData;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * The public API's JSON reply to a sent message: the finished assistant
 * message, the sources it cited and how the turn ended.
 */
class MessageTurnData extends Data
{
    public function __construct(
        /**
         * The conversation's ID.
         */
        public string $conversation_id,
        /**
         * The agent's finished reply.
         */
        public TurnMessageData $message,
        /**
         * The knowledge sources the reply cited (label and public link only).
         * Empty when the agent does not show sources.
         *
         * @var list<array<string, mixed>>
         */
        public array $sources,
        /**
         * How the turn ended: `complete`, or `error` when it was cut short
         * (the partial text is still in `message.content`).
         */
        public ?string $done_reason,
        /**
         * How long the reply took, in milliseconds.
         */
        public ?int $response_time_ms,
        /**
         * Why the turn ended early. Null on a completed turn.
         */
        public ?TurnErrorData $error,
    ) {}

    public static function fromCompletedTurn(CompletedTurnData $turn, Conversation $conversation): self
    {
        return new self(
            conversation_id: $conversation->external_id,
            message: new TurnMessageData(
                id: $turn->assistantMessageId,
                role: 'assistant',
                content: $turn->content,
            ),
            sources: $turn->sources,
            done_reason: $turn->doneReason,
            response_time_ms: $turn->responseTimeMs,
            error: $turn->errorCode !== null
                ? new TurnErrorData($turn->errorCode, $turn->errorMessage, $turn->errorRetryable)
                : null,
        );
    }
}

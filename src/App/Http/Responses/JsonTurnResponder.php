<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\Api\V1\Conversation\MessageTurnData;
use Domain\Conversation\Data\Turn\CompletedTurnData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * The JSON counterpart of {@see SseResponder}: answers a folded chat turn in
 * one response. A turn refused before any text became an error status; a turn
 * that produced text — even one cut short — is a 200, because the messages are
 * already stored and counted, and a retrying client would pay for a new turn.
 */
class JsonTurnResponder
{
    public function respond(CompletedTurnData $turn, Conversation $conversation): JsonResponse
    {
        if ($turn->failedBeforeAnyContent()) {
            return response()->json([
                'message' => $turn->errorMessage ?? __('The message could not be answered.'),
                'code' => $turn->errorCode,
                'retryable' => $turn->errorRetryable,
            ], $this->status($turn->errorCode));
        }

        return response()->json(['data' => MessageTurnData::fromCompletedTurn($turn, $conversation)]);
    }

    private function status(?string $code): int
    {
        return match (ErrorCode::tryFrom($code ?? '')) {
            ErrorCode::AccessBlocked => 403,
            ErrorCode::MessageLimitReached,
            ErrorCode::ChatterMessageLimitReached,
            ErrorCode::RateLimited => 429,
            ErrorCode::UpstreamUnavailable => 502,
            default => 500,
        };
    }
}

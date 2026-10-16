<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatContext;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * Builds the context of one chat turn the way TurnContextFactory does, for
 * tests that drive a streaming source, the tool loop or the knowledge step
 * without the turn set-up in front of them. A new agent, model, session and
 * conversation are used for anything not given.
 */
final class TestChatContext
{
    /**
     * @param  list<array<string, mixed>>|null  $llmMessages  the history sent to the model; the message alone when null
     */
    public static function make(
        ?Agent $agent = null,
        string $message = 'Hello',
        ?Llm $llm = null,
        ?array $llmMessages = null,
        ?string $externalSessionId = null,
        ?string $conversationId = null,
        ?string $requestId = null,
        bool $streamSources = false,
        ?string $userMessageId = null,
        ?int $chatterId = null,
    ): ChatContext {
        $llm ??= Llm::factory()->create();

        $requestData = new StreamRequestData(
            agent: $agent ?? Agent::factory()->create(),
            message: new StreamRequestMessageData(model: $llm, message: $message),
            externalSessionId: $externalSessionId ?? (string) Str::uuid(),
            conversationId: $conversationId ?? (string) Str::uuid(),
            chatterId: $chatterId,
            streamSources: $streamSources,
            requestId: $requestId ?? 'req_'.Str::random(12),
        );

        return new ChatContext(
            requestData: $requestData,
            payload: new UserTurnData(
                external_session_id: $requestData->externalSessionId,
                external_conversation_id: $requestData->conversationId,
                external_message_id: (string) Str::uuid(),
                model: $llm->external_name,
                message: $message,
                sent_at: now(),
                message_for_llm: $message,
            ),
            llmMessages: $llmMessages ?? [['role' => 'user', 'content' => $message]],
            userMessageId: $userMessageId ?? (string) Str::uuid(),
            assistantMessageId: (string) Str::uuid(),
        );
    }
}

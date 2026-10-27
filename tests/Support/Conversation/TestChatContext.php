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
use ReflectionClass;

/**
 * Builds the context of one chat turn the way TurnContextFactory does, for
 * tests that drive a streaming source, the tool loop, the knowledge step or
 * the guardrail recorder without the turn set-up in front of them. A new
 * agent, model, session and conversation are used for anything not given.
 * {@see bare()} builds one with no turn behind it, for a stream transformer.
 */
final class TestChatContext
{
    /**
     * @param  list<array<string, mixed>>|null  $llmMessages  the history sent to the model; the message alone when null
     * @param  string|null  $messageForLlm  the message with its reference material; the message itself when null
     * @param  string|null  $queryLanguage  the language the question was written in, when the turn detected it
     * @param  int|null  $actorUserId  the staff account behind the turn (a preview); null for a visitor or a chatter
     * @param  string|null  $ipAddress  the visitor's IP address, as the request gave it
     * @param  string|null  $userAgent  the visitor's browser, as the request gave it
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
        ?string $messageForLlm = null,
        ?string $queryLanguage = null,
        ?int $actorUserId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $clarifyingQuestion = null,
        ?string $source = null,
    ): ChatContext {
        $llm ??= Llm::factory()->create();

        $requestData = new StreamRequestData(
            agent: $agent ?? Agent::factory()->create(),
            message: new StreamRequestMessageData(model: $llm, message: $message),
            externalSessionId: $externalSessionId ?? (string) Str::uuid7(),
            conversationId: $conversationId ?? (string) Str::uuid7(),
            chatterId: $chatterId,
            actorUserId: $actorUserId,
            streamSources: $streamSources,
            source: $source,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            requestId: $requestId ?? 'req_'.Str::random(12),
        );

        return new ChatContext(
            requestData: $requestData,
            payload: new UserTurnData(
                external_session_id: $requestData->externalSessionId,
                external_conversation_id: $requestData->conversationId,
                external_message_id: (string) Str::uuid7(),
                llm_id: $llm->id,
                message: $message,
                sent_at: now(),
                message_for_llm: $messageForLlm ?? $message,
            ),
            llmMessages: $llmMessages ?? [['role' => 'user', 'content' => $message]],
            userMessageId: $userMessageId ?? (string) Str::uuid7(),
            assistantMessageId: (string) Str::uuid7(),
            queryLanguage: $queryLanguage,
            clarifyingQuestion: $clarifyingQuestion,
        );
    }

    /**
     * A context with no turn behind it, for a stream transformer: built
     * without its constructor, which needs a whole chat turn, and holding
     * only what the transformer reads. No agent, model or session is made.
     *
     * @param  list<string>  $offeredToolNames
     */
    public static function bare(bool $showsReasoning = false, array $offeredToolNames = []): ChatContext
    {
        $reflection = new ReflectionClass(ChatContext::class);
        $context = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('sources')->setValue($context, collect());
        $reflection->getProperty('assistantMessageId')->setValue($context, 'asst-test');
        // Unsaved models: no request id, so the turn cannot be stopped.
        $reflection->getProperty('requestData')->setValue($context, new StreamRequestData(
            agent: new Agent,
            message: new StreamRequestMessageData(model: new Llm, message: ''),
            externalSessionId: 'session-test',
            conversationId: 'conversation-test',
        ));
        $context->offeredToolNames = $offeredToolNames;
        $context->showsReasoning = $showsReasoning;

        return $context;
    }
}

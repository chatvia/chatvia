<?php

namespace Domain\Channel\Services;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\QASource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one messaging-channel turn through the REAL chat pipeline (RAG + tools
 * + guardrails + persistence + billing) and returns the full reply as a
 * string. Shared by every channel runner (Telegram, Slack, Messenger) — the
 * per-channel adapters only decide what happens *while* the reply generates
 * (refresh a typing indicator, push streamed partials) via `$onEvent`.
 *
 * Mirrors {@see ChatStreamService}: same owner subscription/credit gate, same
 * source selection (guard-block → Q&A → LLM). The sources persist + bill +
 * apply output guards as the generator is drained; the final (already
 * redacted/corrected) text lands in `ChatContext::streamedMessage` —
 * mid-drain reads of it are pre-guard, exactly as on the web SSE surface.
 */
class ChannelReplyRunner
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly LlmSource $llmSource,
        private readonly QASource $qaSource,
        private readonly GuardBlockSource $guardBlockSource,
        private readonly UserCreditSystem $creditSystem,
    ) {}

    /**
     * @param  Closure(ChatContext): void|null  $onEvent  invoked after each drained pipeline event
     * @param  array<string, mixed>  $logContext  channel-specific identifiers for the failure log
     */
    public function reply(
        Agent $agent,
        string $sessionExternalId,
        string $conversationExternalId,
        string $message,
        string $source,
        string $requestIdPrefix,
        ?Closure $onEvent = null,
        array $logContext = [],
    ): string {
        $agent->loadMissing(['agentPrompt', 'llm']);
        $owner = $agent->user;

        if ($owner === null || ! $this->ownerCanReceive($owner)) {
            return 'Sorry — this assistant is unavailable right now. Please try again later.';
        }

        if ($agent->llm === null) {
            return 'Sorry — this assistant is not fully configured yet.';
        }

        $data = new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(model: $agent->llm, message: $message),
            externalSessionId: $sessionExternalId,
            conversationId: $conversationExternalId,
            source: $source,
            saveUserMessageImmediately: true,
            requestId: $requestIdPrefix.Str::uuid(),
        );

        try {
            $context = $this->chatService->initializeChatContext($data);
            $context = $this->chatService->enrichContextWithKnowledge($context);

            $events = match (true) {
                $context->guardBlocked => $this->guardBlockSource->events($context),
                $context->hasQAMatch() => $this->qaSource->events($context),
                default => $this->llmSource->events($context, $this->chatService->prepareLlmMessages($context)),
            };

            // Drain the generator: the source's side effects (persist, bill,
            // output-guard correction) all run; the channel adapter reacts to
            // progress through $onEvent.
            foreach ($events as $_event) {
                if ($onEvent !== null) {
                    $onEvent($context);
                }
            }

            $reply = trim($context->streamedMessage);

            return $reply !== '' ? $reply : 'Sorry — I could not produce a reply.';
        } catch (Throwable $e) {
            Log::error('Channel reply failed', [
                'error' => $e->getMessage(),
                'source' => $source,
                'agent_id' => $agent->id,
                ...$logContext,
            ]);

            return 'Sorry — something went wrong. Please try again.';
        }
    }

    /**
     * Same gate the web chat applies to the workspace owner: an active
     * subscription/trial AND remaining message credits.
     */
    private function ownerCanReceive(User $owner): bool
    {
        if ($owner->isAdmin()) {
            return true;
        }

        $billing = $owner->billing;
        $subscribed = (bool) ($billing?->subscribed() || $billing?->onTrial());

        return $subscribed && $this->creditSystem->setUserId($owner->id)->canSendMessages();
    }
}

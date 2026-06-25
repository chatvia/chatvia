<?php

namespace Domain\Channel\Services;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Services\AgentChatRateLimiter;
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
        private readonly AgentChatRateLimiter $rateLimiter,
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

        // Per-agent chat rate limit (owner-configurable on the Security page).
        // The web SSE surface applies this in ChatStreamService; channels reach
        // the pipeline directly, so the cap is enforced here too — otherwise a
        // visitor could bypass it by messaging through Telegram/Slack/etc.
        if ($this->rateLimiter->exceeded($agent)) {
            return 'This assistant is receiving too many messages right now. Please try again in a moment.';
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

            $reply = $this->stripWebOnlyArtifactTags(trim($context->streamedMessage));

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
     * Belt-and-braces for text-only channels. The forms prompt is already
     * withheld off the web (see ChatService::buildSystemPrompt), but if the
     * model still improvises the web-only `<render-form …>` artifact tag as
     * message text — e.g. mimicking one left in this conversation's history —
     * the raw tag must never reach the channel. Drop it and tidy the blank
     * lines it leaves behind. A no-op for the overwhelming majority of replies.
     */
    private function stripWebOnlyArtifactTags(string $reply): string
    {
        if (! str_contains($reply, '<render-form')) {
            return $reply;
        }

        return $reply
            |> (static fn (string $s): string => preg_replace('#<render-form\b[^>]*>.*?</render-form>#is', '', $s) ?? $s)
            |> (static fn (string $s): string => preg_replace('#<render-form\b[^>]*?/?>#i', '', $s) ?? $s)
            |> (static fn (string $s): string => preg_replace("/[ \t]*\n{3,}/", "\n\n", $s) ?? $s)
            |> trim(...);
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

        return $subscribed && $this->creditSystem->forWorkspace($owner->id)->canSendMessages();
    }
}

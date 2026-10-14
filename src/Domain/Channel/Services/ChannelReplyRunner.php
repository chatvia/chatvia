<?php

namespace Domain\Channel\Services;

use App\Identity\Workspace\Services\MessageAllowance;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentModel;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\Replies\StreamingReplyStrategy;
use Domain\Channel\Services\Replies\TypingReplyStrategy;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\QaSource;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs one messaging-channel turn through the REAL chat pipeline (RAG + tools
 * + guardrails + persistence + message limits) and returns the full reply as a
 * string. Shared by every channel — the reply strategies
 * ({@see TypingReplyStrategy}, {@see StreamingReplyStrategy}) only decide what
 * happens *while* the reply generates (refresh a typing indicator, push
 * streamed partials) via `$onProgress`.
 *
 * Mirrors {@see ChatStreamService}: the same workspace message limit
 * ({@see MessageAllowance}), the same per-agent rate limit, the same source
 * selection (guard-block → Q&A → LLM). The fixed replies below are sent in
 * the agent's channel language ({@see ChannelLocale}). The sources persist, count
 * the reply and apply output guards as the generator is drained; the final (already
 * redacted/corrected) text lands in `ChatContext::streamedMessage` —
 * mid-drain reads of it are pre-guard, exactly as on the web SSE surface.
 */
class ChannelReplyRunner
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly LlmSource $llmSource,
        private readonly QaSource $qaSource,
        private readonly GuardBlockSource $guardBlockSource,
        private readonly MessageAllowance $allowance,
        private readonly AgentChatRateLimiter $rateLimiter,
        private readonly AgentModel $models,
    ) {}

    /**
     * @param  (Closure(string): void)|null  $onProgress  invoked with the reply so far after each drained pipeline event
     */
    public function reply(ChannelTurnData $turn, ?Closure $onProgress = null): string
    {
        $agent = $turn->agent;
        $agent->loadMissing(['agentPrompt', 'llm']);

        $refusal = $this->refusal($agent);

        if ($refusal !== null) {
            return ChannelLocale::text($agent, $refusal);
        }

        try {
            $reply = $this->stripWebOnlyArtifactTags(trim($this->run($turn, $onProgress)));

            return $reply !== '' ? $reply : ChannelLocale::text($agent, 'Sorry — I could not produce a reply.');
        } catch (Throwable $e) {
            Log::error('Channel reply failed', [
                ...RedactedException::context($e),
                'source' => $turn->source->value,
                'agent_id' => $agent->id,
                ...$turn->logContext,
            ]);

            return ChannelLocale::text($agent, 'Sorry — something went wrong. Please try again.');
        }
    }

    /**
     * The fixed message (an English translation key) to send instead of
     * running the turn, or null when the turn may run.
     */
    private function refusal(Agent $agent): ?string
    {
        // Same monthly limit as the web chat, so every surface is treated alike.
        if (! $this->allowance->canSend($agent->workspace_id)) {
            return 'Sorry — this assistant is unavailable right now. Please try again later.';
        }

        // Its own model, or the default one while it has none (as on the web).
        $model = $this->models->for($agent);

        if (! $model instanceof Llm) {
            return 'Sorry — this assistant is not fully configured yet.';
        }

        // Per-agent chat rate limit (owner-configurable on the Security page).
        // The web SSE surface applies this in ChatStreamService; channels reach
        // the pipeline directly, so the cap is enforced here too — otherwise a
        // visitor could bypass it by messaging through Telegram/Slack/etc.
        if ($this->rateLimiter->exceeded($agent)) {
            return 'This assistant is receiving too many messages right now. Please try again in a moment.';
        }

        return null;
    }

    /**
     * Run the turn and return the final reply text. The pipeline sources
     * persist, bill and apply the output guards while the generator is
     * drained; the reply strategy reacts to progress through $onProgress.
     *
     * @param  (Closure(string): void)|null  $onProgress
     */
    private function run(ChannelTurnData $turn, ?Closure $onProgress): string
    {
        $agent = $turn->agent;
        // refusal() has made sure one can answer.
        $model = $this->models->for($agent) ?? throw new RuntimeException("Agent {$agent->external_id} has no model, and no model can answer.");

        $context = $this->chatService->initializeChatContext(new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(model: $model, message: $turn->message),
            externalSessionId: $turn->sessionExternalId,
            conversationId: $turn->conversationExternalId,
            saveUserMessageImmediately: true,
            source: $turn->source->value,
            requestId: $turn->requestIdPrefix.Str::uuid(),
        ));
        $context = $this->chatService->enrichContextWithKnowledge($context);

        $events = match (true) {
            $context->guardBlocked => $this->guardBlockSource->events($context),
            $context->hasQaMatch() => $this->qaSource->events($context),
            default => $this->llmSource->events($context, $this->chatService->prepareLlmMessages($context)),
        };

        foreach ($events as $_event) {
            if ($onProgress instanceof Closure) {
                $onProgress($context->streamedMessage);
            }
        }

        return $context->streamedMessage;
    }

    /**
     * Belt-and-braces for text-only channels. The forms prompt is already
     * withheld off the web (see SystemPromptBuilder), but if the
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
}

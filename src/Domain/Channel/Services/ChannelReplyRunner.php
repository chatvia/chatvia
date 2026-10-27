<?php

namespace Domain\Channel\Services;

use App\Identity\Workspace\Services\MessageAllowance;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentModel;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\Replies\StreamingReplyStrategy;
use Domain\Channel\Services\Replies\TypingReplyStrategy;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatTurnPreparer;
use Domain\Conversation\Services\ChatTurnStreamer;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\QaSource;
use Domain\Conversation\Services\Turn\SpectraTurnTagger;
use Domain\Guardrail\Services\GuardrailInspector;
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
 * Mirrors {@see ChatTurnStreamer}: the same deactivated-workspace stop
 * ({@see WorkspaceActivation}), the same workspace message limit
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
        private readonly ChatTurnPreparer $chatTurnPreparer,
        private readonly LlmSource $llmSource,
        private readonly QaSource $qaSource,
        private readonly GuardBlockSource $guardBlockSource,
        private readonly MessageAllowance $allowance,
        private readonly AgentChatRateLimiter $rateLimiter,
        private readonly AgentModel $models,
        private readonly WorkspaceActivation $activation,
        private readonly GuardrailInspector $guardrails,
        private readonly SpectraTurnTagger $spectraTagger,
    ) {}

    /**
     * @param  (Closure(string): void)|null  $onProgress  invoked with the reply so far after each drained pipeline event
     */
    public function reply(ChannelTurnData $turn, ?Closure $onProgress = null): string
    {
        $agent = $turn->agent;
        $agent->loadMissing(['agentPrompt', 'llm']);

        $refusal = $this->refusal($agent, $turn->sessionExternalId);

        if ($refusal !== null) {
            return ChannelLocale::text($agent, $refusal);
        }

        try {
            $reply = $this->stripWebOnlyArtifactTags(Str::trim($this->run($turn, $onProgress)));

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
     * running the turn, or null when the turn may run. `$visitorKey` names
     * the chat on the channel, for the per-visitor cap.
     *
     * Unlike the web chat there is no blocked-visitor check: a channel sender
     * has no network address or browser to fingerprint (see VisitorBlocklist).
     */
    private function refusal(Agent $agent, string $visitorKey): ?string
    {
        // A deactivated workspace's agents answer nowhere; the web chat sends
        // the same text. ChannelAccessGate normally stops the turn earlier.
        if (! $this->activation->isActive($agent->workspace_id)) {
            return 'Sorry — this assistant is unavailable right now. Please try again later.';
        }

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
        // The web SSE surface applies this in ChatTurnStreamer; channels reach
        // the pipeline directly, so the cap is enforced here too — otherwise a
        // visitor could bypass it by messaging through Telegram/Slack/etc.
        // The visitor's own cap comes first, as on the web.
        if ($this->rateLimiter->visitorExceeded($agent, $visitorKey)) {
            return 'You are sending messages too quickly. Please wait a moment and try again.';
        }

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

        // The channel resolvers create the conversation before the turn runs,
        // so Spectra attributes the turn's AI calls to it — the closure keeps
        // the lookup off the path while Spectra is not recording.
        $this->spectraTagger->tag(fn (): ?Conversation => Conversation::query()
            ->inSession($turn->sessionExternalId, $agent->id)
            ->where('external_id', $turn->conversationExternalId)
            ->first());

        $context = $this->chatTurnPreparer->initializeChatContext(new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(model: $model, message: $turn->message),
            externalSessionId: $turn->sessionExternalId,
            conversationId: $turn->conversationExternalId,
            saveUserMessageImmediately: true,
            source: $turn->source->value,
            requestId: $turn->requestIdPrefix.Str::uuid(),
        ));
        $context = $this->chatTurnPreparer->enrichContextWithKnowledge($context);

        $events = match (true) {
            $context->guardBlocked => $this->guardBlockSource->events($context),
            $context->hasQaMatch() => $this->qaSource->events($context),
            default => $this->llmSource->events($context, $this->chatTurnPreparer->prepareLlmMessages($context)),
        };

        // An answer held until the output guards checked it is not shown in
        // pieces (Slack and Teams edit the message as it grows) either.
        $showProgress = $onProgress instanceof Closure && ! $this->guardrails->holdsAnswers($agent);

        foreach ($events as $_event) {
            if ($showProgress) {
                $onProgress($context->streamedMessage);
            }
        }

        return $context->streamedMessage;
    }

    /**
     * Belt-and-braces for text-only channels. The form and calendar tools
     * refuse off the web, but if the model still improvises a web-only
     * `<render-form …>` / `<render-calendar …>` artifact tag as message text
     * (e.g. mimicking one left in this conversation's history), the raw tag
     * must never reach the channel. Drop it and tidy the blank lines it leaves
     * behind. A no-op for the overwhelming majority of replies.
     */
    private function stripWebOnlyArtifactTags(string $reply): string
    {
        if (! Str::contains($reply, ['<render-form', '<render-calendar'])) {
            return $reply;
        }

        return $reply
            |> (static fn (string $s): string => Str::replaceMatches('#<render-(form|calendar)\b[^>]*>.*?</render-\1>#is', '', $s) ?? $s)
            |> (static fn (string $s): string => Str::replaceMatches('#<render-(?:form|calendar)\b[^>]*?/?>#i', '', $s) ?? $s)
            |> (static fn (string $s): string => Str::replaceMatches("/[ \t]*\n{3,}/", "\n\n", $s) ?? $s)
            |> Str::trim(...);
    }
}

<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Billing\Services\ChatterMessageAllowance;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Llm\Services\TikToken;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Pricing\CostCalculator;

class StoreMessageAction
{
    public function __construct(
        private readonly LlmProviderInterface $llmProvider,
        private readonly ChatterMessageAllowance $chatterAllowance,
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(Agent $agent, ChatInputPayload|ChatOutputPayload $payload): Message
    {
        $isUserMessage = $payload instanceof ChatInputPayload;

        $session = $this->resolveSession($agent, $payload, $isUserMessage);

        $conversation = $this->resolveConversation($session, $payload);

        [$promptTokens, $completionTokens, $costInMicroCents] = $this->resolveUsage($payload, $isUserMessage);

        $message = Message::create($this->buildMessageAttributes(
            $payload,
            $conversation,
            $isUserMessage,
            $promptTokens,
            $completionTokens,
            $costInMicroCents,
        ));

        // Link any unattached files of this conversation to the user message
        // that introduced them. Lets UserChatBubble render chips per-message
        // instead of one strip per conversation. Only fires for user turns —
        // assistant turns never own uploaded files.
        if ($isUserMessage) {
            Attachment::query()
                ->where('chat_conversation_id', $conversation->id)
                ->whereNull('chat_message_id')
                ->update(['chat_message_id' => $message->id]);

            // Activity-based retention: a fresh user turn pushes the
            // horizon back out when we're close to it. The model's
            // threshold (3 days) keeps the update cheap — only writes
            // when the conversation is actually at risk of being pruned.
            $conversation->extendRetentionIfDueSoon($agent->messageRetentionInDays());

            // Count this send against the chatter's monthly limit. Stamped
            // only for authenticated chatters (session.user_id); the service
            // is a no-op for non-chatters and for chatters with no limit. Counted
            // here (not via chat_messages, which retention prunes) so the tally
            // is durable.
            if ($session->user) {
                $this->chatterAllowance->recordMessage($session->user);
            }
        } else {
            // Log token usage for the workspace owner's current billing cycle.
            // Assistant turns are the only ones that carry token usage; user
            // turns never do. Uses the provider's prompt+completion count
            // (resolveUsage) — the authoritative billed total — with a TikToken
            // estimate as a fallback when the provider exposes no usage.
            $this->recordTokenUsage($agent, $promptTokens, $completionTokens, $payload->message);
        }

        $this->dispatchAnalytics(
            $agent,
            $payload,
            $session,
            $isUserMessage,
            $promptTokens,
            $completionTokens,
        );

        return $message;
    }

    private function resolveSession(Agent $agent, ChatInputPayload|ChatOutputPayload $payload, bool $isUserMessage): Session
    {
        $userAgentData = [];
        if ($isUserMessage && $payload->user_agent) {
            $userAgentData = UserAgentParser::parse($payload->user_agent);
        }

        $locale = null;
        if ($isUserMessage && $payload->locale) {
            $locale = explode(',', $payload->locale)[0];
        }

        // Key on (external_id, agent_id) only — NOT user_id. On the hosted /chat
        // surface the session row is minted by TurnState::firstOrCreateSession
        // with just agent_id (the authenticated chatter isn't known there), so
        // keying on user_id here would never match that row and the chatter id
        // would never land on the session.
        $session = Session::firstOrCreate([
            'external_id' => $payload->external_session_id,
            'agent_id' => $agent->id,
        ], [
            'user_id' => $isUserMessage ? $payload->user_id : null,
            'ip_address' => $isUserMessage ? $payload->ip_address : null,
            'user_agent' => $isUserMessage ? $payload->user_agent : null,
            'device_type' => $userAgentData['device_type'] ?? null,
            'browser' => $userAgentData['browser'] ?? null,
            'os' => $userAgentData['os'] ?? null,
            'referrer_url' => $isUserMessage ? $payload->referrer_url ?? null : null,
            'page_url' => $isUserMessage ? $payload->page_url ?? null : null,
            'locale' => $locale,
        ]);

        $sessionUpdates = ['last_activity_at' => now()];

        // Backfill the chatter onto a session created without one (the TurnState
        // case above). Only fills a null — never reassigns an existing owner.
        if ($isUserMessage && $payload->user_id && $session->user_id === null) {
            $sessionUpdates['user_id'] = $payload->user_id;
        }

        $session->update($sessionUpdates);

        if ($session->ip_address && ! $session->country_id) {
            SessionLocationUnknown::dispatch($session);
        }

        return $session;
    }

    private function resolveConversation(Session $session, ChatInputPayload|ChatOutputPayload $payload): Conversation
    {
        /** @var Conversation $conversation */
        $conversation = Conversation::firstOrCreate(
            [
                'external_id' => $payload->external_conversation_id,
                'chat_session_id' => $session->id,
            ],
            // Failsafe: the conversation should already exist (created upfront by
            // ConversationManager). When it doesn't, derive the surface from the
            // message-level source — widget messages create a Widget conversation;
            // everything else (playground previews, api-driven) default to Hosted
            // Chat which is the standalone surface most callers exercise.
            [
                'source' => $payload->source === 'widget'
                    ? ConversationSource::Widget
                    : ConversationSource::HostedChat,
            ],
        );

        $conversation->update([
            'last_activity_at' => now(),
            'is_visible' => true,
        ]);

        return $conversation;
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?int}
     */
    private function resolveUsage(ChatInputPayload|ChatOutputPayload $payload, bool $isUserMessage): array
    {
        if ($isUserMessage) {
            return [null, null, null];
        }

        // 1. Inline usage from the streaming response — the authoritative,
        //    provider-agnostic billed count (prompt + completion). Captured by
        //    the transformer onto the context and carried on the payload. It
        //    carries no dollar cost, so cost stays null on this path.
        if ($payload->prompt_tokens !== null || $payload->completion_tokens !== null) {
            return [$payload->prompt_tokens, $payload->completion_tokens, null];
        }

        // 2. OpenRouter-style post-hoc lookup — real tokens AND dollar cost, but
        //    only for providers that support it (costs an extra HTTP round-trip).
        $generationId = $payload->generation_id;
        $usageData = ($generationId && $this->llmProvider->supportsCostTracking())
            ? $this->llmProvider->getGenerationCost($generationId)
            : null;

        $promptTokens = $usageData['tokens_prompt'] ?? null;
        $completionTokens = $usageData['tokens_completion'] ?? null;
        $costInDollars = $usageData['total_cost'] ?? null;
        $costInMicroCents = $costInDollars !== null
            ? CostCalculator::dollarsToMicroCents((float) $costInDollars)
            : null;

        // 3. When both token counts remain null, recordTokenUsage() falls back
        //    to a TikToken estimate of the reply text.
        return [$promptTokens, $completionTokens, $costInMicroCents];
    }

    /**
     * Add this assistant turn's token usage to the workspace owner's current
     * billing cycle. Prefers the provider's prompt+completion total (the
     * authoritative billed count); estimates with TikToken only when the
     * provider exposed no usage (e.g. a provider where supportsCostTracking()
     * is false).
     */
    private function recordTokenUsage(
        Agent $agent,
        ?int $promptTokens,
        ?int $completionTokens,
        ?string $content,
    ): void {
        $tokens = ($promptTokens ?? 0) + ($completionTokens ?? 0);

        if ($tokens === 0 && $content !== null && $content !== '') {
            // Estimation only — never let a tokenizer hiccup fail the
            // message-persist job; a missed estimate just skips logging.
            try {
                $tokens = TikToken::countTokens($content);
            } catch (\Throwable) {
                $tokens = 0;
            }
        }

        if ($tokens <= 0) {
            return;
        }

        // Workspace owner's billing pool (the real prompt+completion count).
        // The per-member budget is charged separately at a flat rate on the
        // user turn (see ChatterMessageAllowance::recordMessage).
        $this->creditSystem->setUserId($agent->user_id)->recordTokenUsage($tokens);
    }

    private function buildMessageAttributes(
        ChatInputPayload|ChatOutputPayload $payload,
        Conversation $conversation,
        bool $isUserMessage,
        ?int $promptTokens,
        ?int $completionTokens,
        ?int $costInMicroCents,
    ): array {
        $llmId = $payload->model
            ? Llm::where('external_name', $payload->model)->value('id')
            : null;

        // Tool artifacts (charts, etc.) live in `metadata.artifacts` so they
        // re-render on reload. `metadata.tool_calls` carries the list of
        // tool names this assistant turn invoked so the sticky-tool
        // resolver can keep recently-used tools in the router pool on
        // follow-up turns. User-side `metadata.attachments` records visitor
        // uploads so the chip re-renders on history reload.
        $metadata = null;
        if (! $isUserMessage) {
            $metadataBuf = [];
            if (! empty($payload->artifacts)) {
                $metadataBuf['artifacts'] = $payload->artifacts;
            }
            if (! empty($payload->tool_calls)) {
                $metadataBuf['tool_calls'] = $payload->tool_calls;
            }
            if ($metadataBuf !== []) {
                $metadata = $metadataBuf;
            }
        } elseif ($payload instanceof ChatInputPayload && ! empty($payload->attachments)) {
            $metadata = ['attachments' => $payload->attachments];
        }

        $generationId = ! $isUserMessage ? $payload->generation_id : null;

        return [
            'external_id' => $payload->external_message_id,
            'chat_conversation_id' => $conversation->id,
            'content' => $payload->message,
            'role' => $isUserMessage ? 'user' : 'assistant',
            'llm_id' => $llmId,
            'response_time_ms' => ! $isUserMessage ? $payload->response_time_ms : null,
            'generation_id' => $generationId,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_in_micro_cents' => $costInMicroCents,
            'confidence_score' => ! $isUserMessage ? $payload->confidence_score : null,
            'is_response_from_qa' => ! $isUserMessage ? $payload->is_response_from_qa : false,
            'agent_question_answer_id' => ! $isUserMessage ? $payload->agent_question_answer_id : null,
            'source' => $payload->source,
            'has_llm_found_answer' => ! $isUserMessage ? ($payload->has_llm_found_answer ?? true) : true,
            'metadata' => $metadata,
            'created_at' => $payload->sent_at,
            'updated_at' => $payload->sent_at,
        ];
    }

    private function dispatchAnalytics(
        Agent $agent,
        ChatInputPayload|ChatOutputPayload $payload,
        Session $session,
        bool $isUserMessage,
        ?int $promptTokens,
        ?int $completionTokens,
    ): void {
        // Analytics: dispatch MessageSent. The Action detects whether this is
        // the first message of the conversation (via the conv_metrics UPSERT
        // RETURNING xmax) and bumps conversations_started under the right
        // country/llm dimensions then — no separate ConversationStarted needed,
        // and the "by country" panel sees the resolved country instead of null.
        MessageSent::dispatch(
            $agent->id,
            $agent->user_id,
            $session->user_id,
            (string) $payload->external_conversation_id,
            (string) $payload->external_message_id,
            (string) $payload->external_session_id,
            $isUserMessage ? 'user' : 'assistant',
            $payload->model ?: null,
            $session->country?->code,
            ! $isUserMessage ? ($payload->response_time_ms ?? null) : null,
            $promptTokens,
            $completionTokens,
            $payload->sent_at,
        );

        AnalyzeMessageJob::dispatchIf($isUserMessage, $payload->external_message_id);

        // Conversation-level analysis (topics, sentiment, tone, language) runs
        // once at end-of-life via the scheduled app:analyze-conversations pass —
        // not here on the hot message-write path.
    }

}

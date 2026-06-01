<?php

namespace Domain\Chat\Actions\Messages;

use App\Country\Models\Country;
use App\LLM\Contracts\LLMProviderInterface;
use App\LLM\Operations\Pricing\CostCalculator;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Analytics\Services\IP2Country;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Chat\Data\Messages\ChatInputPayload;
use Domain\Chat\Data\Messages\ChatOutputPayload;
use Domain\Chat\Enums\ConversationSource;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;

class StoreChatMessageAction
{
    public function __construct(
        private readonly LLMProviderInterface $llmProvider,
    ) {}

    public function execute(Agent $agent, ChatInputPayload|ChatOutputPayload $payload): ChatMessage
    {
        $isUserMessage = $payload instanceof ChatInputPayload;

        $session = $this->resolveSession($agent, $payload, $isUserMessage);

        $conversation = $this->resolveConversation($session, $payload);

        [$promptTokens, $completionTokens, $costInMicroCents] = $this->resolveUsage($payload, $isUserMessage);

        $message = ChatMessage::create($this->buildMessageAttributes(
            $payload,
            $conversation,
            $isUserMessage,
            $promptTokens,
            $completionTokens,
            $costInMicroCents,
        ));

        $this->dispatchAnalytics(
            $agent,
            $payload,
            $session,
            $conversation,
            $isUserMessage,
            $promptTokens,
            $completionTokens,
        );

        return $message;
    }

    private function resolveSession(Agent $agent, ChatInputPayload|ChatOutputPayload $payload, bool $isUserMessage): ChatSession
    {
        $userAgentData = [];
        if ($isUserMessage && $payload->user_agent) {
            $userAgentData = UserAgentParser::parse($payload->user_agent);
        }

        $locale = null;
        if ($isUserMessage && $payload->locale) {
            $locale = explode(',', $payload->locale)[0];
        }

        $session = ChatSession::firstOrCreate([
            'external_id' => $payload->external_session_id,
            'agent_id' => $agent->id,
            'user_id' => $isUserMessage ? $payload->user_id : null,
        ], [
            'ip_address' => $isUserMessage ? $payload->ip_address : null,
            'user_agent' => $isUserMessage ? $payload->user_agent : null,
            'device_type' => $userAgentData['device_type'] ?? null,
            'browser' => $userAgentData['browser'] ?? null,
            'os' => $userAgentData['os'] ?? null,
            'referrer_url' => $isUserMessage ? $payload->referrer_url ?? null : null,
            'page_url' => $isUserMessage ? $payload->page_url ?? null : null,
            'locale' => $locale,
        ]);

        $session->update(['last_activity_at' => now()]);

        if ($session->ip_address && ! $session->country_id) {
            $this->updateSessionLocation($session);
        }

        return $session;
    }

    private function resolveConversation(ChatSession $session, ChatInputPayload|ChatOutputPayload $payload): ChatConversation
    {
        /** @var ChatConversation $conversation */
        $conversation = ChatConversation::firstOrCreate(
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
        $generationId = ! $isUserMessage ? $payload->generation_id : null;
        $usageData = ($generationId && $this->llmProvider->supportsCostTracking())
            ? $this->llmProvider->getGenerationCost($generationId)
            : null;

        $promptTokens = $usageData['tokens_prompt'] ?? null;
        $completionTokens = $usageData['tokens_completion'] ?? null;
        $costInDollars = $usageData['total_cost'] ?? null;
        $costInMicroCents = $costInDollars !== null
            ? CostCalculator::dollarsToMicroCents((float) $costInDollars)
            : null;

        return [$promptTokens, $completionTokens, $costInMicroCents];
    }

    private function buildMessageAttributes(
        ChatInputPayload|ChatOutputPayload $payload,
        ChatConversation $conversation,
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
        // follow-up turns. Both apply only to assistant rows.
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
        ChatSession $session,
        ChatConversation $conversation,
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

        $messagesCount = $conversation->messages()->count();

        AnalyzeConversationJob::dispatchIf(
            $messagesCount >= 5 && $messagesCount < 20,
            $conversation
        );
    }

    private function updateSessionLocation(ChatSession $session): void
    {
        $location = IP2Country::getLocationByIP($session->ip_address);

        if (! $location) {
            return;
        }

        $updateData = [];

        if ($location->countryCode) {
            $country = Country::where('code', $location->countryCode)->first();
            if ($country) {
                $updateData['country_id'] = $country->id;
            }
        }

        if (! $session->city && $location->cityName) {
            $updateData['city'] = $location->cityName;
        }

        if ($location->regionName) {
            $updateData['region'] = $location->regionName;
        }

        if ($location->zipCode) {
            $updateData['zip_code'] = $location->zipCode;
        }

        if ($location->timeZone) {
            $updateData['time_zone'] = $location->timeZone;
        }

        if ($location->isProxy !== null) {
            $updateData['is_proxy'] = $location->isProxy;
        }

        if (! empty($updateData)) {
            $session->update($updateData);
        }
    }
}

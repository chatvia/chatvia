<?php

namespace Domain\Chat\Jobs;

use App\Country\Models\Country;
use App\LLM\Contracts\LLMProviderInterface;
use App\LLM\Operations\CostCalculator;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Domain\Analytics\Events\MessageSentEvent;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Analytics\Services\IP2Country;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Chat\Data\ChatInputPayload;
use Domain\Chat\Data\ChatOutputPayload;
use Domain\Chat\Enums\ConversationSource;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreChatMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Agent $agent,
        public ChatInputPayload|ChatOutputPayload $payload,
    ) {
        $this->onQueue('messages');
    }

    public function handle(): void
    {
        $isUserMessage = $this->payload instanceof ChatInputPayload;

        $userAgentData = [];
        if ($isUserMessage && $this->payload->user_agent) {
            $userAgentData = UserAgentParser::parse($this->payload->user_agent);
        }

        $locale = null;
        if ($isUserMessage && $this->payload->locale) {
            $locale = explode(',', $this->payload->locale)[0];
        }

        $session = ChatSession::firstOrCreate([
            'external_id' => $this->payload->external_session_id,
            'agent_id' => $this->agent->id,
            'user_id' => $isUserMessage ? $this->payload->user_id : null,
        ], [
            'ip_address' => $isUserMessage ? $this->payload->ip_address : null,
            'user_agent' => $isUserMessage ? $this->payload->user_agent : null,
            'device_type' => $userAgentData['device_type'] ?? null,
            'browser' => $userAgentData['browser'] ?? null,
            'os' => $userAgentData['os'] ?? null,
            'referrer_url' => $isUserMessage ? $this->payload->referrer_url ?? null : null,
            'page_url' => $isUserMessage ? $this->payload->page_url ?? null : null,
            'locale' => $locale,
        ]);

        $session->update(['last_activity_at' => now()]);

        // Fetch and store location data if IP exists but country is not yet set
        if ($session->ip_address && ! $session->country_id) {
            $this->updateSessionLocation($session);
        }

        /** @var ChatConversation $conversation */
        $conversation = ChatConversation::firstOrCreate(
            [
                'external_id' => $this->payload->external_conversation_id,
                'chat_session_id' => $session->id,
            ],
            // Failsafe: the conversation should already exist (created upfront by
            // ConversationManager). When it doesn't, derive the surface from the
            // message-level source — widget messages create a Widget conversation;
            // everything else (playground previews, api-driven) default to Hosted
            // Chat which is the standalone surface most callers exercise.
            [
                'source' => $this->payload->source === 'widget'
                    ? ConversationSource::Widget
                    : ConversationSource::HostedChat,
            ],
        );

        $conversation->update([
            'last_activity_at' => now(),
            'is_visible' => true,
        ]);

        $llmId = $this->payload->model
            ? Llm::where('external_name', $this->payload->model)->value('id')
            : null;

        $generationId = ! $isUserMessage ? $this->payload->generation_id : null;
        $llmProvider = app(LLMProviderInterface::class);
        $usageData = ($generationId && $llmProvider->supportsCostTracking())
            ? $llmProvider->getGenerationCost($generationId)
            : null;

        $promptTokens = $usageData['tokens_prompt'] ?? null;
        $completionTokens = $usageData['tokens_completion'] ?? null;
        $costInDollars = $usageData['total_cost'] ?? null;
        $costInMicroCents = $costInDollars !== null
            ? CostCalculator::dollarsToMicroCents((float) $costInDollars)
            : null;

        // Tool artifacts (charts, etc.) live in `metadata.artifacts` so they
        // re-render on reload. `metadata.tool_calls` carries the list of
        // tool names this assistant turn invoked so the sticky-tool
        // resolver can keep recently-used tools in the router pool on
        // follow-up turns. Both apply only to assistant rows.
        $metadata = null;
        if (! $isUserMessage) {
            $metadataBuf = [];
            if (! empty($this->payload->artifacts)) {
                $metadataBuf['artifacts'] = $this->payload->artifacts;
            }
            if (! empty($this->payload->tool_calls)) {
                $metadataBuf['tool_calls'] = $this->payload->tool_calls;
            }
            if ($metadataBuf !== []) {
                $metadata = $metadataBuf;
            }
        }

        $messageData = [
            'external_id' => $this->payload->external_message_id,
            'chat_conversation_id' => $conversation->id,
            'content' => $this->payload->message,
            'role' => $isUserMessage ? 'user' : 'assistant',
            'llm_id' => $llmId,
            'response_time_ms' => ! $isUserMessage ? $this->payload->response_time_ms : null,
            'generation_id' => $generationId,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_in_micro_cents' => $costInMicroCents,
            'confidence_score' => ! $isUserMessage ? $this->payload->confidence_score : null,
            'is_response_from_qa' => ! $isUserMessage ? $this->payload->is_response_from_qa : false,
            'agent_question_answer_id' => ! $isUserMessage ? $this->payload->agent_question_answer_id : null,
            'source' => $this->payload->source,
            'has_llm_found_answer' => ! $isUserMessage ? ($this->payload->has_llm_found_answer ?? true) : true,
            'metadata' => $metadata,
        ];

        $messageData['created_at'] = $this->payload->sent_at;
        $messageData['updated_at'] = $this->payload->sent_at;

        ChatMessage::create($messageData);

        // Analytics: dispatch MessageSent. The Action detects whether this is
        // the first message of the conversation (via the conv_metrics UPSERT
        // RETURNING xmax) and bumps conversations_started under the right
        // country/llm dimensions then — no separate ConversationStarted needed,
        // and the "by country" panel sees the resolved country instead of null.
        MessageSentEvent::dispatch(
            $this->agent->id,
            (string) $this->payload->external_conversation_id,
            (string) $this->payload->external_message_id,
            (string) $this->payload->external_session_id,
            $isUserMessage ? 'user' : 'assistant',
            $this->payload->model ?: null,
            $session->country?->code,
            ! $isUserMessage ? ($this->payload->response_time_ms ?? null) : null,
            $promptTokens,
            $completionTokens,
            $this->payload->sent_at,
        );

        AnalyzeMessageJob::dispatchIf($isUserMessage, $this->payload->external_message_id);

        $messagesCount = $conversation->messages()->count();

        AnalyzeConversationJob::dispatchIf(
            $messagesCount >= 5 && $messagesCount < 20,
            $conversation
        );
    }

    /**
     * Update session with location data from IP address.
     */
    private function updateSessionLocation(ChatSession $session): void
    {
        $location = IP2Country::getLocationByIP($session->ip_address);

        if (! $location) {
            return;
        }

        $updateData = [];

        // Set country if found
        if ($location->countryCode) {
            $country = Country::where('code', $location->countryCode)->first();
            if ($country) {
                $updateData['country_id'] = $country->id;
            }
        }

        // Set city if not already set
        if (! $session->city && $location->cityName) {
            $updateData['city'] = $location->cityName;
        }

        // Set new location fields
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

    public function uniqueId(): string
    {
        return 'messages:store:id:'.$this->payload->external_message_id;
    }
}

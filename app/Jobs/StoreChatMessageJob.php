<?php

namespace App\Jobs;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Country;
use App\Models\Llm;
use App\Services\Infrastructure\LLM\OpenRouter;
use App\Services\IP2Country;
use App\Services\UserAgentParser;
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
            'id' => $this->payload->session_id,
            'agent_id' => $this->agent->id,
            'member_id' => $isUserMessage ? $this->payload->member_id : null,
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

        if ($session->ip_address && ! $session->country_id) {
            $countryCode = IP2Country::getCountryByIP($session->ip_address);
            if ($countryCode) {
                $country = Country::where('code', $countryCode)->first();
                if ($country) {
                    $session->update(['country_id' => $country->id]);
                }
            }
        }

        /** @var ChatConversation $conversation */
        $conversation = ChatConversation::firstOrCreate([
            'id' => $this->payload->conversation_id,
            'chat_session_id' => $this->payload->session_id,
        ]);

        $conversation->update(['last_activity_at' => now()]);

        $llmId = $this->payload->model
            ? Llm::where('external_name', $this->payload->model)->value('id')
            : null;

        $generationId = ! $isUserMessage ? $this->payload->generation_id : null;
        $usageData = $generationId ? OpenRouter::getGeneration($generationId) : null;

        $promptTokens = $usageData['tokens_prompt'] ?? null;
        $completionTokens = $usageData['tokens_completion'] ?? null;
        $cost = $usageData['total_cost'] ?? null;

        $messageData = [
            'id' => $this->payload->message_id,
            'chat_conversation_id' => $this->payload->conversation_id,
            'content' => $this->payload->message,
            'role' => $isUserMessage ? 'user' : 'assistant',
            'llm_id' => $llmId,
            'response_time_ms' => ! $isUserMessage ? $this->payload->response_time_ms : null,
            'generation_id' => $generationId,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost' => $cost,
            'confidence_score' => ! $isUserMessage ? $this->payload->confidence_score : null,
            'is_direct_response' => ! $isUserMessage ? $this->payload->is_direct_response : false,
            'agent_question_answer_id' => ! $isUserMessage ? $this->payload->agent_question_answer_id : null,
            'source' => $this->payload->source,
        ];

        if ($this->payload->sent_at) {
            $messageData['created_at'] = $this->payload->sent_at;
            $messageData['updated_at'] = $this->payload->sent_at;
        }

        ChatMessage::create($messageData);

        AnalyzeMessageJob::dispatchIf($isUserMessage, $this->payload->message_id);

        AnalyzeConversationJob::dispatchIf($conversation->messages()->count() >= 5, $conversation);
    }

    public function uniqueId(): string
    {
        return 'messages:store:id:'.$this->payload->message_id;
    }
}

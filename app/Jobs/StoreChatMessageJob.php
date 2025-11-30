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
use App\Models\User;
use App\Services\Infrastructure\EmotionAnalysis;
use App\Services\IP2Country;
use App\Services\TikToken;
use App\Services\TopicAnalysis;
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
        public string $sessionId,
        public string $role,
        public ?string $conversationId,
        public ?string $title,
        public string $messageId,
        public ChatInputPayload|ChatOutputPayload $payload
    ) {
        $this->onQueue('messages');
    }

    public function handle(EmotionAnalysis $emotionAnalysis, TopicAnalysis $topicAnalysis): void
    {
        $isInputMessage = $this->payload instanceof ChatInputPayload;

        $sessionId = $this->sessionId;

        // Parse user agent to extract device, browser, and OS information
        $userAgentData = [];
        if ($isInputMessage && $this->payload->user_agent) {
            $userAgentData = UserAgentParser::parse($this->payload->user_agent);
        }

        // Parse locale to extract primary language (e.g., "en-US" from "en-US,en;q=0.5")
        $locale = null;
        if ($isInputMessage && $this->payload->locale) {
            $locale = explode(',', $this->payload->locale)[0];
        }

        $session = ChatSession::firstOrCreate([
            'id' => $sessionId,
            'agent_id' => $this->agent->id,
            'member_id' => $isInputMessage ? $this->payload->member_id : null,
        ], [
            'ip_address' => $isInputMessage ? $this->payload->ip_address : null,
            'user_agent' => $isInputMessage ? $this->payload->user_agent : null,
            'device_type' => $userAgentData['device_type'] ?? null,
            'browser' => $userAgentData['browser'] ?? null,
            'os' => $userAgentData['os'] ?? null,
            'referrer_url' => $isInputMessage ? $this->payload->referrer_url ?? null : null,
            'page_url' => $isInputMessage ? $this->payload->page_url ?? null : null,
            'locale' => $locale,
        ]);

        // Update device info if not already set
        if ($isInputMessage && $this->payload->user_agent && (! $session->device_type || ! $session->browser || ! $session->os)) {
            $session->update([
                'device_type' => $session->device_type ?? $userAgentData['device_type'] ?? null,
                'browser' => $session->browser ?? $userAgentData['browser'] ?? null,
                'os' => $session->os ?? $userAgentData['os'] ?? null,
            ]);
        }

        // Get country from IP address if not already set
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
            'id' => $this->conversationId,
            'chat_session_id' => $sessionId,
        ], ['title' => $this->title]);

        // Find LLM by internal_name (model string)
        $llmId = null;
        if ($this->payload->model) {
            $llm = Llm::where('internal_name', $this->payload->model)->first();
            $llmId = $llm?->id;
        }

        $sentiment = null;
        $emotion = null;

        if ($this->payload instanceof ChatInputPayload) {
            $analysis = $emotionAnalysis->analyze($this->payload->message);
            $sentiment = $analysis['sentiment']->value;
            $emotion = $analysis['emotion']->value;
        }

        $messageData = [
            'id' => $this->messageId,
            'chat_conversation_id' => $this->conversationId,
            'content' => $this->payload->message,
            'role' => $this->role,
            'llm_id' => $llmId,
            'response_time_ms' => $this->payload?->response_time_ms ?? 0,
            'generation_id' => $this->payload?->generation_id ?? null,
            'token_count' => TikToken::countTokens($this->payload->message),
            'confidence_score' => $this->payload?->confidence_score ?? null,
            'sentiment' => $sentiment,
            'emotion' => $emotion,
            'is_direct_response' => $this->payload instanceof ChatOutputPayload ? $this->payload->is_direct_response : false,
            'agent_question_answer_id' => $this->payload instanceof ChatOutputPayload ? $this->payload->agent_question_answer_id : null,
        ];

        // Use the sent_at timestamp if provided to ensure correct message ordering
        if ($this->payload->sent_at) {
            $messageData['created_at'] = $this->payload->sent_at;
            $messageData['updated_at'] = $this->payload->sent_at;
        }

        ChatMessage::create($messageData);

        /** @var User $user */
        $user = $this->agent->user;

        if ($isInputMessage) {
            $user->creditSystem()->incrementMessagesUsage($this->agent->user_id);
        }

        // Topic analysis
        $conversation->refresh();

        if (! $conversation->chat_topic_id && $conversation->messages()->count() >= 4) {
            $topic = $topicAnalysis->analyze(
                $conversation
                    ->messages()
                    ->select('content')
                    ->implode('content', "\n")
            );
            $conversation->update(['chat_topic_id' => $topic->id]);
        }
    }

    public function uniqueId(): string
    {
        return $this->messageId;
    }
}

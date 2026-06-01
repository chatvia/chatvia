<?php

namespace Domain\Analytics\Actions;

use App\Billing\Enums\CapabilityEnum;
use App\TextAnalysis\LanguageDetection;
use App\TextAnalysis\ToneAnalysis;
use Domain\Analytics\Services\TopicAnalysis;
use Domain\Chat\Models\ChatConversation;

class AnalyzeConversationAction
{
    public function __construct(
        private readonly TopicAnalysis $topicAnalysis,
        private readonly ToneAnalysis $toneAnalysis,
        private readonly LanguageDetection $languageDetection,
    ) {}

    public function execute(ChatConversation $conversation): void
    {
        $messages = $conversation
            ->messages()
            ->select('content', 'role')
            ->get();

        if ($messages->isEmpty()) {
            return;
        }

        $allMessages = $messages
            ->pluck('content')
            ->implode("\n");

        $userMessages = $messages
            ->where('role', 'user')
            ->pluck('content')
            ->implode("\n");

        $updates = [];

        $this->detectLanguage($conversation, $allMessages, $updates);

        $this->detectTopic($conversation, $allMessages, $updates);

        $this->detectToneAndSentiment($conversation, $userMessages, $updates);

        if (! empty($updates)) {
            $conversation->update($updates);
        }
    }

    private function detectLanguage(ChatConversation $conversation, string $messages, array &$updates): void
    {
        if ($conversation->language_id) {
            return;
        }

        $language = $this->languageDetection->detect($messages);

        if ($language) {
            $updates['language_id'] = $language->id;
        }
    }

    private function detectTopic(ChatConversation $conversation, string $messages, array &$updates): void
    {
        if ($conversation->chat_topic_id) {
            return;
        }

        if ($conversation->messages()->count() < 4) {
            return;
        }

        if (! $this->ownerHasCapability($conversation, CapabilityEnum::TOPICS)) {
            return;
        }

        $topic = $this->topicAnalysis->analyze($messages, $conversation->agent);

        if ($topic) {
            $updates['chat_topic_id'] = $topic->id;
        }
    }

    private function detectToneAndSentiment(ChatConversation $conversation, string $userMessages, array &$updates): void
    {
        if ($conversation->tone && $conversation->sentiment) {
            return;
        }

        if (empty($userMessages)) {
            return;
        }

        // Sentiment + tone share a single LLM call — skip when the owner
        // can't see either result, so a Starter conversation doesn't burn
        // inference on data the customer will never view.
        if (! $this->ownerHasCapability($conversation, CapabilityEnum::SENTIMENT)
            && ! $this->ownerHasCapability($conversation, CapabilityEnum::TONE)
        ) {
            return;
        }

        $analysis = $this->toneAnalysis->analyze($userMessages);

        $updates['tone'] = $analysis['tone']->value;
        $updates['sentiment'] = $analysis['sentiment']->value;
    }

    private function ownerHasCapability(ChatConversation $conversation, CapabilityEnum $capability): bool
    {
        return $conversation->agent?->user?->hasCapability($capability) ?? false;
    }
}

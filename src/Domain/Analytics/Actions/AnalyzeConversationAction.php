<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Services\LanguageDetection;
use Domain\Analytics\Services\ToneAnalysis;
use Domain\Analytics\Services\TopicAnalysis;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Conversation\Models\Conversation;

class AnalyzeConversationAction
{
    public function __construct(
        private readonly TopicAnalysis $topicAnalysis,
        private readonly ToneAnalysis $toneAnalysis,
        private readonly LanguageDetection $languageDetection,
    ) {}

    public function execute(Conversation $conversation): void
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

    private function detectLanguage(Conversation $conversation, string $messages, array &$updates): void
    {
        if ($conversation->language_id) {
            return;
        }

        $language = $this->languageDetection->detect($messages);

        if ($language) {
            $updates['language_id'] = $language->id;
        }
    }

    private function detectTopic(Conversation $conversation, string $messages, array &$updates): void
    {
        if ($conversation->chat_topic_id) {
            return;
        }

        if ($conversation->messages()->count() < 4) {
            return;
        }

        if (! $this->teamHasCapability($conversation, CapabilityEnum::TOPICS)) {
            return;
        }

        $topic = $this->topicAnalysis->analyze($messages, $conversation->agent);

        if ($topic) {
            $updates['chat_topic_id'] = $topic->id;
        }
    }

    private function detectToneAndSentiment(Conversation $conversation, string $userMessages, array &$updates): void
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
        if (! $this->teamHasCapability($conversation, CapabilityEnum::SENTIMENT)
            && ! $this->teamHasCapability($conversation, CapabilityEnum::TONE)
        ) {
            return;
        }

        $analysis = $this->toneAnalysis->analyze($userMessages);

        $updates['tone'] = $analysis['tone']->value;
        $updates['sentiment'] = $analysis['sentiment']->value;
    }

    private function teamHasCapability(Conversation $conversation, CapabilityEnum $capability): bool
    {
        return $conversation->agent?->team?->hasCapability($capability) ?? false;
    }
}

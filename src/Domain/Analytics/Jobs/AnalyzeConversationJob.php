<?php

namespace Domain\Analytics\Jobs;

use App\Billing\Enums\CapabilityEnum;
use App\TextAnalysis\LanguageDetection;
use App\TextAnalysis\ToneAnalysis;
use Domain\Analytics\Services\TopicAnalysis;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeConversationJob implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public ChatConversation $conversation,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(
        TopicAnalysis $topicAnalysis,
        ToneAnalysis $toneAnalysis,
        LanguageDetection $languageDetection,
    ): void {
        $messages = $this->conversation
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

        $this->detectLanguage($allMessages, $languageDetection, $updates);

        $this->detectTopic($allMessages, $topicAnalysis, $updates);

        $this->detectToneAndSentiment($userMessages, $toneAnalysis, $updates);

        if (! empty($updates)) {
            $this->conversation->update($updates);
        }
    }

    private function detectLanguage(string $messages, LanguageDetection $languageDetection, array &$updates): void
    {
        if ($this->conversation->language_id) {
            return;
        }

        $language = $languageDetection->detect($messages);

        if ($language) {
            $updates['language_id'] = $language->id;
        }
    }

    private function detectTopic(string $messages, TopicAnalysis $topicAnalysis, array &$updates): void
    {
        if ($this->conversation->chat_topic_id) {
            return;
        }

        if ($this->conversation->messages()->count() < 4) {
            return;
        }

        if (! $this->ownerHasCapability(CapabilityEnum::TOPICS)) {
            return;
        }

        $topic = $topicAnalysis->analyze($messages, $this->conversation->agent);

        if ($topic) {
            $updates['chat_topic_id'] = $topic->id;
        }
    }

    private function detectToneAndSentiment(string $userMessages, ToneAnalysis $toneAnalysis, array &$updates): void
    {
        if ($this->conversation->tone && $this->conversation->sentiment) {
            return;
        }

        if (empty($userMessages)) {
            return;
        }

        // Sentiment + tone share a single LLM call — skip when the owner
        // can't see either result, so a Starter conversation doesn't burn
        // inference on data the customer will never view.
        if (! $this->ownerHasCapability(CapabilityEnum::SENTIMENT)
            && ! $this->ownerHasCapability(CapabilityEnum::TONE)
        ) {
            return;
        }

        $analysis = $toneAnalysis->analyze($userMessages);

        $updates['tone'] = $analysis['tone']->value;
        $updates['sentiment'] = $analysis['sentiment']->value;
    }

    private function ownerHasCapability(CapabilityEnum $capability): bool
    {
        return $this->conversation->agent?->user?->hasCapability($capability) ?? false;
    }
}

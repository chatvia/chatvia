<?php

namespace Domain\Analytics\Jobs;

use Domain\Chat\Models\ChatConversation;
use App\TextAnalysis\LanguageDetection;
use App\TextAnalysis\ToneAnalysis;
use Domain\Analytics\Services\TopicAnalysis;
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

        $analysis = $toneAnalysis->analyze($userMessages);

        $updates['tone'] = $analysis['tone']->value;
        $updates['sentiment'] = $analysis['sentiment']->value;
    }
}

<?php

namespace Domain\Analytics\Actions\Analysis;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\ConversationAnalysisData;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Support\Localization\Language\Models\Language;

/**
 * End-of-life conversation analysis. Run once per conversation by the scheduled
 * `chatvia:analytics:analyze-conversations` pass (~6h after creation), it makes a SINGLE
 * structured LLM call over the full transcript and records, in one shot:
 *  - language, tone and sentiment on the conversation
 *  - every existing topic that applies (multi-label) → conversation_topics rows
 *    + a ConversationTopicAssigned event each (rolls up analytics_agent_topic_daily)
 *  - emergent topic suggestions (no existing topic fit) → suggested_label rows
 *
 * The language, tone and sentiment are always stored; the topics only while
 * the agent has topics switched on.
 * `analyzed_at` is stamped exactly once, making the batch idempotent. When the
 * model's answer cannot be parsed nothing is saved and `analyzed_at` stays
 * null, so the next scheduled pass tries again instead of recording a
 * made-up "neutral" result. Every run that reaches the model counts one
 * `analysis_attempts`; the scheduled pass stops after
 * `chatvia.analytics.analysis.max_attempts`.
 */
class AnalyzeConversationAction
{
    public function __construct(
        private readonly ConversationAnalyzer $analyzer,
    ) {}

    public function execute(Conversation $conversation): void
    {
        if ($conversation->analyzed_at !== null) {
            return;
        }

        $agent = $conversation->agent;

        /** @var Collection<int, Message> $messages */
        $messages = $conversation->messages()->select('content', 'role')->get();

        // Nothing classifiable (no owning agent, or no visitor turns) — still
        // stamp analyzed_at so the scheduled scan stops re-selecting it.
        if (! $agent || ! $this->hasVisitorContent($messages)) {
            $conversation->update(['analyzed_at' => now()]);

            return;
        }

        // Counted before the call, so a run that throws uses up an attempt too.
        $this->countAttempt($conversation);

        $analysis = $this->analyzer->analyze($this->transcript($messages), $agent);

        if (! $analysis instanceof ConversationAnalysisData) {
            return;
        }

        $newlyAssignedTopicIds = DB::transaction(fn (): array => $this->store($conversation, $agent, $analysis));

        // Dispatch after commit so a rolled-back transaction never enqueues a
        // rollup increment for a topic row that didn't actually persist.
        foreach ($newlyAssignedTopicIds as $topicId) {
            ConversationTopicAssigned::dispatch($agent->id, $agent->workspace_id, $topicId, $conversation->created_at);
        }
    }

    /**
     * Through the base query, so this bookkeeping leaves `updated_at` alone.
     */
    private function countAttempt(Conversation $conversation): void
    {
        Conversation::query()->whereKey($conversation->getKey())->toBase()->increment('analysis_attempts');
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function hasVisitorContent(Collection $messages): bool
    {
        return $messages->contains(fn (Message $message): bool => $message->role === MessageRole::User && filled($message->content));
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function transcript(Collection $messages): string
    {
        return $messages
            ->map(fn (Message $message): string => Str::ucfirst($message->role->value).": {$message->content}")
            ->implode("\n");
    }

    /**
     * Save the analysis. Topics are the one dimension recorded straight from
     * this batch call (sentiment/tone roll up from the per-message pass). When
     * the agent has topics switched off, the already-paid-for language, tone
     * and sentiment are still stored, but no topic rows or rollup events.
     *
     * @return list<int> ids of topics newly assigned to the conversation
     */
    private function store(Conversation $conversation, Agent $agent, ConversationAnalysisData $analysis): array
    {
        $updates = [
            'analyzed_at' => now(),
            'sentiment' => $analysis->sentiment,
            'tone' => $analysis->tone,
        ];

        if ($languageId = $this->resolveLanguageId($analysis->language)) {
            $updates['language_id'] = $languageId;
        }

        $conversation->update($updates);

        if (! $agent->analyticsEnabled('topics')) {
            return [];
        }

        $assigned = [];

        foreach ($analysis->topicIds as $topicId) {
            $row = ConversationTopic::query()->firstOrCreate([
                'chat_conversation_id' => $conversation->id,
                'topic_id' => $topicId,
            ], ['workspace_id' => $conversation->workspace_id]);

            if ($row->wasRecentlyCreated) {
                $assigned[] = $topicId;
            }
        }

        foreach ($analysis->suggestedTopics as $label) {
            ConversationTopic::query()->firstOrCreate([
                'chat_conversation_id' => $conversation->id,
                'suggested_label' => $label,
            ], ['workspace_id' => $conversation->workspace_id]);
        }

        return $assigned;
    }

    private function resolveLanguageId(?string $code): ?int
    {
        if ($code === null) {
            return null;
        }

        return Language::query()->where('code', $code)->value('id');
    }
}

<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Support\Facades\DB;
use Support\Localization\Language\Models\Language;

/**
 * End-of-life conversation analysis. Run once per conversation by the scheduled
 * `app:analyze-conversations` pass (~6h after creation), it makes a SINGLE
 * structured LLM call over the full transcript and records, in one shot:
 *  - language, tone and sentiment on the conversation
 *  - every existing topic that applies (multi-label) → conversation_topics rows
 *    + a ConversationTopicAssigned event each (rolls up analytics_agent_topic_daily)
 *  - emergent topic suggestions (no existing topic fit) → suggested_label rows
 *
 * Everything the one call produces is stored — plan capabilities gate what the
 * customer can VIEW (enforced on the analytics routes), not what we analyze:
 * the inference is already spent, so discarding results would only waste it.
 * `analyzed_at` is stamped exactly once, making the batch idempotent. When the
 * model's answer cannot be parsed nothing is saved and `analyzed_at` stays
 * null, so the next scheduled pass tries again instead of recording a
 * made-up "neutral" result.
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

        $messages = $conversation->messages()->select('content', 'role')->get();

        $hasUserContent = $messages
            ->where('role', 'user')
            ->contains(fn ($message): bool => filled($message->content));

        // Nothing classifiable (no owning agent, or no user turns) — still stamp
        // analyzed_at so the scheduled scan stops re-selecting this conversation.
        if (! $agent || ! $hasUserContent) {
            $conversation->update(['analyzed_at' => now()]);

            return;
        }

        $transcript = $messages
            ->map(fn ($message): string => ucfirst($message->role).': '.$message->content)
            ->implode("\n");

        $analysis = $this->analyzer->analyze($transcript, $agent);

        if ($analysis === null) {
            return;
        }

        // Topics are the one dimension recorded straight from this batch call
        // (sentiment/tone roll up from the per-message pass instead). Honour the
        // agent's per-dimension opt-out: when topics are switched off we still
        // store the already-spent language/tone/sentiment results, but skip
        // creating topic rows and their rollup events so the Topics analytics
        // surface reflects the toggle.
        $topicsEnabled = $agent->analyticsEnabled('topics');

        $newlyAssignedTopicIds = DB::transaction(function () use ($conversation, $analysis, $topicsEnabled): array {
            $updates = [
                'analyzed_at' => now(),
                'sentiment' => $analysis->sentiment->value,
                'tone' => $analysis->tone->value,
            ];

            if ($languageId = $this->resolveLanguageId($analysis->language)) {
                $updates['language_id'] = $languageId;
            }

            $conversation->update($updates);

            if (! $topicsEnabled) {
                return [];
            }

            $assigned = [];

            foreach ($analysis->topicIds as $topicId) {
                $row = ConversationTopic::firstOrCreate([
                    'chat_conversation_id' => $conversation->id,
                    'topic_id' => $topicId,
                ]);

                if ($row->wasRecentlyCreated) {
                    $assigned[] = $topicId;
                }
            }

            foreach ($analysis->suggestedTopics as $label) {
                ConversationTopic::firstOrCreate([
                    'chat_conversation_id' => $conversation->id,
                    'suggested_label' => $label,
                ]);
            }

            return $assigned;
        });

        // Dispatch after commit so a rolled-back transaction never enqueues a
        // rollup increment for a topic row that didn't actually persist.
        foreach ($newlyAssignedTopicIds as $topicId) {
            ConversationTopicAssigned::dispatch($agent->id, $agent->user_id, $topicId, $conversation->created_at);
        }
    }

    private function resolveLanguageId(?string $code): ?int
    {
        if ($code === null) {
            return null;
        }

        return Language::where('code', $code)->value('id');
    }
}

<?php

namespace Domain\Analytics\Queries\Topics;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Topics\SuggestedTopicData;
use Domain\Analytics\Data\Topics\SuggestedTopicExampleData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Support\Casts\EncryptedTolerant;

/**
 * The emergent topics the conversation analysis suggests for an agent, most
 * frequent first. A label is listed once it came up in at least
 * `chatvia.analytics.analysis.min_occurrences` conversations and is not
 * already one of the agent's topics; each carries up to
 * `chatvia.analytics.analysis.max_examples` example conversations, fetched in
 * one window-function query for all labels.
 *
 * Only the agent's conversations of the last {@see self::WINDOW_DAYS} days
 * count ("recent conversations" in the UI). The filter goes through
 * `chat_conversations (agent_id, created_at)`, so a busy agent never reads its
 * whole history.
 */
class SuggestedTopicsQuery
{
    /** Label cardinality is unbounded, so the list is capped. */
    private const int MAX_SUGGESTIONS = 50;

    /** How far back a conversation still counts towards a suggestion. */
    public const int WINDOW_DAYS = 90;

    /**
     * @return Collection<int, SuggestedTopicData>
     */
    public function forAgent(Agent $agent): Collection
    {
        $labels = $this->frequentLabels($agent);
        $examples = $this->examplesByLabel($agent, $labels->pluck('suggested_label')->all());

        return $labels
            ->map(fn (ConversationTopic $row): SuggestedTopicData => new SuggestedTopicData(
                label: (string) $row->suggested_label,
                // `conversations` is a selectRaw count alias, not a model column.
                count: (int) $row->getAttribute('conversations'),
                examples: $examples[$row->suggested_label] ?? [],
            ))
            ->values();
    }

    /**
     * @return Collection<int, ConversationTopic>
     */
    private function frequentLabels(Agent $agent): Collection
    {
        $minOccurrences = (int) config('chatvia.analytics.analysis.min_occurrences', 3);

        // Labels already covered by a real topic shouldn't be suggested again.
        $existingNames = $agent->topics()
            ->pluck('topics.topic')
            ->map(fn (mixed $name): string => mb_strtolower((string) $name))
            ->all();

        return $this->forAgentConversations(ConversationTopic::query(), $agent)
            ->selectRaw('suggested_label, count(distinct chat_conversation_id) as conversations')
            ->whereNotNull('suggested_label')
            // In SQL, before the LIMIT: rejecting covered labels in PHP after
            // the cap would return fewer rows than uncovered labels exist.
            ->whereNotIn(DB::raw('lower(suggested_label)'), $existingNames)
            ->groupBy('suggested_label')
            ->havingRaw('count(distinct chat_conversation_id) >= ?', [$minOccurrences])
            ->orderByDesc('conversations')
            ->limit(self::MAX_SUGGESTIONS)
            ->get()
            ->values();
    }

    /**
     * Example conversations per label, capped per label by row_number().
     *
     * @param  list<string>  $labels
     * @return array<array-key, list<SuggestedTopicExampleData>>
     */
    private function examplesByLabel(Agent $agent, array $labels): array
    {
        $maxExamples = (int) config('chatvia.analytics.analysis.max_examples', 10);

        $ranked = $this->forAgentConversations(ConversationTopic::query(), $agent)
            ->select('suggested_label', 'chat_conversation_id')
            ->selectRaw('row_number() over (partition by suggested_label order by id) as rn')
            ->whereIn('suggested_label', $labels);

        $rows = ConversationTopic::query()
            ->fromSub($ranked, 'ranked')
            ->where('ranked.rn', '<=', $maxExamples)
            ->join('chat_conversations', 'chat_conversations.id', '=', 'ranked.chat_conversation_id')
            ->orderBy('ranked.rn')
            ->get(['ranked.suggested_label', 'chat_conversations.external_id', 'chat_conversations.title']);

        $examples = [];

        foreach ($rows as $row) {
            $examples[$row->suggested_label][] = new SuggestedTopicExampleData(
                conversation_id: (string) $row->getAttribute('external_id'),
                // Raw join bypasses the Conversation `title` cast, so the
                // column comes back ciphertext — decrypt it by hand.
                title: EncryptedTolerant::decrypt($row->getAttribute('title')),
            );
        }

        return $examples;
    }

    /**
     * @param  Builder<ConversationTopic>  $query
     * @return Builder<ConversationTopic>
     */
    private function forAgentConversations(Builder $query, Agent $agent): Builder
    {
        return $query->whereIn('chat_conversation_id', self::recentConversationIds($agent));
    }

    /**
     * The ids of the agent's conversations inside the suggestion window, as a
     * subquery.
     *
     * @return ConversationQueryBuilder<Conversation>
     */
    public static function recentConversationIds(Agent $agent): ConversationQueryBuilder
    {
        return Conversation::query()
            ->select('id')
            ->where('agent_id', $agent->id)
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS));
    }
}

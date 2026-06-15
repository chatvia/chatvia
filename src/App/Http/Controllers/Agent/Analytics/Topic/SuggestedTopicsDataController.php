<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Support\Casts\EncryptedTolerant;

class SuggestedTopicsDataController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        $minOccurrences = (int) config('chatvia.analytics.analysis.min_occurrences', 3);
        $maxExamples = (int) config('chatvia.analytics.analysis.max_examples', 10);

        // Labels already covered by a real topic shouldn't be re-suggested.
        $existingNames = $agent->topics()
            ->pluck('topics.topic')
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->all();

        // Label cardinality is unbounded — cap the list.
        $suggestionRows = ConversationTopic::query()
            ->selectRaw('suggested_label, count(distinct chat_conversation_id) as conversations')
            ->whereNotNull('suggested_label')
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
            ->groupBy('suggested_label')
            ->havingRaw('count(distinct chat_conversation_id) >= ?', [$minOccurrences])
            ->orderByDesc('conversations')
            ->limit(50)
            ->get()
            ->reject(fn ($row): bool => in_array(mb_strtolower((string) $row->suggested_label), $existingNames, true));

        // One examples query for all labels, capped per label by row_number().
        $examplesByLabel = ConversationTopic::query()
            ->fromSub(
                ConversationTopic::query()
                    ->select('suggested_label', 'chat_conversation_id')
                    ->selectRaw('row_number() over (partition by suggested_label order by id) as rn')
                    ->whereIn('suggested_label', $suggestionRows->pluck('suggested_label'))
                    ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id)),
                'ranked',
            )
            ->where('ranked.rn', '<=', $maxExamples)
            ->join('chat_conversations', 'chat_conversations.id', '=', 'ranked.chat_conversation_id')
            ->get(['ranked.suggested_label', 'chat_conversations.external_id', 'chat_conversations.title'])
            ->groupBy('suggested_label');

        $suggestions = $suggestionRows
            ->map(fn ($row): array => [
                'label' => $row->suggested_label,
                // `conversations` is a selectRaw count alias, not a model column.
                'count' => (int) $row->getAttribute('conversations'),
                'examples' => ($examplesByLabel[$row->suggested_label] ?? collect())
                    ->map(fn ($example): array => [
                        'conversation_id' => $example->external_id,
                        // Raw join bypasses the Conversation `title` cast, so the
                        // column comes back ciphertext — decrypt it by hand.
                        'title' => EncryptedTolerant::decrypt($example->title),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();

        return response()->json(['suggestions' => $suggestions]);
    }
}

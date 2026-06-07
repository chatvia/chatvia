<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedTopicsDataController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $minOccurrences = (int) config('chatvia.analytics.analysis.min_occurrences', 3);
        $maxExamples = (int) config('chatvia.analytics.analysis.max_examples', 10);

        // Labels already covered by a real topic shouldn't be re-suggested.
        $existingNames = $agent->topics()
            ->pluck('topics.topic')
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->all();

        $suggestions = ConversationTopic::query()
            ->selectRaw('suggested_label, count(distinct chat_conversation_id) as conversations')
            ->whereNotNull('suggested_label')
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
            ->groupBy('suggested_label')
            ->havingRaw('count(distinct chat_conversation_id) >= ?', [$minOccurrences])
            ->orderByDesc('conversations')
            ->get()
            ->reject(fn ($row): bool => in_array(mb_strtolower((string) $row->suggested_label), $existingNames, true))
            ->map(fn ($row): array => [
                'label' => $row->suggested_label,
                // `conversations` is a selectRaw count alias, not a model column.
                'count' => (int) $row->getAttribute('conversations'),
                'examples' => $this->examplesFor($agent, $row->suggested_label, $maxExamples),
            ])
            ->values();

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * @return list<array{conversation_id: string, title: string|null}>
     */
    private function examplesFor(Agent $agent, string $label, int $limit): array
    {
        return ConversationTopic::query()
            ->where('suggested_label', $label)
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
            ->with('conversation:id,external_id,title')
            ->limit($limit)
            ->get()
            ->pluck('conversation')
            ->filter()
            ->map(fn ($conversation): array => [
                'conversation_id' => $conversation->external_id,
                'title' => $conversation->title,
            ])
            ->values()
            ->all();
    }
}

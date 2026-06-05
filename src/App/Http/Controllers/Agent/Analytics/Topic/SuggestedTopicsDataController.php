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

        $minOccurrences = (int) config('analytics.analysis.min_occurrences', 3);
        $maxExamples = (int) config('analytics.analysis.max_examples', 10);

        // Labels already covered by a real topic shouldn't be re-suggested.
        $existingNames = $agent->topics()
            ->pluck('chat_topics.topic')
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->all();

        $suggestions = ConversationTopic::query()
            ->selectRaw('suggested_label, count(distinct chat_conversation_id) as conversations')
            ->whereNotNull('suggested_label')
            ->whereHas('conversation', fn ($query) => $query->forAgent($agent->id))
            ->groupBy('suggested_label')
            ->havingRaw('count(distinct chat_conversation_id) >= ?', [$minOccurrences])
            ->orderByDesc('conversations')
            ->get()
            ->reject(fn ($row) => in_array(mb_strtolower((string) $row->suggested_label), $existingNames, true))
            ->map(fn ($row) => [
                'label' => $row->suggested_label,
                'count' => (int) $row->conversations,
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
            ->whereHas('conversation', fn ($query) => $query->forAgent($agent->id))
            ->with('conversation:id,external_id,title')
            ->limit($limit)
            ->get()
            ->pluck('conversation')
            ->filter()
            ->map(fn ($conversation) => [
                'conversation_id' => $conversation->external_id,
                'title' => $conversation->title,
            ])
            ->values()
            ->all();
    }
}

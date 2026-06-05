<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DismissSuggestedTopicController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        // Drop the suggestion rows for this agent. The owning conversations keep
        // analyzed_at, so the label won't be regenerated.
        ConversationTopic::query()
            ->where('suggested_label', $validated['label'])
            ->whereHas('conversation', fn ($query) => $query->forAgent($agent->id))
            ->delete();

        return response()->json(['message' => 'Suggestion dismissed']);
    }
}

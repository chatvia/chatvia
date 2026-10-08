<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\PromoteSuggestedTopicAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoteSuggestedTopicController
{
    public function __invoke(Request $request, Agent $agent, PromoteSuggestedTopicAction $promote): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $topic = $promote->execute($agent, $validated['label']);

        return response()->json(['message' => 'Topic added successfully', 'topic' => $topic]);
    }
}

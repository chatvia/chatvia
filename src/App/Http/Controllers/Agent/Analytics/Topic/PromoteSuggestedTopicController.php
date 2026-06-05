<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\PromoteSuggestedTopicAction;
use Domain\Membership\Team\Exceptions\InviteRejectedException;
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

        try {
            $topic = $promote->execute($agent, $request->user(), $validated['label']);
        } catch (InviteRejectedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Topic added successfully', 'topic' => $topic]);
    }
}

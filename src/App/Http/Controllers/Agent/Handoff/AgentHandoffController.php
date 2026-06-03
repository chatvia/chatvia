<?php

namespace App\Http\Controllers\Agent\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\ListPendingHandoffsAction;
use Domain\Conversation\Data\Handoff\HandoffSummaryData;
use Domain\Conversation\Data\Handoff\PendingHandoffsResourceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\DataCollection;

/**
 * Pending-handoff feed for an agent (sidebar badge + list view). Mutations
 * each live in their own invokable controller in this namespace.
 */
class AgentHandoffController
{
    public function index(Agent $agent, ListPendingHandoffsAction $listPending): JsonResponse|PendingHandoffsResourceData
    {
        if (Gate::denies('access-agent', $agent)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $pending = $listPending->execute($agent);

        return new PendingHandoffsResourceData(
            pending: new DataCollection(HandoffSummaryData::class, $pending),
            count: $pending->count(),
        );
    }
}

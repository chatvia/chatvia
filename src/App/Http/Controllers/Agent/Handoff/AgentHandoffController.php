<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Resources\Handoff\PendingHandoffsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Queries\PendingHandoffsQuery;

/**
 * Pending-handoff feed for an agent (sidebar badge + list view). Access to the
 * agent is checked by the route's `can.access.agent` middleware. Mutations each
 * live in their own invokable controller in this namespace.
 */
class AgentHandoffController
{
    public function index(Agent $agent, PendingHandoffsQuery $pendingHandoffs): PendingHandoffsResourceData
    {
        return PendingHandoffsResourceData::fromConversations($pendingHandoffs->get($agent));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chatter;

use App\Http\Requests\Api\V1\Chatter\GrantApiChatterAgentsRequest;
use App\Identity\Chatter\Models\Chatter;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Illuminate\Http\Response;

/**
 * Grant a chatter access to one or more of your agents — the mirror of the
 * revoke endpoint. `workspace.scope` turns another workspace's chatter into
 * a 404.
 */
#[Group(name: 'Chatters', weight: 90)]
class GrantChatterAgentsController
{
    /**
     * Grant chatter agent access
     *
     * Grants the chatter access to the agents named by `agent_ids`. Granting
     * is idempotent — an agent the chatter already has is simply kept.
     */
    public function __invoke(GrantApiChatterAgentsRequest $request, Chatter $chatter, GrantAgentMembershipAction $grant): Response
    {
        $request->agents()->each(fn (Agent $agent) => $grant->execute($agent, $chatter));

        return response()->noContent();
    }
}

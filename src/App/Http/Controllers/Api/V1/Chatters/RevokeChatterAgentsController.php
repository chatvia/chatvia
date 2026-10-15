<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Requests\Api\V1\Chatter\RevokeApiChatterAgentsRequest;
use App\Identity\Chatter\Models\Chatter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

/**
 * Revoke a chatter's access to one or more of your agents by removing their
 * `agent_chatter` grants. The chatter account itself is untouched — to delete the
 * chatter entirely use `DELETE /api/v1/chatters/{chatter}`.
 * `workspace.scope` turns another workspace's chatter into a 404.
 */
#[Group('Chatters', weight: 90)]
class RevokeChatterAgentsController
{
    /**
     * Revoke chatter agent access
     *
     * Remove the chatter's access to the agents named by `agent_ids`. The
     * chatter keeps access to any agent not listed. Revoking is idempotent — an
     * agent the chatter wasn't granted is simply ignored.
     */
    public function __invoke(RevokeApiChatterAgentsRequest $request, Chatter $chatter): Response
    {
        // A single pivot detach — no Action needed.
        $chatter->agents()->detach($request->agents()->modelKeys());

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Controllers\Api\V1\Chatters\Concerns\ResolvesWorkspaceAgents;
use App\Identity\Workspace\Data\Account\RevokeChatterAgentsData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Revoke a chatter's access to one or more of your agents by removing their
 * `agent_user` grants. The chatter account itself is untouched — to delete the
 * chatter entirely use `DELETE /api/v1/chatters/{chatter}`.
 */
#[Group('Chatters', weight: 90)]
class RevokeChatterAgentsController
{
    use ResolvesWorkspaceAgents;

    /**
     * Revoke chatter agent access
     *
     * Remove the chatter's access to the agents named by `agent_ids`. The
     * chatter keeps access to any agent not listed. Revoking is idempotent — an
     * agent the chatter wasn't granted is simply ignored.
     */
    public function __invoke(Request $request, string $chatter, RevokeChatterAgentsData $payload): Response
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->workspace();

        // Scope to the caller's workspace — an unknown or foreign chatter 404s.
        $target = $owner->chatters()->where('external_id', $chatter)->firstOrFail();

        // The same ownership guard as creating a chatter: a foreign or unknown
        // agent id fails loudly instead of silently doing nothing.
        $agents = $this->workspaceAgentsOrFail($owner, $payload->agent_ids);

        // A single pivot detach — no Action needed.
        $target->accessibleAgents()->detach($agents->modelKeys());

        return response()->noContent();
    }
}

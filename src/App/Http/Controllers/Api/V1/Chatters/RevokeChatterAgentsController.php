<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Identity\Workspace\Data\Account\RevokeChatterAgentsData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Revoke a chatter's access to one or more of your agents by removing their
 * `agent_user` grants. The chatter account itself is untouched — to delete the
 * chatter entirely use `DELETE /api/v1/chatters/{chatter}`.
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
    public function __invoke(Request $request, string $chatter, RevokeChatterAgentsData $payload): Response
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->workspace();

        // Scope to the caller's workspace — an unknown or foreign chatter 404s.
        $target = $owner->chatters()->where('external_id', $chatter)->firstOrFail();

        // Resolve the agents scoped to the workspace — the same ownership guard
        // as creating a chatter, so a foreign or unknown agent id fails loudly
        // instead of silently no-opping.
        $agents = $owner->agents()->whereIn('external_id', $payload->agent_ids)->get();

        $missing = array_values(
            array_diff(array_unique($payload->agent_ids), $agents->pluck('external_id')->all())
        );

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'agent_ids' => 'Unknown agent(s): '.implode(', ', $missing).'.',
            ]);
        }

        $target->accessibleAgents()->detach($agents->pluck('id')->all());

        return response()->noContent();
    }
}

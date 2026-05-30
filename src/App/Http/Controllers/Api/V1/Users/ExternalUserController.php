<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Auth\Models\User;
use App\Http\Resources\Api\V1\UserData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Auth\Actions\CreateChatterAction;
use Domain\Auth\Data\CreateUserPayloadData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Your end-users. Create one here for a specific agent, then either hand them
 * a chat sign-in link via `POST /api/v1/agents/{agent}/sign-in-links` or let
 * them sign in directly (if you provided an `email` + `password`). End-users
 * never reach the dashboard — they can only chat with the agent(s) you've
 * granted them.
 */
#[Group('Users', weight: 30)]
class ExternalUserController
{
    /**
     * Create user
     *
     * Provisions an end-user under your account, granted access to the agent
     * named by `agent_id`. Returns the user's `id` — pass it when minting a
     * sign-in link.
     *
     * @return array{data: UserData}
     */
    public function store(Request $request, CreateUserPayloadData $payload, CreateChatterAction $action): array
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->belongsToWorkspace() ? $account->workspace : $account;

        $agent = $owner->agents()->where('external_id', $payload->agent_id)->first();

        if (! $agent instanceof Agent) {
            throw ValidationException::withMessages([
                'agent_id' => 'Unknown agent.',
            ]);
        }

        return ['data' => UserData::fromModel(
            $action->execute($owner, $agent, $payload->name, $payload->email, $payload->password)
        )];
    }
}

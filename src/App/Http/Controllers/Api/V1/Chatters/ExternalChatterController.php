<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Resources\Api\V1\Auth\UserData;
use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Workspace\Data\Account\CreateUserPayloadData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Your end-users (chatters). Create one here for one or more agents (with an
 * `email` + `password`), then either hand them a chat sign-in link via
 * `POST /api/v1/chatters/sign` or let them sign in directly. Chatters never
 * reach the dashboard — they can only chat with the agent(s) you've granted
 * them.
 */
#[Group('Chatters', weight: 90)]
class ExternalChatterController
{
    /**
     * Create chatter
     *
     * Provisions an end-user (chatter) under your account, granted access to
     * the agents named by `agent_ids`. Returns the chatter's `id` — pass it
     * when minting a sign-in link.
     *
     * @return array{data: UserData}
     */
    public function store(Request $request, CreateUserPayloadData $payload, CreateChatterAction $action): array
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->workspace();

        // Scope the requested agents to the caller's workspace — a chatter can
        // only be granted agents the workspace owns. Any id that doesn't resolve
        // to an owned agent fails validation, so a foreign id can't slip through.
        $agents = $owner->agents()->whereIn('external_id', $payload->agent_ids)->get();

        $missing = array_values(
            array_diff(array_unique($payload->agent_ids), $agents->pluck('external_id')->all())
        );

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'agent_ids' => 'Unknown agent(s): '.implode(', ', $missing).'.',
            ]);
        }

        return ['data' => UserData::fromModel(
            $action->execute($owner, $agents, $payload->name, $payload->email, $payload->password)
        )];
    }
}

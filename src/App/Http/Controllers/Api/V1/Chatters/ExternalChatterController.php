<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Resources\Api\V1\Auth\UserData;
use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Workspace\Data\Account\CreateUserPayloadData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Your end-users (chatters). Create one here for a specific agent (with an
 * `email` + `password`), then either hand them a chat sign-in link via
 * `POST /api/v1/chatters/sign` or let them sign in directly. Chatters never
 * reach the dashboard — they can only chat with the agent(s) you've granted
 * them.
 */
#[Group('Chatters', weight: 30)]
class ExternalChatterController
{
    /**
     * Create chatter
     *
     * Provisions an end-user (chatter) under your account, granted access to
     * the agent named by `agent_id`. Returns the chatter's `id` — pass it when
     * minting a sign-in link.
     *
     * @return array{data: UserData}
     */
    public function store(Request $request, CreateUserPayloadData $payload, CreateChatterAction $action): array
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->workspace();

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

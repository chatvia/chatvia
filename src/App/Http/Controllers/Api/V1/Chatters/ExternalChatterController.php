<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Controllers\Api\V1\Chatters\Concerns\ResolvesWorkspaceAgents;
use App\Http\Resources\Api\V1\Auth\UserData;
use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Data\Account\CreateUserPayloadData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

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
    use ResolvesWorkspaceAgents;

    /**
     * Create chatter
     *
     * Provisions an end-user (chatter) under your account, granted access to
     * the agents named by `agent_ids`. Returns the chatter's `id` — pass it
     * when minting a sign-in link.
     *
     * @return array{data: UserData}
     */
    public function store(Request $request, CreateUserPayloadData $payload, CreateChatterAction $createChatter): array
    {
        /** @var User $account */
        $account = $request->user();
        $workspace = $account->workspace();

        $chatter = $createChatter->execute(new CreateChatterData(
            workspace: $workspace,
            agents: $this->workspaceAgentsOrFail($workspace, $payload->agent_ids),
            name: $payload->name,
            email: $payload->email,
            password: $payload->password,
        ));

        return ['data' => UserData::fromModel($chatter)];
    }
}

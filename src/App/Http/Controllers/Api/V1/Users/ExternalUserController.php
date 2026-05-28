<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Auth\Models\User;
use App\Http\Resources\Api\V1\UserData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Auth\Actions\CreateExternalUserAction;
use Domain\Auth\Data\CreateUserPayloadData;
use Illuminate\Http\Request;

/**
 * Your end-users. Create one here, then hand it a chat sign-in link via
 * `POST /api/v1/agents/{agent}/sign-in-links`. These users never sign in to the
 * dashboard — they only reach an agent's chat through a link.
 */
#[Group('Users', weight: 30)]
class ExternalUserController
{
    /**
     * Create user
     *
     * Provisions an end-user under your account and returns its `id` — pass that
     * `id` when minting a sign-in link.
     *
     * @return array{data: UserData}
     */
    public function store(Request $request, CreateUserPayloadData $payload, CreateExternalUserAction $action): array
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->isManagedUser() ? $account->manager : $account;

        return ['data' => UserData::fromModel($action->execute($owner, $payload->name, $payload->email))];
    }
}

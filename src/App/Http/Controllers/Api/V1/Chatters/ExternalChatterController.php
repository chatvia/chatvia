<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Requests\Api\V1\Chatter\StoreApiChatterRequest;
use App\Http\Resources\Api\V1\Auth\UserData;
use App\Identity\Chatter\Actions\CreateChatterAction;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

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
     */
    public function store(StoreApiChatterRequest $request, CreateChatterAction $createChatter): JsonResponse
    {
        return response()->json(['data' => UserData::fromModel($createChatter->execute($request->toData()))], 201);
    }
}

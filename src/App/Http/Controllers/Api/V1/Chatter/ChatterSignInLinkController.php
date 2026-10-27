<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chatter;

use App\Http\Requests\Api\V1\Chatter\StoreChatterSignInLinkRequest;
use App\Http\Resources\Api\V1\Auth\SignInLinkData;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * Mint a sign-in link that logs one of your end-users (chatters) into one of
 * your agents' chat — pass the agent's `id` plus the chatter's `id` from
 * `POST /api/v1/chatters`. The link is valid until it expires (default 1 hour)
 * and can be opened more than once within that window.
 */
#[Group('Chatters', weight: 90)]
class ChatterSignInLinkController
{
    public function __construct(
        private readonly AgentSignInTokens $tokens,
    ) {}

    /**
     * Sign a chatter
     *
     * Mint a sign-in link for one of your end-users (chatters) into one of your agents.
     */
    public function store(StoreChatterSignInLinkRequest $request): JsonResponse
    {
        $agent = $request->agent();

        $result = $this->tokens->issue(
            user: $request->signee(),
            agent: $agent,
            ttlSeconds: $request->ttlSeconds(),
        );

        return response()->json(['data' => new SignInLinkData(
            url: "{$agent->chat_url}?token={$result['token']}",
            expires_at: $result['expires_at'],
        )], 201);
    }
}

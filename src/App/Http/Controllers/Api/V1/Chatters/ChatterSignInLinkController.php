<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Controllers\Concerns\ResolvesAccountAgent;
use App\Http\Resources\Api\V1\Auth\SignInLinkData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Data\Api\SignInLinkPayloadData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mint a sign-in link that logs one of your end-users (chatters) into one of
 * your agents' chat — pass the agent's `id` plus the chatter's `id` from
 * `POST /api/v1/chatters`. The link is valid until it expires (default 1 hour)
 * and can be opened more than once within that window.
 */
#[Group('Chatters', weight: 90)]
class ChatterSignInLinkController
{
    use ResolvesAccountAgent;

    public function __construct(
        private readonly AgentSignInTokenService $tokens,
    ) {}

    /**
     * Sign a chatter
     *
     * Mint a sign-in link for one of your end-users (chatters) into one of your agents.
     */
    public function store(Request $request, SignInLinkPayloadData $payload): JsonResponse
    {
        $agent = $this->resolveAccountAgent($request, $payload->agent_id);

        /** @var User $account */
        $account = $request->user();
        $workspace = $account->workspace();

        // The link may sign in a chatter of this workspace or one of its staff
        // (owner or teammate). Ids outside this workspace (or not ids at all)
        // are 404s.
        abort_unless(Str::isUuid($payload->user_id), 404);

        $user = $workspace->chatters()->where('external_id', $payload->user_id)->first()
            ?? $workspace->members()->where('users.external_id', $payload->user_id)->firstOrFail();

        $result = $this->tokens->issue(
            user: $user,
            agent: $agent,
            ttlSeconds: $payload->ttl_seconds ?? AgentSignInTokenService::DEFAULT_TTL_SECONDS,
        );

        return response()->json(['data' => new SignInLinkData(
            url: $agent->chat_url.'?token='.$result['token'],
            expires_at: $result['expires_at'],
        )], 201);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Chatters;

use App\Http\Controllers\Concerns\ResolvesAccountAgent;
use App\Http\Resources\Api\V1\Auth\SignInLinkData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Data\Api\SignInLinkPayloadData;
use Illuminate\Http\Request;

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
     *
     * @return array{data: SignInLinkData}
     */
    public function store(Request $request, SignInLinkPayloadData $payload): array
    {
        $agent = $this->resolveAccountAgent($request, $payload->agent_id);

        /** @var User $account */
        $account = $request->user();
        $owner = $account->workspace();

        // `users()` is a BelongsToMany on User → related model is User by
        // construction, but the relation's static type erases to Model. The
        // `@var` brings the concrete type back so the `issue()` call below
        // accepts it.
        /** @var User $user */
        $user = $owner->external_id === $payload->user_id ? $owner :
            $owner->users()->where('external_id', $payload->user_id)->firstOrFail();

        $result = $this->tokens->issue(
            user: $user,
            agent: $agent,
            ttlSeconds: $payload->ttl_seconds ?? AgentSignInTokenService::DEFAULT_TTL_SECONDS,
        );

        return ['data' => new SignInLinkData(
            url: $agent->chat_url.'?token='.$result['token'],
            expires_at: $result['expires_at'],
        )];
    }
}

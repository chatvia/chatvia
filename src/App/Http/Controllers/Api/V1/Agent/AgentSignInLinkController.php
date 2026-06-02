<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Workspace\Models\User;
use App\Workspace\Services\AgentSignInTokenService;
use App\Http\Resources\Api\V1\Auth\SignInLinkData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Data\Api\SignInLinkPayloadData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * Mint a sign-in link that logs one of your end-users into an agent's chat —
 * pass the user's `id` from `POST /api/v1/users`. The link is valid until it
 * expires (default 1 hour) and can be opened more than once within that window.
 */
#[Group(name: 'Agents', weight: 10)]
class AgentSignInLinkController
{
    public function __construct(
        private readonly AgentSignInTokenService $tokens,
    ) {}

    /**
     * Create sign-in link
     *
     * Returns a ready-to-open `url`. Redirect your end-user to it; they land in
     * the agent's chat signed in as that user.
     *
     * @return array{data: SignInLinkData}
     */
    public function store(Request $request, Agent $agent, SignInLinkPayloadData $payload): array
    {
        /** @var User $account */
        $account = $request->user();
        $owner = $account->belongsToWorkspace() ? $account->workspace : $account;

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

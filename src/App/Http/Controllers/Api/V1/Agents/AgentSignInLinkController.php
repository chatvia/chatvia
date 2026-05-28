<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Auth\Models\User;
use App\Auth\Services\AgentSignInTokenService;
use App\Http\Resources\Api\V1\SignInLinkData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Data\SignInLinkPayloadData;
use Domain\Agents\Models\Agent;
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
        $owner = $account->isManagedUser() ? $account->manager : $account;

        $user = $owner->externalUsers()->where('external_id', $payload->user_id)->firstOrFail();

        $result = $this->tokens->generate(
            agent: $agent,
            user: $user,
            ttlSeconds: $payload->ttl_seconds ?? AgentSignInTokenService::DEFAULT_TTL_SECONDS,
        );

        return ['data' => new SignInLinkData(
            url: $agent->chat_url.'?token='.$result['token'],
            expires_at: $result['expires_at'],
        )];
    }
}

<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Identity\Workspace\Enums\SocialProvider;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Kick off the OAuth flow for a chatter accepting an agent invitation through
 * Google / Slack / etc. The URL carries the invitation `token`; we resolve
 * the {@see AgentChatterInvitation} row, stash its id in the session so the callback
 * can finalize against the same row, and redirect to the provider.
 */
class AgentInvitationSocialStartController
{
    public function __invoke(Request $request, string $token, string $provider): RedirectResponse
    {
        $providerEnum = SocialProvider::tryFrom($provider);

        abort_if($providerEnum === null, 404);

        $invitation = AgentChatterInvitation::query()->where('token', $token)->first();

        abort_if($invitation === null || $invitation->hasExpired(), 404);

        $request->session()->put('pending_agent_invitation', [
            'invitation_id' => $invitation->id,
            'provider' => $providerEnum->value,
        ]);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($providerEnum->value);

        return $driver
            ->stateless()
            ->redirectUrl(route('agents.invitation.social.callback', ['provider' => $providerEnum->value]))
            ->redirect();
    }
}

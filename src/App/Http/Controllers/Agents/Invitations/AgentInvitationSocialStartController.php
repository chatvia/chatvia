<?php

namespace App\Http\Controllers\Agents\Invitations;

use App\Http\Controllers\Controller;
use Domain\Agents\Models\ChatterInvitation;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Support\Enums\User\SocialProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Kick off the OAuth flow for a chatter accepting an agent invitation through
 * Google / Slack / etc. The URL carries the invitation `token`; we resolve
 * the {@see ChatterInvitation} row, stash its id in the session so the callback
 * can finalize against the same row, and redirect to the provider.
 */
class AgentInvitationSocialStartController extends Controller
{
    public function __invoke(Request $request, string $token, string $provider): RedirectResponse
    {
        $providerEnum = SocialProvider::tryFrom($provider);

        abort_if($providerEnum === null, 404);

        $invitation = ChatterInvitation::query()->where('token', $token)->first();

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

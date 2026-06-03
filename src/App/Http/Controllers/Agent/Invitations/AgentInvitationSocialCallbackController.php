<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Http\Controllers\Controller;
use App\Identity\Chatter\Services\ChatterAuthTokenService;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Exceptions\AgentMemberInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentMemberInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Support\Enums\User\SocialProvider;
use Throwable;

/**
 * OAuth callback for the agent-invitation social flow. The pending invitation
 * id was stashed in the session by {@see AgentInvitationSocialStartController};
 * we read it back, look up the row, resolve the social user from the provider,
 * hand off to the accept action, delete the invitation, and redirect to the
 * agent's subdomain with an `auth_token` (the bare callback host can't share
 * a session with the subdomain).
 */
class AgentInvitationSocialCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        AcceptAgentInvitationAction $accept,
        ChatterAuthTokenService $authTokens,
    ): RedirectResponse {
        $providerEnum = SocialProvider::tryFrom($provider);

        abort_if($providerEnum === null, 404);

        $pending = $request->session()->pull('pending_agent_invitation');

        if (! is_array($pending) || ($pending['provider'] ?? null) !== $providerEnum->value) {
            return redirect()->route('login')->with('error', 'Your invitation link expired. Ask for a fresh one.');
        }

        $invitation = AgentMemberInvitation::query()
            ->with('agent')
            ->find($pending['invitation_id'] ?? 0);

        if ($invitation === null || $invitation->hasExpired()) {
            return redirect()->route('login')->with('error', 'Your invitation link expired. Ask for a fresh one.');
        }

        $agent = $invitation->agent;

        if ($agent === null) {
            return redirect()->route('login')->with('error', 'That agent is no longer available.');
        }

        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($providerEnum->value);
            $socialUser = $driver
                ->stateless()
                ->redirectUrl(route('agents.invitation.social.callback', ['provider' => $providerEnum->value]))
                ->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('error', "Couldn't complete the {$providerEnum->value} sign-in.");
        }

        try {
            $chatter = $accept->executeWithSocial(
                agent: $agent,
                invitedEmail: $invitation->email,
                socialEmail: (string) ($socialUser->getEmail() ?? ''),
                socialName: (string) ($socialUser->getName() ?? $socialUser->getNickname() ?? 'Member'),
                provider: $providerEnum,
                providerId: (string) $socialUser->getId(),
            );
        } catch (AgentMemberInvitationRejectedException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        $invitation->delete();

        $authToken = $authTokens->generateToken($agent, $chatter, 5);

        return redirect()->away($agent->chat_url.'?auth_token='.$authToken);
    }
}

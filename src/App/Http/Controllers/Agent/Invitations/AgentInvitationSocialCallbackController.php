<?php

namespace App\Http\Controllers\Agent\Invitations;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Enums\SocialProvider;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Data\AcceptAgentInvitationData;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Support\Http\PublicUrl;
use Throwable;

/**
 * OAuth callback for the agent-invitation social flow. The pending invitation
 * id was stashed in the session by {@see AgentInvitationSocialStartController};
 * we read it back, look up the row, resolve the social user from the provider,
 * and hand off to the accept action — which checks expiry and deletes the
 * invitation in the same transaction that activates the chatter. Then we
 * redirect into the agent's hosted chat with an `auth_token` (the chat has its
 * own session, separate from this callback's).
 */
class AgentInvitationSocialCallbackController
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
            return redirect()->route('login')->with('error', __('Your invitation link expired. Ask for a fresh one.'));
        }

        $invitation = AgentChatterInvitation::query()
            ->with('agent')
            ->find($pending['invitation_id'] ?? 0);

        if ($invitation === null) {
            return redirect()->route('login')->with('error', __('Your invitation link expired. Ask for a fresh one.'));
        }

        $agent = $invitation->agent;

        if ($agent === null) {
            return redirect()->route('login')->with('error', __('That agent is no longer available.'));
        }

        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($providerEnum->value);
            $socialUser = $driver
                ->stateless()
                ->redirectUrl(PublicUrl::route('agents.invitation.social.callback', ['provider' => $providerEnum->value]))
                ->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', __("Couldn't complete the :provider sign-in.", ['provider' => $providerEnum->value]));
        }

        try {
            $chatter = $accept->execute(new AcceptAgentInvitationData(
                invitation: $invitation,
                name: (string) ($socialUser->getName() ?? $socialUser->getNickname() ?? 'Member'),
                kind: InvitationCredentialKind::Social,
                socialEmail: (string) ($socialUser->getEmail() ?? ''),
                socialProvider: $providerEnum,
                socialProviderId: (string) $socialUser->getId(),
            ));
        } catch (AgentChatterInvitationRejectedException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        $authToken = $authTokens->generateToken($agent, $chatter, 5);

        return redirect()->away($agent->chat_url.'?auth_token='.$authToken);
    }
}

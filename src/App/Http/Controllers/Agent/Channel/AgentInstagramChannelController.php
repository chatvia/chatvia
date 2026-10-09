<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use App\Http\Requests\Agent\Channel\SelectInstagramAccountRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\CompleteMetaOAuthAction;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Instagram\Actions\ConnectInstagramAccountAction;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connects an agent's Instagram professional account through Facebook Login for
 * Business — one Chatvia Meta app for every workspace. Same shape as
 * {@see AgentMessengerChannelController}: redirect → callback → select. The IG
 * account is reached through its linked Facebook Page (the Page token sends the
 * DMs); Instagram may have its own Login-for-Business configuration and
 * callback URL. See {@see InteractsWithMetaOAuth} for the session handling.
 * Messaging channels are public-agents only.
 */
class AgentInstagramChannelController
{
    use InteractsWithMetaOAuth;

    public function redirect(Agent $agent, MetaOAuthClient $oauth): RedirectResponse
    {
        return $this->startMetaOAuth($agent, MetaOAuthChannel::Instagram, $oauth);
    }

    public function callback(Request $request, CompleteMetaOAuthAction $complete): RedirectResponse
    {
        return $this->completeMetaOAuth($request, MetaOAuthChannel::Instagram, $complete);
    }

    public function select(SelectInstagramAccountRequest $request, Agent $agent, ConnectInstagramAccountAction $connect): RedirectResponse
    {
        $chosen = collect(self::pendingAccounts($agent, MetaOAuthChannel::Instagram))
            ->firstWhere('ig_id', $request->igId());

        if (! is_array($chosen)) {
            return back()->withErrors(['instagram' => __('That account selection expired. Please reconnect.')]);
        }

        try {
            $connect->execute($agent, InstagramAccountData::from($chosen));
        } catch (ChannelConnectException $e) {
            return back()->withErrors(['instagram' => match ($e->failure) {
                ChannelConnectFailure::ProtectedAgent => self::publicAgentsOnly('Instagram'),
                default => __('That Instagram account is already connected to another agent. Disconnect it there first.'),
            }]);
        }

        self::forgetPendingAccounts(MetaOAuthChannel::Instagram);

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->instagramAccount?->delete();
        self::forgetPendingAccounts(MetaOAuthChannel::Instagram);

        return back();
    }
}

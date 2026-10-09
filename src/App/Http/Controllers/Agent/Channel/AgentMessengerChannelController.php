<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use App\Http\Requests\Agent\Channel\SelectMessengerPageRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\CompleteMetaOAuthAction;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connects an agent's Facebook Page through Facebook Login for Business — one
 * Chatvia Meta app for every workspace, so customers click "Connect with
 * Facebook" instead of pasting tokens. The flow:
 *
 *   redirect()  → stash the pending connection in the session, bounce to consent
 *   callback()  → verify the returned state, list the granted Pages for the picker
 *   select()    → the workspace picks which Page powers this agent; store + subscribe it
 *
 * See {@see InteractsWithMetaOAuth} for the session handling. Disconnect is a
 * single delete. All gated by `agent.permission:agent.channels` (the callback
 * by the session state). Messaging channels are public-agents only.
 */
class AgentMessengerChannelController
{
    use InteractsWithMetaOAuth;

    public function redirect(Agent $agent, MetaOAuthClient $oauth): RedirectResponse
    {
        return $this->startMetaOAuth($agent, MetaOAuthChannel::Messenger, $oauth);
    }

    public function callback(Request $request, CompleteMetaOAuthAction $complete): RedirectResponse
    {
        return $this->completeMetaOAuth($request, MetaOAuthChannel::Messenger, $complete);
    }

    public function select(SelectMessengerPageRequest $request, Agent $agent, ConnectMessengerPageAction $connect): RedirectResponse
    {
        $chosen = collect(self::pendingAccounts($agent, MetaOAuthChannel::Messenger))
            ->firstWhere('id', $request->pageId());

        if (! is_array($chosen)) {
            return back()->withErrors(['messenger' => __('That Page selection expired. Please reconnect.')]);
        }

        try {
            $connect->execute($agent, MetaPageData::from($chosen));
        } catch (ChannelConnectException $e) {
            return back()->withErrors(['messenger' => match ($e->failure) {
                ChannelConnectFailure::ProtectedAgent => self::publicAgentsOnly('Messenger'),
                default => __('That Facebook Page is already connected to another agent. Disconnect it there first.'),
            }]);
        }

        self::forgetPendingAccounts(MetaOAuthChannel::Messenger);

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->messengerPage?->delete();
        self::forgetPendingAccounts(MetaOAuthChannel::Messenger);

        return back();
    }
}

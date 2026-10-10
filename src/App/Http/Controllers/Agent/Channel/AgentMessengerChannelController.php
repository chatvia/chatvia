<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use App\Http\Requests\Agent\Channel\SelectMessengerPageRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Illuminate\Http\RedirectResponse;

/**
 * Connects an agent's Facebook Page through Facebook Login for Business — one
 * Chatvia Meta app for every workspace, so customers click "Connect with
 * Facebook" instead of pasting tokens. The flow:
 *
 *   {@see AgentMessengerOAuthRedirectController} → stash the pending connection, bounce to consent
 *   {@see AgentMessengerOAuthCallbackController} → verify the returned state, list the granted Pages
 *   store()   → the workspace picks which Page powers this agent; store + subscribe it
 *   destroy() → disconnect (a single delete)
 *
 * See {@see InteractsWithMetaOAuth} for the session handling. All gated by
 * `agent.permission:agent.channels` (the callback by the session state).
 * Messaging channels are public-agents only.
 */
class AgentMessengerChannelController
{
    use InteractsWithMetaOAuth;

    public function store(SelectMessengerPageRequest $request, Agent $agent, ConnectMessengerPageAction $connect): RedirectResponse
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

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->messengerPage?->delete();
        self::forgetPendingAccounts(MetaOAuthChannel::Messenger);

        return back();
    }
}

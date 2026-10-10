<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\ExplainsChannelConnectFailures;
use App\Http\Requests\Agent\Channel\ConnectWhatsappRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Illuminate\Http\RedirectResponse;

/**
 * Connect / disconnect an agent's WhatsApp business number through WhatsApp
 * Embedded Signup — one Chatvia Meta app for every workspace, so customers
 * click "Connect with Facebook" and pick their number inside Meta's popup
 * instead of pasting a token + app secret. The popup hands the browser an OAuth
 * code + the chosen WABA/phone ids, which it POSTs here;
 * {@see ConnectWhatsappNumberAction} exchanges the code, subscribes the WABA,
 * registers the number, and stores the token. Both gated by
 * `agent.permission:agent.channels`. Disconnect is a single delete (the
 * app-level webhook keeps getting silent 200s for a removed number).
 */
class AgentWhatsappChannelController
{
    use ExplainsChannelConnectFailures;

    public function store(ConnectWhatsappRequest $request, Agent $agent, ConnectWhatsappNumberAction $connect): RedirectResponse
    {
        try {
            $connect->execute($agent, $request->toData());
        } catch (ChannelConnectException $e) {
            return back()->withErrors(['whatsapp' => match ($e->failure) {
                ChannelConnectFailure::ProtectedAgent => self::publicAgentsOnly('WhatsApp'),
                ChannelConnectFailure::TakenByAnotherAgent => __('That WhatsApp number is already connected to another agent. Disconnect it there first.'),
                ChannelConnectFailure::Rejected => __('Meta couldn’t complete the WhatsApp connection. Please try connecting again.'),
            }]);
        }

        return back();
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->whatsappNumber?->delete();

        return back();
    }
}

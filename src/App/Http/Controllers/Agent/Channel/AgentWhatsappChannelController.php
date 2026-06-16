<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectWhatsappRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's WhatsApp business number through WhatsApp
 * Embedded Signup — one Chatvia Meta app for every workspace, so customers
 * click "Connect with Facebook" and pick their number inside Meta's popup
 * instead of pasting a token + app secret. The popup hands the browser an OAuth
 * code + the chosen WABA/phone ids, which it POSTs here;
 * {@see ConnectWhatsappNumberAction} exchanges the code, subscribes the WABA,
 * registers the number, and stores the token. Both gated by `agent.channels`.
 * Messaging channels are public-agents only. Disconnect is a single delete (the
 * app-level webhook keeps getting silent 200s for a removed number).
 */
class AgentWhatsappChannelController
{
    public function connect(ConnectWhatsappRequest $request, Agent $agent, ConnectWhatsappNumberAction $action): RedirectResponse
    {
        // WhatsApp (and messaging channels generally) is for public agents only —
        // a WhatsApp sender is anonymous to Chatvia and can't satisfy a protected
        // agent's access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'whatsapp' => 'WhatsApp is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $data = $request->toData();

        $takenByAnother = AgentWhatsappNumber::query()
            ->where('phone_number_id', $data->phoneNumberId)
            ->where('agent_id', '!=', $agent->id)
            ->exists();

        if ($takenByAnother) {
            return back()->withErrors([
                'whatsapp' => 'That WhatsApp number is already connected to another agent. Disconnect it there first.',
            ]);
        }

        try {
            $number = $action->execute($agent, $data);
        } catch (QueryException $e) {
            // Lost a race to another agent claiming the same number — the partial
            // unique index on (phone_number_id) WHERE enabled is the real source
            // of truth behind the exists() fast-path above. Any other DB error
            // is unexpected and should surface.
            if ($e->getCode() === '23505') {
                return back()->withErrors([
                    'whatsapp' => 'That WhatsApp number is already connected to another agent. Disconnect it there first.',
                ]);
            }

            throw $e;
        }

        if ($number === null) {
            return back()->withErrors([
                'whatsapp' => 'Meta couldn’t complete the WhatsApp connection. Please try connecting again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent): RedirectResponse
    {
        $agent->whatsappNumber?->delete();

        return back();
    }
}

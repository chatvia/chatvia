<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectWhatsappRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Actions\ConnectWhatsappNumberAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's WhatsApp business number. Both gated by
 * `agent.setup`. Connect validates the credentials via
 * {@see ConnectWhatsappNumberAction}; disconnect is a single delete, so it
 * stays inline (the app-level webhook can't be deregistered via API — Meta
 * keeps posting to the dead URL and gets silent 200s until the workspace
 * removes it from their Meta app).
 */
class AgentWhatsappChannelController
{
    public function connect(ConnectWhatsappRequest $request, Agent $agent, ConnectWhatsappNumberAction $action): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        // WhatsApp (and messaging channels generally) is for public agents
        // only — a WhatsApp sender is anonymous to Chatvia and can't satisfy
        // a protected agent's access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'access_token' => 'WhatsApp is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $number = $action->execute(
            $agent,
            $request->accessToken(),
            $request->appSecret(),
            $request->phoneNumberId(),
            $request->wabaId(),
        );

        if ($number === null) {
            return back()->withErrors([
                'access_token' => 'Meta rejected those credentials. Double-check the access token and phone number ID under WhatsApp → API Setup and try again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $agent->whatsappNumber?->delete();

        return back();
    }
}

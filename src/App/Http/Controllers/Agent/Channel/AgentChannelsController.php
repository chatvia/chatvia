<?php

namespace App\Http\Controllers\Agent\Channel;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent "Channels" page — connects the agent to messaging apps (Telegram
 * today, more to come). Gated by `agent.setup` on the route.
 */
class AgentChannelsController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $bot = $agent->telegramBot;

        return Inertia::render('agents/Channels/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'telegram' => $bot !== null
                ? ['connected' => true, 'bot_username' => $bot->bot_username, 'enabled' => $bot->enabled]
                : ['connected' => false, 'bot_username' => null, 'enabled' => false],
        ]);
    }
}

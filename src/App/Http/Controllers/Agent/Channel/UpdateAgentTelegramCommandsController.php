<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\UpdateTelegramCommandsRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Actions\UpdateTelegramCommandsAction;
use Illuminate\Http\RedirectResponse;

/**
 * Saves the custom commands of the agent's connected Telegram bot. Gated by
 * `agent.permission:agent.channels` on the route.
 */
class UpdateAgentTelegramCommandsController
{
    public function __invoke(UpdateTelegramCommandsRequest $request, Agent $agent, UpdateTelegramCommandsAction $update): RedirectResponse
    {
        if ($agent->telegramBot === null) {
            return back()->withErrors(['commands' => __('Connect a Telegram bot before adding commands.')]);
        }

        $update->execute($agent->telegramBot, $request->commands());

        return back();
    }
}

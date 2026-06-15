<?php

namespace App\Http\Controllers\Agent\Channel;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent "Channels" page — connects the agent to messaging apps (Telegram,
 * Slack, and Messenger today, more to come). Gated by `agent.setup` on the route.
 */
class AgentChannelsController
{
    public function __invoke(Request $request, Agent $agent, TelegramCommandRouter $commands): Response
    {
        $bot = $agent->telegramBot;
        $slack = $agent->slackApp;
        $messenger = $agent->messengerPage;
        $whatsapp = $agent->whatsappNumber;
        $teams = $agent->teamsBot;
        $instagram = $agent->instagramAccount;

        return Inertia::render('agents/Channels/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'telegram' => $bot !== null
                ? ['connected' => true, 'bot_username' => $bot->bot_username, 'enabled' => $bot->enabled, 'commands' => $commands->forEditing($bot)]
                : ['connected' => false, 'bot_username' => null, 'enabled' => false, 'commands' => []],
            // The webhook URLs embed the per-app secret — the workspace pastes
            // them into their Slack app's Event Subscriptions / Meta app's
            // Messenger Webhooks settings after connecting. For Messenger the
            // same secret doubles as the verify token of Meta's GET handshake.
            'slack' => $slack !== null
                ? ['connected' => true, 'team_name' => $slack->team_name, 'webhook_url' => route('slack.webhook', ['secret' => $slack->webhook_secret]), 'enabled' => $slack->enabled]
                : ['connected' => false, 'team_name' => null, 'webhook_url' => null, 'enabled' => false],
            'messenger' => $messenger !== null
                ? ['connected' => true, 'page_name' => $messenger->page_name, 'webhook_url' => route('messenger.webhook', ['secret' => $messenger->webhook_secret]), 'verify_token' => $messenger->webhook_secret, 'enabled' => $messenger->enabled]
                : ['connected' => false, 'page_name' => null, 'webhook_url' => null, 'verify_token' => null, 'enabled' => false],
            'whatsapp' => $whatsapp !== null
                ? ['connected' => true, 'display_phone_number' => $whatsapp->display_phone_number, 'verified_name' => $whatsapp->verified_name, 'webhook_url' => route('whatsapp.webhook', ['secret' => $whatsapp->webhook_secret]), 'verify_token' => $whatsapp->webhook_secret, 'enabled' => $whatsapp->enabled]
                : ['connected' => false, 'display_phone_number' => null, 'verified_name' => null, 'webhook_url' => null, 'verify_token' => null, 'enabled' => false],
            // The app id doubles as the "Open in Teams" deep-link target
            // (a bot's Teams user id is `28:{app_id}`).
            'msteams' => $teams !== null
                ? ['connected' => true, 'app_id' => $teams->app_id, 'webhook_url' => route('msteams.webhook', ['secret' => $teams->webhook_secret]), 'enabled' => $teams->enabled]
                : ['connected' => false, 'app_id' => null, 'webhook_url' => null, 'enabled' => false],
            'instagram' => $instagram !== null
                ? ['connected' => true, 'username' => $instagram->username, 'webhook_url' => route('instagram.webhook', ['secret' => $instagram->webhook_secret]), 'verify_token' => $instagram->webhook_secret, 'enabled' => $instagram->enabled]
                : ['connected' => false, 'username' => null, 'webhook_url' => null, 'verify_token' => null, 'enabled' => false],
        ]);
    }
}

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
 * Slack, Messenger, WhatsApp, Instagram, and Microsoft Teams). Gated by
 * `agent.channels` on the route.
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
            // Messenger connects via Facebook OAuth (one Chatvia Meta app), so
            // there's no per-page webhook URL to surface. `pick` holds the Pages
            // returned from Facebook awaiting the workspace's choice (id + name
            // only — the page tokens stay server-side in cache).
            'messenger' => [
                'connected' => $messenger !== null,
                'page_name' => $messenger?->page_name,
                'enabled' => (bool) $messenger?->enabled,
                'pick' => $this->messengerPick($agent),
            ],
            // WhatsApp connects via Embedded Signup (the Facebook JS SDK popup),
            // so there's no per-number webhook URL to surface — the frontend just
            // needs the shared app id + WhatsApp Login configuration to launch it.
            'whatsapp' => [
                'connected' => $whatsapp !== null,
                'display_phone_number' => $whatsapp?->display_phone_number,
                'verified_name' => $whatsapp?->verified_name,
                'enabled' => (bool) $whatsapp?->enabled,
                'app_id' => config('services.meta.app_id'),
                'config_id' => config('services.meta.whatsapp_config_id') ?: config('services.meta.config_id'),
                'graph_version' => config('services.meta.graph_version'),
            ],
            // The app id doubles as the "Open in Teams" deep-link target
            // (a bot's Teams user id is `28:{app_id}`).
            'msteams' => $teams !== null
                ? ['connected' => true, 'app_id' => $teams->app_id, 'webhook_url' => route('msteams.webhook', ['secret' => $teams->webhook_secret]), 'enabled' => $teams->enabled]
                : ['connected' => false, 'app_id' => null, 'webhook_url' => null, 'enabled' => false],
            // Instagram connects via Facebook OAuth (one Chatvia Meta app) — no
            // per-account webhook URL to surface. `pick` holds the IG-linked
            // accounts returned from Facebook awaiting the workspace's choice
            // (ig username + page name only — the page tokens stay server-side).
            'instagram' => [
                'connected' => $instagram !== null,
                'username' => $instagram?->username,
                'enabled' => (bool) $instagram?->enabled,
                'pick' => $this->instagramPick($agent),
            ],
        ]);
    }

    /**
     * The Pages Facebook just handed back, waiting for the workspace to pick
     * which one powers this agent — id + name only (tokens stay server-side).
     * Mirrors the session stash written by {@see AgentMessengerChannelController}.
     *
     * @return list<array{id: string, name: string}>|null
     */
    private function messengerPick(Agent $agent): ?array
    {
        $stash = session('messenger_oauth_pages');

        if (! is_array($stash) || ($stash['agent_id'] ?? null) !== $agent->id || ! is_array($stash['pages'] ?? null) || $stash['pages'] === []) {
            return null;
        }

        return array_map(static fn (array $page): array => [
            'id' => (string) $page['id'],
            'name' => (string) $page['name'],
        ], $stash['pages']);
    }

    /**
     * The IG-linked accounts Facebook just handed back, waiting for the
     * workspace to pick which one powers this agent — ig username + page name
     * only (tokens stay server-side). Mirrors the session stash written by
     * {@see AgentInstagramChannelController}.
     *
     * @return list<array{ig_id: string, ig_username: string, page_name: string}>|null
     */
    private function instagramPick(Agent $agent): ?array
    {
        $stash = session('instagram_oauth_pages');

        if (! is_array($stash) || ($stash['agent_id'] ?? null) !== $agent->id || ! is_array($stash['pages'] ?? null) || $stash['pages'] === []) {
            return null;
        }

        return array_map(static fn (array $page): array => [
            'ig_id' => (string) $page['ig_id'],
            'ig_username' => (string) $page['ig_username'],
            'page_name' => (string) $page['page_name'],
        ], $stash['pages']);
    }
}

<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent "Channels" page — connects the agent to messaging apps (Telegram,
 * Slack, Messenger, WhatsApp, Instagram, and Microsoft Teams). Gated by
 * `agent.permission:agent.channels` on the route.
 */
class AgentChannelsController
{
    use InteractsWithMetaOAuth;

    public function __invoke(Request $request, Agent $agent, TelegramCommandRouter $commands): Response
    {
        $agent->loadMissing(['telegramBot', 'slackApp', 'messengerPage', 'whatsappNumber', 'teamsBot', 'instagramAccount']);

        return Inertia::render('agents/Channels/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'open' => $this->channelToOpen($request),
            ConversationSource::Telegram->value => $this->telegram($agent, $commands),
            ConversationSource::Slack->value => $this->slack($agent),
            ConversationSource::Messenger->value => $this->messenger($agent),
            ConversationSource::Whatsapp->value => $this->whatsapp($agent),
            ConversationSource::MsTeams->value => $this->teams($agent),
            ConversationSource::Instagram->value => $this->instagram($agent),
        ]);
    }

    /**
     * Which channel slide-over to auto-open on load. Set after the
     * Messenger/Instagram OAuth round-trip (a full-page redirect) so the page
     * picker / error doesn't land in a closed sheet. Only messaging channels
     * are accepted, so a hand-crafted `?open=` can't open an unknown one.
     */
    private function channelToOpen(Request $request): ?string
    {
        $source = ConversationSource::tryFrom((string) $request->query('open'));

        return $source !== null && ! $source->isWeb() ? $source->value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function telegram(Agent $agent, TelegramCommandRouter $commands): array
    {
        $bot = $agent->telegramBot;

        return $bot !== null
            ? ['connected' => true, 'bot_username' => $bot->bot_username, 'enabled' => $bot->enabled, 'commands' => $commands->forEditing($bot)]
            : ['connected' => false, 'bot_username' => null, 'enabled' => false, 'commands' => []];
    }

    /**
     * The webhook URL embeds the per-app secret — the workspace pastes it into
     * their Slack app's Event Subscriptions after connecting.
     *
     * @return array<string, mixed>
     */
    private function slack(Agent $agent): array
    {
        $slack = $agent->slackApp;

        return $slack !== null
            ? ['connected' => true, 'team_name' => $slack->team_name, 'webhook_url' => route('slack.webhook', ['secret' => $slack->webhook_secret]), 'enabled' => $slack->enabled]
            : ['connected' => false, 'team_name' => null, 'webhook_url' => null, 'enabled' => false];
    }

    /**
     * Messenger connects via Facebook OAuth (one Chatvia Meta app), so there's
     * no per-page webhook URL. `available` is false until an admin sets up the
     * Messenger Meta app. `pick` holds the Pages returned from Facebook
     * awaiting the workspace's choice (id + name only — the page tokens stay
     * server-side in the session).
     *
     * @return array<string, mixed>
     */
    private function messenger(Agent $agent): array
    {
        $page = $agent->messengerPage;
        $pending = self::pendingAccounts($agent, MetaOAuthChannel::Messenger);

        return [
            'available' => MetaApp::Messenger->isConfigured(),
            'connected' => $page !== null,
            'page_name' => $page?->page_name,
            'enabled' => (bool) $page?->enabled,
            'pick' => $pending === [] ? null : array_map(static fn (array $pick): array => [
                'id' => (string) $pick['id'],
                'name' => (string) $pick['name'],
            ], $pending),
        ];
    }

    /**
     * WhatsApp connects via Embedded Signup (the Facebook JS SDK popup), so
     * there's no per-number webhook URL — the frontend just needs the WhatsApp
     * Meta app's id + Login configuration to launch it. `available` is false
     * until an admin sets up that app.
     *
     * @return array<string, mixed>
     */
    private function whatsapp(Agent $agent): array
    {
        $number = $agent->whatsappNumber;

        return [
            'available' => MetaApp::Whatsapp->isConfigured(),
            'connected' => $number !== null,
            'display_phone_number' => $number?->display_phone_number,
            'verified_name' => $number?->verified_name,
            'enabled' => (bool) $number?->enabled,
            'app_id' => MetaApp::Whatsapp->appId() ?: null,
            'config_id' => MetaApp::Whatsapp->configId(),
            'graph_version' => config('services.meta.graph_version'),
        ];
    }

    /**
     * The app id doubles as the "Open in Teams" deep-link target (a bot's
     * Teams user id is `28:{app_id}`).
     *
     * @return array<string, mixed>
     */
    private function teams(Agent $agent): array
    {
        $bot = $agent->teamsBot;

        return $bot !== null
            ? ['connected' => true, 'app_id' => $bot->app_id, 'webhook_url' => route('msteams.webhook', ['secret' => $bot->webhook_secret]), 'enabled' => $bot->enabled]
            : ['connected' => false, 'app_id' => null, 'webhook_url' => null, 'enabled' => false];
    }

    /**
     * Instagram connects via Facebook OAuth (one Chatvia Meta app) — no
     * per-account webhook URL. `available` is false until an admin sets up the
     * Instagram Meta app. `pick` holds the IG-linked accounts returned
     * from Facebook awaiting the workspace's choice (ig username + page name
     * only — the page tokens stay server-side in the session).
     *
     * @return array<string, mixed>
     */
    private function instagram(Agent $agent): array
    {
        $account = $agent->instagramAccount;
        $pending = self::pendingAccounts($agent, MetaOAuthChannel::Instagram);

        return [
            'available' => MetaApp::Instagram->isConfigured(),
            'connected' => $account !== null,
            'username' => $account?->username,
            'enabled' => (bool) $account?->enabled,
            'pick' => $pending === [] ? null : array_map(static fn (array $pick): array => [
                'ig_id' => (string) $pick['ig_id'],
                'ig_username' => (string) $pick['ig_username'],
                'page_name' => (string) $pick['page_name'],
            ], $pending),
        ];
    }
}

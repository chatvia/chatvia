<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Instagram\Data\InstagramChannelCardData;
use Domain\Channel\Messenger\Data\MessengerChannelCardData;
use Domain\Channel\Slack\Data\SlackChannelCardData;
use Domain\Channel\Teams\Data\TeamsChannelCardData;
use Domain\Channel\Telegram\Data\TelegramChannelCardData;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Domain\Channel\Whatsapp\Data\WhatsappChannelCardData;
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

        $bot = $agent->telegramBot;

        return Inertia::render('agents/Channels/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'open' => $this->channelToOpen($request),
            ConversationSource::Telegram->value => TelegramChannelCardData::fromBot($bot, $bot !== null ? $commands->forEditing($bot) : []),
            ConversationSource::Slack->value => SlackChannelCardData::fromApp($agent->slackApp),
            ConversationSource::Messenger->value => MessengerChannelCardData::fromPage($agent->messengerPage, self::pendingAccounts($agent, MetaOAuthChannel::Messenger)),
            ConversationSource::Whatsapp->value => WhatsappChannelCardData::fromNumber($agent->whatsappNumber),
            ConversationSource::MsTeams->value => TeamsChannelCardData::fromBot($agent->teamsBot),
            ConversationSource::Instagram->value => InstagramChannelCardData::fromAccount($agent->instagramAccount, self::pendingAccounts($agent, MetaOAuthChannel::Instagram)),
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
}

<?php

namespace Domain\Channel\Concerns;

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The messaging channels an {@see Agent} can be connected to — at most one
 * connection per channel. Kept in the Channel context so the agent aggregate
 * doesn't depend on every channel model; the relations stay ordinary `hasOne`s
 * so they eager-load as usual (`$agent->load('telegramBot')`).
 */
trait HasMessagingChannels
{
    /**
     * @return HasOne<AgentTelegramBot, $this>
     */
    public function telegramBot(): HasOne
    {
        return $this->hasOne(AgentTelegramBot::class);
    }

    /**
     * @return HasOne<AgentSlackApp, $this>
     */
    public function slackApp(): HasOne
    {
        return $this->hasOne(AgentSlackApp::class);
    }

    /**
     * The Facebook Page the agent answers on.
     *
     * @return HasOne<AgentMessengerPage, $this>
     */
    public function messengerPage(): HasOne
    {
        return $this->hasOne(AgentMessengerPage::class);
    }

    /**
     * The WhatsApp business number the agent answers on.
     *
     * @return HasOne<AgentWhatsappNumber, $this>
     */
    public function whatsappNumber(): HasOne
    {
        return $this->hasOne(AgentWhatsappNumber::class);
    }

    /**
     * The Azure bot the agent answers on in Microsoft Teams.
     *
     * @return HasOne<AgentTeamsBot, $this>
     */
    public function teamsBot(): HasOne
    {
        return $this->hasOne(AgentTeamsBot::class);
    }

    /**
     * The Instagram professional account the agent answers on.
     *
     * @return HasOne<AgentInstagramAccount, $this>
     */
    public function instagramAccount(): HasOne
    {
        return $this->hasOne(AgentInstagramAccount::class);
    }
}

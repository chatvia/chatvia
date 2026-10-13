<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an agent for the user at once and leaves the heavy work to the
 * queue. In one short transaction the agent is soft-deleted (route binding,
 * the hosted chat, the widget and every list stop seeing it), its slug and
 * custom domain are cleared (both are unique across the install, so they are
 * free again right away) and its messaging channels stop answering.
 * {@see PurgeAgentJob} then removes its chats, sources, settings, chat memory
 * and files in small batches, and finally the agent row.
 * Used by the dashboard and the public API.
 */
class DeleteAgentAction
{
    public function execute(Agent $agent): void
    {
        DB::transaction(function () use ($agent): void {
            $agent->forceFill(['slug' => null, 'domain' => null])->save();

            $agent->delete();

            $this->silenceMessagingChannels($agent);
        });

        PurgeAgentJob::dispatch($agent->id)->afterCommit();
    }

    /**
     * A channel webhook finds its connection without going through the agent,
     * so the connections are switched off here: until the purge removes them,
     * a message to the deleted agent's bot is ignored like one to a
     * disconnected bot.
     */
    private function silenceMessagingChannels(Agent $agent): void
    {
        $agent->telegramBot()->update(['enabled' => false]);
        $agent->slackApp()->update(['enabled' => false]);
        $agent->messengerPage()->update(['enabled' => false]);
        $agent->whatsappNumber()->update(['enabled' => false]);
        $agent->teamsBot()->update(['enabled' => false]);
        $agent->instagramAccount()->update(['enabled' => false]);
    }
}

<?php

namespace App\Observers;

use App\Models\AgentWebsite;

class AgentWebsiteObserver
{
    /**
     * Handle the AgentWebsite "created" event.
     */
    public function created(AgentWebsite $agentWebsite): void
    {
        //
    }

    /**
     * Handle the AgentWebsite "updated" event.
     */
    public function updated(AgentWebsite $agentWebsite): void
    {
        //
    }

    /**
     * Handle the AgentWebsite "deleted" event.
     */
    public function deleted(AgentWebsite $agentWebsite): void
    {
        $agentWebsite->chunks()->delete();
    }

    /**
     * Handle the AgentWebsite "restored" event.
     */
    public function restored(AgentWebsite $agentWebsite): void
    {
        //
    }

    /**
     * Handle the AgentWebsite "force deleted" event.
     */
    public function forceDeleted(AgentWebsite $agentWebsite): void
    {
        $this->deleted($agentWebsite);
    }
}

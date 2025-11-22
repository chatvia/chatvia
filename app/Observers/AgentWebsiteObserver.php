<?php

namespace App\Observers;

use App\Models\SourceWebsite;

class AgentWebsiteObserver
{
    /**
     * Handle the AgentWebsite "created" event.
     */
    public function created(SourceWebsite $agentWebsite): void
    {
        //
    }

    /**
     * Handle the AgentWebsite "updated" event.
     */
    public function updated(SourceWebsite $agentWebsite): void
    {
        //
    }

    /**
     * Handle the AgentWebsite "deleted" event.
     */
    public function deleted(SourceWebsite $agentWebsite): void
    {
        $agentWebsite->chunks()->delete();
    }

    /**
     * Handle the AgentWebsite "restored" event.
     */
    public function restored(SourceWebsite $agentWebsite): void
    {
        //
    }

    /**
     * Handle the AgentWebsite "force deleted" event.
     */
    public function forceDeleted(SourceWebsite $agentWebsite): void
    {
        $this->deleted($agentWebsite);
    }
}

<?php

namespace Domain\Source\Web\Observers;

use Domain\Source\Web\Models\SourceWebsite;

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
        // Chunks (incl. summary) cleaned up by Chunkable::bootChunkable() via the
        // deleting event — FK cascade handles chunk_references.

        // Detach all agents (removes agent_sources pivot records)
        $agentWebsite->agents()->detach();
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

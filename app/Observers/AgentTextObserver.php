<?php

namespace App\Observers;

use App\Models\SourceText;

class AgentTextObserver
{
    /**
     * Handle the AgentText "created" event.
     */
    public function created(SourceText $agentText): void
    {
        //
    }

    /**
     * Handle the AgentText "updated" event.
     */
    public function updated(SourceText $agentText): void
    {
        //
    }

    /**
     * Handle the AgentText "deleted" event.
     */
    public function deleted(SourceText $agentText): void
    {
        // Delete all chunks associated with this text
        $agentText->chunks()->delete();

        // Detach all agents (removes agent_sources pivot records)
        $agentText->agents()->detach();
    }

    /**
     * Handle the AgentText "restored" event.
     */
    public function restored(SourceText $agentText): void
    {
        //
    }

    /**
     * Handle the AgentText "force deleted" event.
     */
    public function forceDeleted(SourceText $agentText): void
    {
        $this->deleted($agentText);
    }
}

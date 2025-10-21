<?php

namespace App\Observers;

use App\Models\AgentText;

class AgentTextObserver
{
    /**
     * Handle the AgentText "created" event.
     */
    public function created(AgentText $agentText): void
    {
        //
    }

    /**
     * Handle the AgentText "updated" event.
     */
    public function updated(AgentText $agentText): void
    {
        //
    }

    /**
     * Handle the AgentText "deleted" event.
     */
    public function deleted(AgentText $agentText): void
    {
        $agentText->chunks()->delete();
    }

    /**
     * Handle the AgentText "restored" event.
     */
    public function restored(AgentText $agentText): void
    {
        //
    }

    /**
     * Handle the AgentText "force deleted" event.
     */
    public function forceDeleted(AgentText $agentText): void
    {
        $this->deleted($agentText);
    }
}

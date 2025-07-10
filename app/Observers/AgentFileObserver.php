<?php

namespace App\Observers;

use App\Models\AgentFile;

class AgentFileObserver
{
    public function created(AgentFile $agentFile): void
    {
        //
    }

    public function updated(AgentFile $agentFile): void
    {
        //
    }

    public function deleted(AgentFile $agentFile): void
    {
        //
    }

   public function restored(AgentFile $agentFile): void
    {
        //
    }

    public function forceDeleted(AgentFile $agentFile): void
    {
        //
    }
}

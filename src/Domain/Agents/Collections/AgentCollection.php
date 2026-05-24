<?php

namespace Domain\Agents\Collections;

use Illuminate\Database\Eloquent\Collection;

class AgentCollection extends Collection
{
    public function weeklyMessages()
    {
        return $this;
    }
}

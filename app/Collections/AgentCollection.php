<?php

namespace App\Collections;

use Illuminate\Database\Eloquent\Collection;

class AgentCollection extends Collection
{
    public function weeklyMessages()
    {
        return $this;
    }
}

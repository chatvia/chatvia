<?php

namespace Domain\Agents\Collections;

use Domain\Agents\Models\Agent;
use Illuminate\Database\Eloquent\Collection;

/**
 * @template TKey of array-key
 * @template TModel of Agent
 *
 * @extends Collection<TKey, TModel>
 */
class AgentCollection extends Collection
{
    public function weeklyMessages()
    {
        return $this;
    }
}

<?php

namespace App\Services\Widget;

use App\Models\Agent;
use Illuminate\Support\Facades\Cache;

class WidgetSession
{
    private Agent $agent;

    public function setAgent(Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getChatCache()
    {
        return Cache::driver('chat');
    }
}

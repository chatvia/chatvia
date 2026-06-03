<?php

namespace Domain\Widget\Services;

use Illuminate\Support\Facades\Cache;

class WidgetSession
{
    public function getChatCache()
    {
        return Cache::driver('chat');
    }
}

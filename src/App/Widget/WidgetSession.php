<?php

namespace App\Widget;

use Illuminate\Support\Facades\Cache;

class WidgetSession
{
    public function getChatCache()
    {
        return Cache::driver('chat');
    }
}

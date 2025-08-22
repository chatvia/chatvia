<?php

namespace App\Services\Widget;

use App\Models\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WidgetSession
{
    private Agent $agent;

    private const string WIDGET_SESSION_CACHE_KEY = 'chat-session.widget.%s.%s';

    public function setAgent(Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function generateSession(): string
    {
        $newChatSessionId = Str::uuid()->toString();

        $this->getChatCache()->put(
            key: $newChatSessionId,
            value: ['conversations' => []],
            ttl: now()->addDays(3),
        );

        return $newChatSessionId;
    }

    public function getChatCache()
    {
        return Cache::driver('chat');
    }
}

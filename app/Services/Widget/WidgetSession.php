<?php

namespace App\Services\Widget;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WidgetSession
{
    public function generateSession(string $externalAgentId): string
    {
        $newChatSessionId = 'chat-session.widget.'.$externalAgentId.'.'.Str::uuid()->toString();

        $this->getChatCache()->put(
            key: $newChatSessionId,
            value: ['conversations' => []],
            ttl: now()->addDay(),
        );

        return $newChatSessionId;
    }

    public function getChatCache()
    {
        return Cache::driver('chat');
    }
}

<?php

namespace App\Http\Controllers\Chat\Usage;

use Domain\Billing\Services\UserTokenSystem;
use Illuminate\Http\Request;

class MessagesUsage
{
    public function __invoke(Request $request): array
    {
        return [
            // "Can the user send?" — token-primary: a positive token balance.
            // Key stays `can_send_messages` (the chat client reads it).
            'can_send_messages' => app(UserTokenSystem::class)
                ->setUserId($request->attributes->get('agent_user_id'))
                ->hasTokenBalance(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Chat;

use App\Billing\Services\UserCreditSystem;
use Illuminate\Http\Request;

class ChatMessagesUsage
{
    public function __invoke(Request $request): array
    {
        return [
            'can_send_messages' => app(UserCreditSystem::class)
                ->setUserId($request->attributes->get('agent_user_id'))
                ->canSendMessages(),
        ];
    }
}

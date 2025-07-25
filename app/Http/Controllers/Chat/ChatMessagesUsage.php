<?php

namespace App\Http\Controllers\Chat;

use App\Services\Billing\UserCreditSystem;
use Illuminate\Http\Request;

class ChatMessagesUsage
{
    public function __invoke(Request $request)
    {
        return [
            'can_send_messages' => app(UserCreditSystem::class)
                ->setUserId($request->attributes->get('agent_user_id'))
                ->canSendMessages(),
        ];
    }
}

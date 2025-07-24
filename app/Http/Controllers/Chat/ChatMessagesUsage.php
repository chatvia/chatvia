<?php

namespace App\Http\Controllers\Chat;

use App\Services\Billing\Credit;
use Illuminate\Http\Request;

class ChatMessagesUsage
{
    public function __invoke(Request $request)
    {
        return [
            'can_send_messages' => app(Credit::class)
                ->setUserId($request->attributes->get('agent_user_id'))
                ->canSendMessages(),
        ];
    }
}

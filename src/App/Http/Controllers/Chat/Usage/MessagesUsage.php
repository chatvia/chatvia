<?php

namespace App\Http\Controllers\Chat\Usage;

use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Http\Request;

class MessagesUsage
{
    public function __invoke(Request $request): array
    {
        return [
            'can_send_messages' => app(UserCreditSystem::class)
                ->forWorkspace($request->attributes->get('agent_user_id'))
                ->canSendMessages(),
        ];
    }
}

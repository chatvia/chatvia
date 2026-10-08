<?php

namespace App\Http\Controllers\Chat\Usage;

use Domain\Billing\Contracts\Entitlements;
use Illuminate\Http\Request;

class MessagesUsage
{
    public function __invoke(Request $request, Entitlements $entitlements): array
    {
        return [
            'can_send_messages' => $entitlements->hasMessageCapacity(
                (int) $request->attributes->get('agent_user_id'),
            ),
        ];
    }
}

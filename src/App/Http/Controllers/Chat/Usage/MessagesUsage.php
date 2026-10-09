<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Usage;

use Domain\Billing\Contracts\Entitlements;
use Illuminate\Http\Request;

/**
 * Whether the agent's workspace can still send messages, so the hosted chat
 * can warn before the visitor types.
 */
class MessagesUsage
{
    /**
     * @return array{can_send_messages: bool}
     */
    public function __invoke(Request $request, Entitlements $entitlements): array
    {
        return [
            'can_send_messages' => $entitlements->hasMessageCapacity(
                (int) $request->attributes->get('agent_user_id'),
            ),
        ];
    }
}

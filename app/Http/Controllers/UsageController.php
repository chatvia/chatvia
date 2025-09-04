<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserQuota;
use App\Services\Billing\UserCreditSystem;
use Illuminate\Http\Request;

class UsageController
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        /** @var User $credit */
        $credit = $user->credit;

        $totalSentMessage = app(UserCreditSystem::class)
            ->setUserId($user->id)
            ->getTotalSentMessages();

        $totalAgents = $user->agents()->count();

        /** @var UserQuota $quota */
        $quota = $user->quota;

        $agentsLimit = $quota->agents_limit;

        $consumableMessages = $user->consumableMessages();

        $pagesConsumed = $user->pagesConsumed();
        $minutesConsumed = $user->minutesConsumed();
        $linksConsumed = $user->linksConsumed();

        return [
            'subscription' => [
                'plan' => $user->plan ?? 'Free',
            ],
            'agents' => [
                'total' => [
                    'limit' => $agentsLimit,
                    'created' => $totalAgents,
                    'remaining' => max(0, $agentsLimit - $totalAgents),
                ],
            ],
            'pages' => [
                'limit' => $quota->pages_limit,
                'used' => $pagesConsumed,
                'remaining' => ($quota->pages_limit - $pagesConsumed),
            ],
            'minutes' => [
                'limit' => $quota->minutes_limit,
                'used' => $minutesConsumed,
                'remaining' => ($quota->minutes_limit - $minutesConsumed),
            ],
            'links' => [
                'limit' => $quota->links_limit,
                'used' => $linksConsumed,
                'remaining' => ($quota->links_limit - $linksConsumed),
            ],
            'messages' => [
                'limit' => $consumableMessages,
                'sent' => $totalSentMessage,
                'remaining' => ($consumableMessages - $totalSentMessage),
            ],
            'cycle' => [
                'started_at' => $credit->started_at,
                'ends_at' => $credit->ends_at,
            ],
        ];
    }
}

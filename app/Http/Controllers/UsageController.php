<?php

namespace App\Http\Controllers;

use App\Models\Usage;
use App\Models\User;
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

        /** @var Usage $usage */
        $usage = $user->usage;

        $totalSentMessage = app(UserCreditSystem::class)
            ->setUserId($user->id)
            ->getTotalSentMessages();

        $agentCounts = $user->agents()
            ->whereIn('type', ['knowledge', 'instruction', 'widget', 'knowledge_base'])
            ->get()
            ->countBy('type');

        // Sum all agent types for unified limit
        $totalAgents =
                      $agentCounts->get('widget', 0) +
                      $agentCounts->get('knowledge_base', 0);

        $agentsLimit = $user->agents_limit;

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
                'limit' => $user->pages_limit,
                'used' => $pagesConsumed,
                'remaining' => ($user->pages_limit - $pagesConsumed),
            ],
            'minutes' => [
                'limit' => $user->minutes_limit,
                'used' => $minutesConsumed,
                'remaining' => ($user->minutes_limit - $minutesConsumed),
            ],
            'links' => [
                'limit' => $user->links_limit,
                'used' => $linksConsumed,
                'remaining' => ($user->links_limit - $linksConsumed),
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

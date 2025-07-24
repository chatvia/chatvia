<?php

namespace App\Http\Controllers;

use App\Models\Usage;
use App\Models\User;
use App\Services\Billing\Credit;
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

        $totalSentMessage = app(Credit::class)
            ->setUserId($user->id)
            ->getTotalSentMessages();

        $agentCounts = $user->agents()
            ->whereIn('type', ['knowledge', 'instruction'])
            ->get()
            ->countBy('type');

        $knowledgeAgentLimit = $user->agents_knowledge_limit;
        $instructionAgentLimit = $user->agents_instruction_limit;

        return [
            'subscription' => [
                'plan' => $user->plan,
            ],
            'agents' => [
                'knowledge' => [
                    'limit' => $knowledgeAgentLimit,
                    'created' => $agentCounts->get('knowledge', 0),
                    'remaining' => max(0, $knowledgeAgentLimit - $agentCounts->get('knowledge', 0)),
                ],
                'instruction' => [
                    'limit' => $instructionAgentLimit,
                    'created' => $agentCounts->get('instruction', 0),
                    'remaining' => max(0, $instructionAgentLimit - $agentCounts->get('instruction', 0)),
                ],
            ],
            'pages' => [
                'limit' => $credit->pages,
                'used' => $usage->pages,
                'remaining' => ($credit->pages - $usage->pages),
            ],
            'messages' => [
                'sent' => $totalSentMessage,
                'limit' => $credit->messages,
                'remaining' => ($credit->messages - $totalSentMessage),
            ],
            'cycle' => [
                'started_at' => $credit->started_at,
                'ends_at' => $credit->ends_at,
            ],
        ];
    }
}

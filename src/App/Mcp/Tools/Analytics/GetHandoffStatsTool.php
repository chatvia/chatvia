<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Outcomes\HandoffStatsQuery;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-handoff-stats: escalations to a human.
 */
#[Description(<<<'TXT'
Human-handoff stats for a period: how many conversations were escalated from the
AI to a human, the handoff rate (share of conversations), and how many handoffs
are pending right now (awaiting a human, not period-bound). Answers "how many
chats went to a human?", "what's my escalation rate?", "any handoffs waiting?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetHandoffStatsTool extends AnalyticsTool
{
    public function __construct(private readonly HandoffStatsQuery $handoffs) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->handoffs->get($scope);

        $summary = sprintf(
            '%s: %s of %s conversations went to a human (%s%% handoff rate). %s handoff%s pending right now.',
            ucfirst($scope->period->label),
            number_format($data->handoffs),
            number_format($data->conversations),
            $data->handoffRatePct,
            number_format($data->pendingNow),
            $data->pendingNow === 1 ? '' : 's',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => $this->periodPayload($scope->period),
            'conversations' => $data->conversations,
            'handoffs' => $data->handoffs,
            'handoff_rate_pct' => $data->handoffRatePct,
            'pending_now' => $data->pendingNow,
        ]);
    }
}

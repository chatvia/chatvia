<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\Activity\AgentActivityData;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Activity\AgentBreakdownQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Number;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-agent-breakdown: traffic per agent, always for the whole account.
 */
#[Description(<<<'TXT'
Per-agent activity for a period: each agent ranked by total messages and
conversations. Answers "which agent gets the most traffic?", "how is volume
split across my agents?". Only meaningful for accounts with more than one agent.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetAgentBreakdownTool extends AnalyticsTool
{
    public function __construct(private readonly AgentBreakdownQuery $agents) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $agents = array_map(static fn (AgentActivityData $agent): array => [
            'agent_id' => $agent->agentId,
            'name' => $agent->name,
            'messages' => $agent->messages,
            'conversations' => $agent->conversations,
        ], $this->agents->get($scope));
        $period = $scope->period;

        $summary = $agents === []
            ? sprintf('No agent activity %s.', $period->label)
            : sprintf(
                'Busiest agent %s is %s with %s messages across %s conversations.',
                $period->label,
                $agents[0]['name'],
                Number::format($agents[0]['messages']),
                Number::format($agents[0]['conversations']),
            );

        $structured = [
            'period' => $this->periodPayload($period),
            'agents' => $agents,
        ];

        if (count($agents) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($agents, 'name'),
                [['name' => 'Messages', 'data' => array_column($agents, 'messages')]],
                "Messages by agent — {$period->label}",
                horizontal: true,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    /**
     * Whole account only — narrowing to one agent would defeat the comparison.
     *
     * @param  array<string, mixed>  $input
     */
    protected function filter(array $input): AnalyticsFilterData
    {
        $filter = parent::filter($input);

        return new AnalyticsFilterData($filter->period, $filter->startDate, $filter->endDate);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $common = $this->commonSchema($schema);

        return [
            'period' => $common['period'],
            'start_date' => $common['start_date'],
            'end_date' => $common['end_date'],
        ];
    }
}

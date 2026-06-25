<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
Leads captured for a period: the total, the breakdown by status (new, contacted,
qualified, converted, lost), how many have been contacted, and the conversion
rate (leads per 100 conversations). Answers "how many leads this cycle?", "what's
my lead conversion rate?", "how many leads still need contacting?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetLeadStatsTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate($this->commonRules());

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);

        $total = DB::table('form_submissions')
            ->whereIn('agent_id', $scope['ids'])
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();

        $conversations = (int) $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->sum('conversations_started');

        $conversionRate = $this->pct($total, $conversations);

        $summary = sprintf(
            '%s: %s leads captured. Conversion rate %s%% of %s conversations.',
            ucfirst($period->label),
            number_format($total),
            $conversionRate,
            number_format($conversations),
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'total_leads' => $total,
            'conversations' => $conversations,
            'conversion_rate_pct' => $conversionRate,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->commonSchema($schema);
    }
}

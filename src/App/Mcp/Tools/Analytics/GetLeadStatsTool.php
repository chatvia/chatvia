<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Outcomes\LeadStatsQuery;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-lead-stats: leads captured and the conversion rate.
 */
#[Description(<<<'TXT'
Leads captured for a period: the total, the breakdown by status (new, contacted,
qualified, converted, lost), how many have been contacted, and the conversion
rate (leads per 100 conversations). Answers "how many leads this month?", "what's
my lead conversion rate?", "how many leads still need contacting?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetLeadStatsTool extends AnalyticsTool
{
    public function __construct(private readonly LeadStatsQuery $leads) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->leads->get($scope);

        $summary = sprintf(
            '%s: %s leads captured. Conversion rate %s%% of %s conversations.',
            Str::ucfirst($scope->period->label),
            Number::format($data->totalLeads),
            $data->conversionRatePct,
            Number::format($data->conversations),
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => $this->periodPayload($scope->period),
            'total_leads' => $data->totalLeads,
            'conversations' => $data->conversations,
            'conversion_rate_pct' => $data->conversionRatePct,
        ]);
    }
}

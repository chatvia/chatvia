<?php

namespace Domain\Analytics\Queries\Account\Outcomes;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Outcomes\LeadStatsData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * Leads captured — submitted forms — and leads per 100 conversations.
 */
class LeadStatsQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): LeadStatsData
    {
        $leads = $this->leadCount($scope);
        $conversations = (int) $this->hourlyRollups($scope)->sum('conversations_started');

        return new LeadStatsData(
            totalLeads: $leads,
            conversations: $conversations,
            conversionRatePct: $this->pct($leads, $conversations),
        );
    }
}
